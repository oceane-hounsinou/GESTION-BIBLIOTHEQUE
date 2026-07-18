<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$conn = getConnection();
$bookId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($bookId <= 0) {
    setFlash('danger', 'Livre introuvable.');
    redirect('livres.php');
}

$bookStmt = $conn->prepare('SELECT titre FROM Livre WHERE id = ?');
$bookStmt->bind_param('i', $bookId);
$bookStmt->execute();
$book = $bookStmt->get_result()->fetch_assoc();
$bookStmt->close();

if (!$book) {
    setFlash('danger', 'Livre introuvable.');
    redirect('livres.php');
}

$loanStmt = $conn->prepare('SELECT COUNT(*) AS total FROM Emprunt WHERE livre_id = ?');
$loanStmt->bind_param('i', $bookId);
$loanStmt->execute();
$loanCount = (int) $loanStmt->get_result()->fetch_assoc()['total'];
$loanStmt->close();

if ($loanCount > 0) {
    setFlash('warning', 'Ce livre ne peut pas être supprimé car il est lié à un historique d\'emprunts.');
    redirect('livres.php');
}

$deleteStmt = $conn->prepare('DELETE FROM Livre WHERE id = ?');
$deleteStmt->bind_param('i', $bookId);
$deleteStmt->execute();
$deleteStmt->close();

setFlash('success', 'Le livre "' . $book['titre'] . '" a été supprimé.');
redirect('livres.php');
