<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('emprunts.php');
}

$conn = getConnection();
$bookId = (int) ($_POST['livre_id'] ?? 0);
$borrowerId = (int) ($_POST['emprunteur_id'] ?? 0);
$expectedReturnDate = cleanInput($_POST['date_retour_prevue'] ?? '');
$notes = cleanInput($_POST['notes'] ?? '');
$redirectPath = $bookId > 0 ? 'emprunts.php?livre_id=' . $bookId : 'emprunts.php';

if ($bookId <= 0 || $borrowerId <= 0) {
    setFlash('danger', 'Veuillez sélectionner un livre et un emprunteur.');
    redirect($redirectPath);
}

if ($expectedReturnDate !== '' && strtotime($expectedReturnDate) === false) {
    setFlash('danger', 'La date de retour prévue est invalide.');
    redirect($redirectPath);
}

if ($expectedReturnDate !== '' && $expectedReturnDate < date('Y-m-d')) {
    setFlash('danger', 'La date de retour prévue ne peut pas être antérieure à aujourd\'hui.');
    redirect($redirectPath);
}

if (mb_strlen($notes) > 255) {
    setFlash('danger', 'Les notes ne doivent pas dépasser 255 caractères.');
    redirect($redirectPath);
}

$transactionStarted = false;

try {
    $conn->begin_transaction();
    $transactionStarted = true;

    $bookStmt = $conn->prepare('SELECT id, titre, disponible FROM Livre WHERE id = ? FOR UPDATE');
    $bookStmt->bind_param('i', $bookId);
    $bookStmt->execute();
    $book = $bookStmt->get_result()->fetch_assoc();
    $bookStmt->close();

    if (!$book) {
        throw new RuntimeException('Le livre sélectionné est introuvable.');
    }

    if ((int) $book['disponible'] !== 1) {
        throw new RuntimeException('Ce livre n\'est plus disponible pour un nouvel emprunt.');
    }

    $borrowerStmt = $conn->prepare('SELECT id FROM Emprunteur WHERE id = ?');
    $borrowerStmt->bind_param('i', $borrowerId);
    $borrowerStmt->execute();
    $borrowerExists = $borrowerStmt->get_result()->fetch_assoc();
    $borrowerStmt->close();

    if (!$borrowerExists) {
        throw new RuntimeException('L\'emprunteur sélectionné est introuvable.');
    }

    $insertStmt = $conn->prepare(
        "INSERT INTO Emprunt (livre_id, emprunteur_id, date_retour_prevue, notes)
        VALUES (?, ?, NULLIF(?, ''), NULLIF(?, ''))"
    );
    $insertStmt->bind_param('iiss', $bookId, $borrowerId, $expectedReturnDate, $notes);
    $insertStmt->execute();
    $insertStmt->close();

    $updateStmt = $conn->prepare('UPDATE Livre SET disponible = FALSE WHERE id = ?');
    $updateStmt->bind_param('i', $bookId);
    $updateStmt->execute();
    $updateStmt->close();

    $conn->commit();

    setFlash('success', 'L\'emprunt du livre "' . $book['titre'] . '" a été enregistré.');
    redirect('emprunts.php');
} catch (Throwable $exception) {
    if ($transactionStarted) {
        $conn->rollback();
    }

    $message = $exception instanceof RuntimeException
        ? $exception->getMessage()
        : 'Impossible d\'enregistrer l\'emprunt pour le moment.';

    setFlash('danger', $message);
    redirect($redirectPath);
}
