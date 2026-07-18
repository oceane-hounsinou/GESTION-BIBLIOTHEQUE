<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

$conn = getConnection();
$categories = getCategories($conn);
$bookId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($bookId <= 0) {
    setFlash('danger', 'Livre introuvable.');
    redirect('livres.php');
}

$stmt = $conn->prepare('SELECT * FROM Livre WHERE id = ?');
$stmt->bind_param('i', $bookId);
$stmt->execute();
$book = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$book) {
    setFlash('danger', 'Livre introuvable.');
    redirect('livres.php');
}

$currentYear = (int) date('Y');
$formData = [
    'titre' => $book['titre'],
    'auteur' => $book['auteur'],
    'categorie_id' => (string) $book['categorie_id'],
    'editeur' => $book['editeur'] ?? '',
    'annee_publication' => $book['annee_publication'] ?? '',
    'isbn' => $book['isbn'] ?? '',
    'nombre_pages' => $book['nombre_pages'] ?? '',
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
        $checkStmt = $conn->prepare('SELECT id FROM Livre WHERE isbn = ? AND id <> ?');
        $checkStmt->bind_param('si', $formData['isbn'], $bookId);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) {
            $errors[] = 'Un autre livre utilise déjà cet ISBN.';
        }
        $checkStmt->close();
    }

    if (!$hasCategories) {
        $errors[] = 'Ajoutez d\'abord une categorie avant de modifier un livre.';
    }

    if ($errors === []) {
        $categoryId = (int) $formData['categorie_id'];
        $updateStmt = $conn->prepare(
            "UPDATE Livre
            SET titre = ?, auteur = ?, categorie_id = ?, editeur = NULLIF(?, ''), annee_publication = NULLIF(?, ''), isbn = NULLIF(?, ''), nombre_pages = NULLIF(?, '')
            WHERE id = ?"
        );
        $updateStmt->bind_param(
            'ssissssi',
            $formData['titre'],
            $formData['auteur'],
            $categoryId,
            $formData['editeur'],
            $formData['annee_publication'],
            $formData['isbn'],
            $formData['nombre_pages'],
            $bookId
        );
        $updateStmt->execute();
        $updateStmt->close();

        setFlash('success', 'Le livre a été mis à jour.');
        redirect('livres.php');
    }
}

renderHeader('Modifier un livre', 'livres');
renderPageTitle(
    'Modifier un livre',
    'Mettez à jour le livre et sa catégorie.',
    [
        ['href' => 'categories.php', 'label' => 'Gérer les catégories', 'class' => 'btn-outline-secondary'],
        ['href' => 'livres.php', 'label' => 'Retour aux livres', 'class' => 'btn-outline-secondary'],
    ]
);
?>

<section class="card simple-card shadow-sm">
    <div class="card-body">
        <p class="text-secondary mb-4">Statut actuel : <?php echo e((int) $book['disponible'] === 1 ? 'disponible' : 'emprunté'); ?></p>

        <?php if (!$hasCategories): ?>
            <div class="alert alert-warning">
                Aucune categorie n'est disponible. Creez une categorie avant de modifier ce livre.
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
                <input type="number" class="form-control" id="annee_publication" name="annee_publication" min="1000" max="<?php echo e((string) $currentYear); ?>" value="<?php echo e((string) $formData['annee_publication']); ?>">
            </div>
            <div class="col-md-6">
                <label for="nombre_pages" class="form-label">Pages</label>
                <input type="number" class="form-control" id="nombre_pages" name="nombre_pages" min="1" value="<?php echo e((string) $formData['nombre_pages']); ?>">
            </div>
            <div class="col-12">
                <label for="isbn" class="form-label">ISBN</label>
                <input type="text" class="form-control" id="isbn" name="isbn" value="<?php echo e($formData['isbn']); ?>">
            </div>
            <div class="col-12 d-flex flex-wrap justify-content-end gap-2">
                <a href="livres.php" class="btn btn-outline-secondary">Annuler</a>
                <button type="submit" class="btn btn-primary" <?php echo $hasCategories ? '' : 'disabled'; ?>>Mettre à jour</button>
            </div>
        </form>
    </div>
</section>

<?php renderFooter(); ?>
