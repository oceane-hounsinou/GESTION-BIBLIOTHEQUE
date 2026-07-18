<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

$conn = getConnection();
$requestedBookId = isset($_GET['livre_id']) ? (int) $_GET['livre_id'] : 0;
$pageAlerts = [];
$preselectedBook = null;
$defaultReturnDate = date('Y-m-d', strtotime('+14 days'));

if ($requestedBookId > 0) {
    $selectedBookStmt = $conn->prepare('SELECT id, titre, auteur, disponible FROM Livre WHERE id = ?');
    $selectedBookStmt->bind_param('i', $requestedBookId);
    $selectedBookStmt->execute();
    $preselectedBook = $selectedBookStmt->get_result()->fetch_assoc();
    $selectedBookStmt->close();

    if (!$preselectedBook) {
        $pageAlerts[] = [
            'type' => 'warning',
            'message' => 'Le livre demandé est introuvable.',
        ];
    } elseif ((int) $preselectedBook['disponible'] !== 1) {
        $pageAlerts[] = [
            'type' => 'warning',
            'message' => 'Le livre sélectionné est déjà emprunté.',
        ];
    }
}

$loanSummary = $conn->query(
    "SELECT
        COUNT(*) AS total,
        COALESCE(SUM(statut = 'en_cours'), 0) AS en_cours,
        COALESCE(SUM(statut = 'retourne'), 0) AS retournes,
        COALESCE(SUM(statut = 'en_cours' AND date_retour_prevue IS NOT NULL AND date_retour_prevue < CURDATE()), 0) AS en_retard
    FROM Emprunt"
)->fetch_assoc() ?: [];

$availableBooks = fetchAllRows(
    $conn->query(
        "SELECT id, titre, auteur
        FROM Livre
        WHERE disponible = TRUE
        ORDER BY titre ASC, auteur ASC"
    )
);
$borrowers = fetchAllRows(
    $conn->query(
        "SELECT id, nom, prenom
        FROM Emprunteur
        ORDER BY prenom ASC, nom ASC"
    )
);
$activeLoans = fetchAllRows(
    $conn->query(
        "SELECT em.id, em.date_emprunt, em.date_retour_prevue, em.notes,
                l.titre,
                CONCAT(emp.prenom, ' ', emp.nom) AS emprunteur_nom,
                CASE
                    WHEN em.date_retour_prevue IS NOT NULL AND em.date_retour_prevue < CURDATE() THEN 1
                    ELSE 0
                END AS is_overdue
        FROM Emprunt AS em
        INNER JOIN Livre AS l ON l.id = em.livre_id
        INNER JOIN Emprunteur AS emp ON emp.id = em.emprunteur_id
        WHERE em.statut = 'en_cours'
        ORDER BY em.date_retour_prevue IS NULL ASC, em.date_retour_prevue ASC, em.date_emprunt DESC"
    )
);
$recentHistory = fetchAllRows(
    $conn->query(
        "SELECT em.date_emprunt, em.date_retour, em.date_retour_prevue, em.statut,
                l.titre,
                CONCAT(emp.prenom, ' ', emp.nom) AS emprunteur_nom
        FROM Emprunt AS em
        INNER JOIN Livre AS l ON l.id = em.livre_id
        INNER JOIN Emprunteur AS emp ON emp.id = em.emprunteur_id
        ORDER BY COALESCE(em.date_retour, em.date_emprunt) DESC
        LIMIT 8"
    )
);

$selectedBookId = $preselectedBook && (int) $preselectedBook['disponible'] === 1 ? (string) $preselectedBook['id'] : '';
$canCreateLoan = $availableBooks !== [] && $borrowers !== [];

if ($availableBooks === []) {
    $pageAlerts[] = [
        'type' => 'warning',
        'message' => 'Aucun livre disponible pour un nouvel emprunt.',
    ];
}

if ($borrowers === []) {
    $pageAlerts[] = [
        'type' => 'warning',
        'message' => 'Aucun emprunteur enregistré dans la base.',
    ];
}

renderHeader('Emprunts', 'emprunts');
renderPageTitle(
    'Emprunts',
    'Créez un prêt et suivez les retours.',
    [
        ['href' => 'index.php', 'label' => 'Retour à l\'accueil', 'class' => 'btn-outline-secondary'],
        ['href' => 'livres.php', 'label' => 'Voir les livres', 'class' => 'btn-primary'],
    ]
);
?>

<section class="row g-3 mb-4">
    <?php renderStatCard('Prêts en cours', (string) ($loanSummary['en_cours'] ?? 0)); ?>
    <?php renderStatCard('Retours', (string) ($loanSummary['retournes'] ?? 0)); ?>
    <?php renderStatCard('Retards', (string) ($loanSummary['en_retard'] ?? 0)); ?>
    <?php renderStatCard('Livres disponibles', (string) count($availableBooks)); ?>
</section>

<section class="row g-4 mb-4">
    <div class="col-lg-5">
        <div class="card simple-card shadow-sm h-100">
            <div class="card-body">
                <h2 class="h5 mb-3">Nouveau prêt</h2>

                <?php foreach ($pageAlerts as $alert): ?>
                    <div class="alert alert-<?php echo e(mapFlashToBootstrap($alert['type'])); ?>">
                        <?php echo e($alert['message']); ?>
                    </div>
                <?php endforeach; ?>

                <form method="post" action="traiter_emprunt.php" class="row g-3">
                    <div class="col-12">
                        <label for="livre_id" class="form-label">Livre</label>
                        <select class="form-select" id="livre_id" name="livre_id" required>
                            <option value="">-- Sélectionner un livre --</option>
                            <?php foreach ($availableBooks as $book): ?>
                                <option value="<?php echo e((string) $book['id']); ?>" <?php echo $selectedBookId === (string) $book['id'] ? 'selected' : ''; ?>>
                                    <?php echo e($book['titre'] . ' - ' . $book['auteur']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label for="emprunteur_id" class="form-label">Emprunteur</label>
                        <select class="form-select" id="emprunteur_id" name="emprunteur_id" required>
                            <option value="">-- Sélectionner un emprunteur --</option>
                            <?php foreach ($borrowers as $borrower): ?>
                                <option value="<?php echo e((string) $borrower['id']); ?>">
                                    <?php echo e($borrower['prenom'] . ' ' . $borrower['nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label for="date_retour_prevue" class="form-label">Retour prévu</label>
                        <input type="date" class="form-control" id="date_retour_prevue" name="date_retour_prevue" min="<?php echo e(date('Y-m-d')); ?>" value="<?php echo e($defaultReturnDate); ?>">
                    </div>
                    <div class="col-12">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" maxlength="255"></textarea>
                    </div>
                    <div class="col-12 d-flex flex-wrap justify-content-end gap-2">
                        <a href="livres.php" class="btn btn-outline-secondary">Annuler</a>
                        <button type="submit" class="btn btn-primary" <?php echo $canCreateLoan ? '' : 'disabled'; ?>>Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card simple-card shadow-sm h-100">
            <div class="card-body">
                <h2 class="h5 mb-3">Prêts en cours</h2>

                <?php if ($activeLoans === []): ?>
                    <p class="mb-0 text-secondary">Aucun prêt en cours.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Livre</th>
                                    <th>Emprunteur</th>
                                    <th>Emprunt</th>
                                    <th>Retour prévu</th>
                                    <th>Notes</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($activeLoans as $loan): ?>
                                    <tr>
                                        <td>
                                            <div><?php echo e($loan['titre']); ?></div>
                                            <?php if ((int) $loan['is_overdue'] === 1): ?>
                                                <div class="small text-danger">En retard</div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo e($loan['emprunteur_nom']); ?></td>
                                        <td><?php echo e(formatDate($loan['date_emprunt'])); ?></td>
                                        <td>
                                            <?php echo !empty($loan['date_retour_prevue']) ? e(formatDate($loan['date_retour_prevue'], false)) : '<span class="text-secondary">Non défini</span>'; ?>
                                        </td>
                                        <td><?php echo $loan['notes'] !== null && $loan['notes'] !== '' ? e($loan['notes']) : '<span class="text-secondary">-</span>'; ?></td>
                                        <td class="text-end">
                                            <a href="retour_livre.php?id=<?php echo e((string) $loan['id']); ?>&amp;redirect=emprunts.php" class="btn btn-sm btn-success" onclick="return confirm('Confirmer le retour de ce livre ?');">Retour</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<section class="card simple-card shadow-sm">
    <div class="card-body">
        <h2 class="h5 mb-3">Historique récent</h2>

        <?php if ($recentHistory === []): ?>
            <p class="mb-0 text-secondary">Aucun historique disponible.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Livre</th>
                            <th>Emprunteur</th>
                            <th>Emprunt</th>
                            <th>Retour</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentHistory as $entry): ?>
                            <tr>
                                <td><?php echo e($entry['titre']); ?></td>
                                <td><?php echo e($entry['emprunteur_nom']); ?></td>
                                <td><?php echo e(formatDate($entry['date_emprunt'])); ?></td>
                                <td>
                                    <?php if (!empty($entry['date_retour'])): ?>
                                        <?php echo e(formatDate($entry['date_retour'])); ?>
                                    <?php elseif (!empty($entry['date_retour_prevue'])): ?>
                                        <span class="text-secondary">Prévu le <?php echo e(formatDate($entry['date_retour_prevue'], false)); ?></span>
                                    <?php else: ?>
                                        <span class="text-secondary">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo e($entry['statut'] === 'retourne' ? 'Retourné' : 'En cours'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php renderFooter(); ?>
