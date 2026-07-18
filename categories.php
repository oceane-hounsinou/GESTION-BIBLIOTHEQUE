<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

$conn = getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = cleanInput($_POST['action'] ?? '');
    $categoryId = (int) ($_POST['categorie_id'] ?? 0);
    $name = cleanInput($_POST['nom'] ?? '');
    $description = cleanInput($_POST['description'] ?? '');

    if (in_array($action, ['add', 'update'], true)) {
        if ($name === '') {
            setFlash('danger', 'Le nom de la catégorie est obligatoire.');
            redirect('categories.php');
        }

        if (mb_strlen($name) > 100) {
            setFlash('danger', 'Le nom de la catégorie est trop long.');
            redirect('categories.php');
        }

        if (mb_strlen($description) > 255) {
            setFlash('danger', 'La description est trop longue.');
            redirect('categories.php');
        }
    }

    if ($action === 'add') {
        $checkStmt = $conn->prepare('SELECT id FROM Categorie WHERE nom = ?');
        $checkStmt->bind_param('s', $name);
        $checkStmt->execute();
        $exists = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        if ($exists) {
            setFlash('warning', 'Cette catégorie existe déjà.');
            redirect('categories.php');
        }

        $insertStmt = $conn->prepare(
            "INSERT INTO Categorie (nom, description)
            VALUES (?, NULLIF(?, ''))"
        );
        $insertStmt->bind_param('ss', $name, $description);
        $insertStmt->execute();
        $insertStmt->close();

        setFlash('success', 'La catégorie a été ajoutée.');
        redirect('categories.php');
    }

    if ($action === 'update') {
        if ($categoryId <= 0) {
            setFlash('danger', 'Catégorie introuvable.');
            redirect('categories.php');
        }

        $checkStmt = $conn->prepare('SELECT id FROM Categorie WHERE nom = ? AND id <> ?');
        $checkStmt->bind_param('si', $name, $categoryId);
        $checkStmt->execute();
        $exists = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        if ($exists) {
            setFlash('warning', 'Une autre catégorie utilise déjà ce nom.');
            redirect('categories.php');
        }

        $updateStmt = $conn->prepare(
            "UPDATE Categorie
            SET nom = ?, description = NULLIF(?, '')
            WHERE id = ?"
        );
        $updateStmt->bind_param('ssi', $name, $description, $categoryId);
        $updateStmt->execute();
        $updateStmt->close();

        setFlash('success', 'La catégorie a été mise à jour.');
        redirect('categories.php');
    }

    if ($action === 'delete') {
        if ($categoryId <= 0) {
            setFlash('danger', 'Catégorie introuvable.');
            redirect('categories.php');
        }

        $countResult = $conn->query('SELECT COUNT(*) AS total FROM Categorie')->fetch_assoc();
        if ((int) ($countResult['total'] ?? 0) <= 1) {
            setFlash('warning', 'Impossible de supprimer la dernière catégorie.');
            redirect('categories.php');
        }

        $usageStmt = $conn->prepare('SELECT COUNT(*) AS total FROM Livre WHERE categorie_id = ?');
        $usageStmt->bind_param('i', $categoryId);
        $usageStmt->execute();
        $usageCount = (int) $usageStmt->get_result()->fetch_assoc()['total'];
        $usageStmt->close();

        if ($usageCount > 0) {
            setFlash('warning', 'Cette catégorie est utilisée par des livres et ne peut pas être supprimée.');
            redirect('categories.php');
        }

        $deleteStmt = $conn->prepare('DELETE FROM Categorie WHERE id = ?');
        $deleteStmt->bind_param('i', $categoryId);
        $deleteStmt->execute();
        $deleteStmt->close();

        setFlash('success', 'La catégorie a été supprimée.');
        redirect('categories.php');
    }
}

$categories = fetchAllRows(
    $conn->query(
        "SELECT c.id, c.nom, c.description, COUNT(l.id) AS total_livres
        FROM Categorie AS c
        LEFT JOIN Livre AS l ON l.categorie_id = c.id
        GROUP BY c.id, c.nom, c.description
        ORDER BY c.nom ASC"
    )
);

$editCategoryId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editCategory = null;
foreach ($categories as $category) {
    if ((int) $category['id'] === $editCategoryId) {
        $editCategory = $category;
        break;
    }
}

$usedCategories = 0;
foreach ($categories as $category) {
    if ((int) $category['total_livres'] > 0) {
        $usedCategories++;
    }
}

$formAction = $editCategory ? 'update' : 'add';
$formTitle = $editCategory ? 'Modifier une catégorie' : 'Ajouter une catégorie';
$formButton = $editCategory ? 'Mettre à jour' : 'Ajouter';
$formValues = [
    'id' => $editCategory['id'] ?? '',
    'nom' => $editCategory['nom'] ?? '',
    'description' => $editCategory['description'] ?? '',
];

renderHeader('Catégories', 'categories');
renderPageTitle(
    'Catégories',
    'Gerez les categories utilisees lors de l\'ajout des livres.',
    [
        ['href' => 'livres.php', 'label' => 'Retour aux livres', 'class' => 'btn-outline-secondary'],
    ]
);
?>

<section class="row g-3 mb-4">
    <?php renderStatCard('Total catégories', (string) count($categories)); ?>
    <?php renderStatCard('Catégories utilisées', (string) $usedCategories); ?>
</section>

<section class="card simple-card shadow-sm mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h5 mb-0"><?php echo e($formTitle); ?></h2>
            <?php if ($editCategory): ?>
                <a href="categories.php" class="btn btn-sm btn-outline-secondary">Annuler la modification</a>
            <?php endif; ?>
        </div>

        <form method="post" class="row g-3">
            <input type="hidden" name="action" value="<?php echo e($formAction); ?>">
            <input type="hidden" name="categorie_id" value="<?php echo e((string) $formValues['id']); ?>">
            <div class="col-md-5">
                <label for="nom" class="form-label">Nom</label>
                <input type="text" class="form-control" id="nom" name="nom" maxlength="100" value="<?php echo e($formValues['nom']); ?>" required>
            </div>
            <div class="col-md-5">
                <label for="description" class="form-label">Description</label>
                <input type="text" class="form-control" id="description" name="description" maxlength="255" value="<?php echo e($formValues['description']); ?>">
            </div>
            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-primary"><?php echo e($formButton); ?></button>
            </div>
        </form>
    </div>
</section>

<section class="card simple-card shadow-sm">
    <div class="card-body">
        <h2 class="h5 mb-3">Catégories existantes</h2>

        <?php if ($categories === []): ?>
            <p class="mb-0 text-secondary">Aucune catégorie disponible.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Description</th>
                            <th>Livres liés</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $category): ?>
                            <tr>
                                <td><?php echo e($category['nom']); ?></td>
                                <td><?php echo $category['description'] !== null && $category['description'] !== '' ? e($category['description']) : '<span class="text-secondary">-</span>'; ?></td>
                                <td><?php echo e((string) $category['total_livres']); ?></td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-2">
                                        <a href="categories.php?edit=<?php echo e((string) $category['id']); ?>" class="btn btn-sm btn-outline-secondary">Modifier</a>
                                        <form method="post" onsubmit="return confirm('Supprimer cette catégorie ?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="categorie_id" value="<?php echo e((string) $category['id']); ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Supprimer</button>
                                        </form>
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
