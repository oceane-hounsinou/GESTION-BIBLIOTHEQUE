<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function resolveLoanRedirect(): string
{
    $allowedTargets = ['emprunts.php', 'livres.php', 'index.php'];
    $requestedTarget = cleanInput($_GET['redirect'] ?? '');

    if (in_array($requestedTarget, $allowedTargets, true)) {
        return $requestedTarget;
    }

    return 'emprunts.php';
}

$redirectPath = resolveLoanRedirect();
$loanId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($loanId <= 0) {
    setFlash('danger', 'Emprunt introuvable.');
    redirect($redirectPath);
}

$conn = getConnection();
$transactionStarted = false;

try {
    $conn->begin_transaction();
    $transactionStarted = true;

    $loanStmt = $conn->prepare(
        "SELECT em.id, em.statut, em.livre_id, l.titre
        FROM Emprunt AS em
        INNER JOIN Livre AS l ON l.id = em.livre_id
        WHERE em.id = ?
        FOR UPDATE"
    );
    $loanStmt->bind_param('i', $loanId);
    $loanStmt->execute();
    $loan = $loanStmt->get_result()->fetch_assoc();
    $loanStmt->close();

    if (!$loan) {
        throw new RuntimeException('Emprunt introuvable.');
    }

    if ($loan['statut'] === 'retourne') {
        $conn->commit();
        setFlash('info', 'Ce prêt est déjà marqué comme retourné.');
        redirect($redirectPath);
    }

    $returnStmt = $conn->prepare(
        "UPDATE Emprunt
        SET statut = 'retourne', date_retour = NOW()
        WHERE id = ?"
    );
    $returnStmt->bind_param('i', $loanId);
    $returnStmt->execute();
    $returnStmt->close();

    $bookStmt = $conn->prepare('UPDATE Livre SET disponible = TRUE WHERE id = ?');
    $bookStmt->bind_param('i', $loan['livre_id']);
    $bookStmt->execute();
    $bookStmt->close();

    $conn->commit();

    setFlash('success', 'Le retour du livre "' . $loan['titre'] . '" a été enregistré.');
    redirect($redirectPath);
} catch (Throwable $exception) {
    if ($transactionStarted) {
        $conn->rollback();
    }

    $message = $exception instanceof RuntimeException
        ? $exception->getMessage()
        : 'Impossible d\'enregistrer le retour pour le moment.';

    setFlash('danger', $message);
    redirect($redirectPath);
}
