<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

$conn = getConnection();
$search = cleanInput($_GET['q'] ?? '');
$statusFilter = cleanInput($_GET['statut'] ?? 'tous');
$categoryFilter = isset($_GET['categorie_id']) ? (int) $_GET['categorie_id'] : 0;
$categories = getCategories($conn);

$summary = $conn->query(
    "SELECT
        (SELECT COUNT(*) FROM Livre) AS total,
        (SELECT COUNT(*) FROM Categorie) AS total_categories,
        (SELECT COUNT(*) FROM Livre WHERE disponible = TRUE) AS disponibles,
        (SELECT COUNT(*) FROM Livre WHERE disponible = FALSE) AS empruntes"
)->fetch_assoc();

$sql = "SELECT l.*,
            c.nom AS categorie_nom,
            em.id AS emprunt_id,
            em.date_retour_prevue,
            CONCAT(emp.prenom, ' ', emp.nom) AS emprunteur_actuel
        FROM Livre AS l
        INNER JOIN Categorie AS c ON c.id = l.categorie_id
        LEFT JOIN Emprunt AS em ON em.livre_id = l.id AND em.statut = 'en_cours'
        LEFT JOIN Emprunteur AS emp ON emp.id = em.emprunteur_id
        WHERE 1 = 1";
$types = '';
$params = [];

if ($search !== '') {
    $sql .= " AND (l.titre LIKE ? OR l.auteur LIKE ? OR l.isbn LIKE ? OR l.editeur LIKE ? OR c.nom LIKE ?)";
    $like = '%' . $search . '%';
    $types .= 'sssss';
    $params = [$like, $like, $like, $like, $like];
}

if ($statusFilter === 'disponibles') {
    $sql .= " AND l.disponible = TRUE";
} elseif ($statusFilter === 'empruntes') {
    $sql .= " AND l.disponible = FALSE";
}

if ($categoryFilter > 0) {
    $sql .= " AND l.categorie_id = ?";
    $types .= 'i';
    $params[] = $categoryFilter;
}

$sql .= " ORDER BY l.titre ASC";

$stmt = $conn->prepare($sql);
bindStatementParams($stmt, $types, $params);
$stmt->execute();
$books = fetchAllRows($stmt->get_result());
$stmt->close();

renderHeader('Livres', 'livres');
renderPageTitle(
    'Livres',
    'Consultez le catalogue, les catégories et les prêts.',
    [
        ['href' => 'index.php', 'label' => 'Retour à l\'accueil', 'class' => 'btn-outline-secondary'],
        ['href' => 'categories.php', 'label' => 'Gérer les catégories', 'class' => 'btn-outline-secondary'],
        ['href' => 'ajouter_livre.php', 'label' => 'Ajouter un livre', 'class' => 'btn-primary'],
    ]
);
?>

<section class="row g-3 mb-4">
    <?php renderStatCard('Catalogue', (string) ($summary['total'] ?? 0)); ?>
    <?php renderStatCard('Catégories', (string) ($summary['total_categories'] ?? 0)); ?>
    <?php renderStatCard('Disponibles', (string) ($summary['disponibles'] ?? 0)); ?>
    <?php renderStatCard('Empruntés', (string) ($summary['empruntes'] ?? 0)); ?>
</section>

<section class="card simple-card shadow-sm mb-4">
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-lg-5">
                <label for="q" class="form-label">Recherche</label>
                <input type="text" class="form-control" id="q" name="q" value="<?php echo e($search); ?>" placeholder="Titre, auteur, ISBN, éditeur ou catégorie">
            </div>
            <div class="col-lg-3">
                <label for="categorie_id" class="form-label">Catégorie</label>
                <select class="form-select" id="categorie_id" name="categorie_id">
                    <option value="0">Toutes</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo e((string) $category['id']); ?>" <?php echo $categoryFilter === (int) $category['id'] ? 'selected' : ''; ?>>
                            <?php echo e($category['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-2">
                <label for="statut" class="form-label">Statut</label>
                <select class="form-select" id="statut" name="statut">
                    <option value="tous" <?php echo $statusFilter === 'tous' ? 'selected' : ''; ?>>Tous</option>
                    <option value="disponibles" <?php echo $statusFilter === 'disponibles' ? 'selected' : ''; ?>>Disponibles</option>
                    <option value="empruntes" <?php echo $statusFilter === 'empruntes' ? 'selected' : ''; ?>>Empruntés</option>
                </select>
            </div>
            <div class="col-lg-2 d-grid">
                <button type="submit" class="btn btn-primary">Filtrer</button>
            </div>
        </form>
    </div>
</section>

<section class="card simple-card shadow-sm">
    <div class="card-body">
        <?php if ($books === []): ?>
            <div class="empty-state p-4">
                <p class="mb-2">Aucun livre trouvé.</p>
                <a href="ajouter_livre.php" class="btn btn-primary">Ajouter un livre</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Titre</th>
                            <th>Catégorie</th>
                            <th>Auteur</th>
                            <th>ISBN</th>
                            <th>Disponibilité</th>
                            <th>Emprunt en cours</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($books as $book): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?php echo e($book['titre']); ?></div>
                                    <?php if (!empty($book['annee_publication'])): ?>
                                        <div class="small text-secondary"><?php echo e((string) $book['annee_publication']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo e($book['categorie_nom']); ?></td>
                                <td><?php echo e($book['auteur']); ?></td>
                                <td><?php echo $book['isbn'] !== null && $book['isbn'] !== '' ? e($book['isbn']) : '<span class="text-secondary">-</span>'; ?></td>
                                <td><?php echo e((int) $book['disponible'] === 1 ? 'Disponible' : 'Emprunté'); ?></td>
                                <td>
                                    <?php if ($book['emprunteur_actuel']): ?>
                                        <div><?php echo e($book['emprunteur_actuel']); ?></div>
                                        <?php if ($book['date_retour_prevue']): ?>
                                            <div class="small text-secondary">Retour prévu le <?php echo e(formatDate($book['date_retour_prevue'], false)); ?></div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-secondary">Aucun</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex flex-wrap justify-content-end gap-2">
                                        <a href="modifier_livre.php?id=<?php echo e((string) $book['id']); ?>" class="btn btn-sm btn-outline-secondary">Modifier</a>
                                        <?php if ((int) $book['disponible'] === 1): ?>
                                            <a href="emprunts.php?livre_id=<?php echo e((string) $book['id']); ?>" class="btn btn-sm btn-primary">Prêter</a>
                                        <?php else: ?>
                                            <a href="retour_livre.php?id=<?php echo e((string) $book['emprunt_id']); ?>&amp;redirect=livres.php" class="btn btn-sm btn-success" onclick="return confirm('Confirmer le retour de ce livre ?');">Retour</a>
                                        <?php endif; ?>
                                        <a href="supprimer_livre.php?id=<?php echo e((string) $book['id']); ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Supprimer ce livre ?');">Supprimer</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php renderFooter(); ?>
