<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

$conn = getConnection();
$categories = getCategories($conn);
$currentYear = (int) date('Y');
$formData = [
    'titre' => '',
    'auteur' => '',
    'categorie_id' => $categories[0]['id'] ?? '',
    'editeur' => '',
    'annee_publication' => '',
    'isbn' => '',
    'nombre_pages' => '',
];
$errors = [];
$hasCategories = $categories !== [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($formData as $key => $value) {
        $formData[$key] = cleanInput($_POST[$key] ?? '');
    }

    if ($formData['titre'] === '') {
        $errors[] = 'Le titre est obligatoire.';
    }

    if ($formData['auteur'] === '') {
        $errors[] = "L'auteur est obligatoire.";
    }

    if ($formData['categorie_id'] === '' || !ctype_digit($formData['categorie_id'])) {
        $errors[] = 'La catégorie est obligatoire.';
    } else {
        $categoryId = (int) $formData['categorie_id'];
        $categoryStmt = $conn->prepare('SELECT id FROM Categorie WHERE id = ?');
        $categoryStmt->bind_param('i', $categoryId);
        $categoryStmt->execute();
        $categoryExists = $categoryStmt->get_result()->fetch_assoc();
        $categoryStmt->close();

        if (!$categoryExists) {
            $errors[] = 'La catégorie sélectionnée est invalide.';
        }
    }

    if ($formData['annee_publication'] !== '' && (!ctype_digit($formData['annee_publication']) || (int) $formData['annee_publication'] < 1000 || (int) $formData['annee_publication'] > $currentYear)) {
        $errors[] = "L'année de publication est invalide.";
    }

    if ($formData['nombre_pages'] !== '' && (!ctype_digit($formData['nombre_pages']) || (int) $formData['nombre_pages'] < 1)) {
        $errors[] = 'Le nombre de pages doit être un entier positif.';
    }

    if ($formData['isbn'] !== '') {
        $checkStmt = $conn->prepare('SELECT id FROM Livre WHERE isbn = ?');
        $checkStmt->bind_param('s', $formData['isbn']);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) {
            $errors[] = 'Un livre avec cet ISBN existe déjà.';
        }
        $checkStmt->close();
    }

    if (!$hasCategories) {
        $errors[] = 'Ajoutez d\'abord une categorie avant d\'enregistrer un livre.';
    }

    if ($errors === []) {
        $categoryId = (int) $formData['categorie_id'];
        $stmt = $conn->prepare(
            "INSERT INTO Livre (titre, auteur, categorie_id, editeur, annee_publication, isbn, nombre_pages)
            VALUES (?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''))"
        );
        $stmt->bind_param(
            'ssissss',
            $formData['titre'],
            $formData['auteur'],
            $categoryId,
            $formData['editeur'],
            $formData['annee_publication'],
            $formData['isbn'],
            $formData['nombre_pages']
        );
        $stmt->execute();
        $stmt->close();

        setFlash('success', 'Le livre a été ajouté avec succès.');
        redirect('livres.php');
    }
}

renderHeader('Ajouter un livre', 'livres');
renderPageTitle(
    'Ajouter un livre',
    'Ajoutez un ouvrage au catalogue avec sa catégorie.',
    [
        ['href' => 'categories.php', 'label' => 'Gérer les catégories', 'class' => 'btn-outline-secondary'],
        ['href' => 'livres.php', 'label' => 'Retour aux livres', 'class' => 'btn-outline-secondary'],
    ]
);
?>

<section class="card simple-card shadow-sm">
    <div class="card-body">
        <?php if (!$hasCategories): ?>
            <div class="alert alert-warning">
                Aucune categorie n'est disponible. Creez une categorie avant d'ajouter un livre.
            </div>
        <?php endif; ?>

        <?php if ($errors !== []): ?>
            <div class="alert alert-danger">
                <ul class="mb-0 mt-2">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo e($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" class="row g-3">
            <div class="col-md-6">
                <label for="titre" class="form-label">Titre *</label>
                <input type="text" class="form-control" id="titre" name="titre" value="<?php echo e($formData['titre']); ?>" required>
            </div>
            <div class="col-md-6">
                <label for="auteur" class="form-label">Auteur *</label>
                <input type="text" class="form-control" id="auteur" name="auteur" value="<?php echo e($formData['auteur']); ?>" required>
            </div>
            <div class="col-md-6">
                <label for="categorie_id" class="form-label">Catégorie *</label>
                <select class="form-select" id="categorie_id" name="categorie_id" required>
                    <option value="">-- Sélectionner une catégorie --</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo e((string) $category['id']); ?>" <?php echo $formData['categorie_id'] === (string) $category['id'] ? 'selected' : ''; ?>>
                            <?php echo e($category['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label for="editeur" class="form-label">Éditeur</label>
                <input type="text" class="form-control" id="editeur" name="editeur" value="<?php echo e($formData['editeur']); ?>">
            </div>
            <div class="col-md-6">
                <label for="annee_publication" class="form-label">Année</label>
                <input type="number" class="form-control" id="annee_publication" name="annee_publication" min="1000" max="<?php echo e((string) $currentYear); ?>" value="<?php echo e($formData['annee_publication']); ?>">
            </div>
            <div class="col-md-6">
                <label for="nombre_pages" class="form-label">Pages</label>
                <input type="number" class="form-control" id="nombre_pages" name="nombre_pages" min="1" value="<?php echo e($formData['nombre_pages']); ?>">
            </div>
            <div class="col-12">
                <label for="isbn" class="form-label">ISBN</label>
                <input type="text" class="form-control" id="isbn" name="isbn" value="<?php echo e($formData['isbn']); ?>" placeholder="9780262033848">
            </div>
            <div class="col-12 d-flex flex-wrap justify-content-end gap-2">
                <a href="livres.php" class="btn btn-outline-secondary">Annuler</a>
                <button type="submit" class="btn btn-primary" <?php echo $hasCategories ? '' : 'disabled'; ?>>Enregistrer</button>
            </div>
        </form>
    </div>
</section>

<?php renderFooter(); ?>
