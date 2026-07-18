<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

$conn = getConnection();
$stats = getDashboardStats($conn);
$recentTransactions = fetchAllRows(
    $conn->query(
        "SELECT em.date_emprunt, em.date_retour, em.statut, l.titre,
                CONCAT(emp.prenom, ' ', emp.nom) AS emprunteur_nom
        FROM Emprunt AS em
        INNER JOIN Livre AS l ON l.id = em.livre_id
        INNER JOIN Emprunteur AS emp ON emp.id = em.emprunteur_id
        ORDER BY COALESCE(em.date_retour, em.date_emprunt) DESC
        LIMIT 6"
    )
);
$overdueLoans = fetchAllRows(
    $conn->query(
        "SELECT em.date_retour_prevue, l.titre,
                CONCAT(emp.prenom, ' ', emp.nom) AS emprunteur_nom
        FROM Emprunt AS em
        INNER JOIN Livre AS l ON l.id = em.livre_id
        INNER JOIN Emprunteur AS emp ON emp.id = em.emprunteur_id
        WHERE em.statut = 'en_cours'
          AND em.date_retour_prevue IS NOT NULL
          AND em.date_retour_prevue < CURDATE()
        ORDER BY em.date_retour_prevue ASC
        LIMIT 5"
    )
);

renderHeader('Accueil', 'dashboard');
renderPageTitle(
    'Accueil',
    'Accédez rapidement aux livres, aux catégories et aux emprunts.',
    [
        ['href' => 'livres.php', 'label' => 'Voir les livres', 'class' => 'btn-outline-secondary'],
        ['href' => 'categories.php', 'label' => 'Gérer les catégories', 'class' => 'btn-outline-secondary'],
        ['href' => 'emprunts.php', 'label' => 'Gérer les emprunts', 'class' => 'btn-primary'],
    ]
);
?>

<section class="row g-3 mb-4">
    <?php renderStatCard('Total des livres', (string) ($stats['total_livres'] ?? 0)); ?>
    <?php renderStatCard('Catégories', (string) ($stats['total_categories'] ?? 0)); ?>
    <?php renderStatCard('Livres disponibles', (string) ($stats['livres_disponibles'] ?? 0)); ?>
    <?php renderStatCard('Prêts en cours', (string) ($stats['emprunts_en_cours'] ?? 0)); ?>
    <?php renderStatCard('Retards', (string) ($stats['emprunts_en_retard'] ?? 0)); ?>
</section>

<section class="row g-4">
    <div class="col-lg-8">
        <div class="card simple-card shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 mb-0">Transactions récentes</h2>
                    <a href="emprunts.php" class="btn btn-sm btn-outline-secondary">Voir les emprunts</a>
                </div>

                <?php if ($recentTransactions === []): ?>
                    <div class="empty-state p-4">
                        <p class="mb-2">Aucune transaction enregistrée pour le moment.</p>
                        <a href="emprunts.php" class="btn btn-primary">Créer un emprunt</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Livre</th>
                                    <th>Emprunteur</th>
                                    <th>Date</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentTransactions as $transaction): ?>
                                    <tr>
                                        <td><?php echo e($transaction['titre']); ?></td>
                                        <td><?php echo e($transaction['emprunteur_nom']); ?></td>
                                        <td>
                                            <?php
                                            $isReturned = $transaction['statut'] === 'retourne';
                                            $label = $isReturned ? 'Retour le ' : 'Emprunt le ';
                                            $dateValue = $isReturned ? $transaction['date_retour'] : $transaction['date_emprunt'];
                                            echo e($label . formatDate($dateValue));
                                            ?>
                                        </td>
                                        <td><?php echo e($isReturned ? 'Retourné' : 'En cours'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card simple-card shadow-sm h-100">
            <div class="card-body">
                <h2 class="h5 mb-3">Retards</h2>

                <?php if ($overdueLoans === []): ?>
                    <p class="mb-0 text-secondary">Aucun emprunt en retard.</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($overdueLoans as $loan): ?>
                            <div class="list-group-item px-0">
                                <div class="fw-semibold"><?php echo e($loan['titre']); ?></div>
                                <div class="small text-secondary"><?php echo e($loan['emprunteur_nom']); ?></div>
                                <div class="small text-danger">Prévu le <?php echo e(formatDate($loan['date_retour_prevue'], false)); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php renderFooter(); ?>
