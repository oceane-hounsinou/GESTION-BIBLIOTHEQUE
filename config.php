<?php
declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Use a local writable session directory so flash messages work reliably in this environment.
$sessionPath = '/tmp/bibliotheque_sessions';
if (!is_dir($sessionPath)) {
    @mkdir($sessionPath, 0777, true);
}

if (is_dir($sessionPath) && is_writable($sessionPath)) {
    session_save_path($sessionPath);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'bibliotheque_sil');

function getConnection(): mysqli
{
    static $conn = null;

    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        $conn->set_charset('utf8mb4');
        ensureSchema($conn);
        synchronizeBookAvailability($conn);
    }

    return $conn;
}

function ensureSchema(mysqli $conn): void
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS Categorie (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(100) NOT NULL UNIQUE,
            description VARCHAR(255) DEFAULT NULL,
            date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    seedDefaultCategories($conn);

    if (tableExists($conn, 'Livre')) {
        if (!columnExists($conn, 'Livre', 'categorie_id')) {
            $conn->query("ALTER TABLE Livre ADD COLUMN categorie_id INT NULL AFTER auteur");
        }

        $defaultCategoryId = ensureDefaultCategory($conn);
        $updateStmt = $conn->prepare(
            "UPDATE Livre AS l
            LEFT JOIN Categorie AS c ON c.id = l.categorie_id
            SET l.categorie_id = ?
            WHERE l.categorie_id IS NULL OR c.id IS NULL"
        );
        $updateStmt->bind_param('i', $defaultCategoryId);
        $updateStmt->execute();
        $updateStmt->close();

        if (columnAllowsNull($conn, 'Livre', 'categorie_id')) {
            $conn->query("ALTER TABLE Livre MODIFY categorie_id INT NOT NULL");
        }

        if (!indexExists($conn, 'Livre', 'idx_livre_categorie')) {
            $conn->query("ALTER TABLE Livre ADD INDEX idx_livre_categorie (categorie_id)");
        }

        if (!foreignKeyExists($conn, 'Livre', 'fk_livre_categorie')) {
            $conn->query(
                "ALTER TABLE Livre
                ADD CONSTRAINT fk_livre_categorie
                FOREIGN KEY (categorie_id) REFERENCES Categorie(id)
                ON DELETE RESTRICT
                ON UPDATE CASCADE"
            );
        }
    }

    if (tableExists($conn, 'Livre') && tableExists($conn, 'Emprunteur')) {
        $conn->query(
            "CREATE TABLE IF NOT EXISTS Emprunt (
                id INT AUTO_INCREMENT PRIMARY KEY,
                livre_id INT NOT NULL,
                emprunteur_id INT NOT NULL,
                date_emprunt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                date_retour_prevue DATE DEFAULT NULL,
                date_retour DATETIME DEFAULT NULL,
                statut ENUM('en_cours', 'retourne') NOT NULL DEFAULT 'en_cours',
                notes VARCHAR(255) DEFAULT NULL,
                CONSTRAINT fk_emprunt_livre FOREIGN KEY (livre_id) REFERENCES Livre(id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT fk_emprunt_emprunteur FOREIGN KEY (emprunteur_id) REFERENCES Emprunteur(id) ON DELETE RESTRICT ON UPDATE CASCADE,
                INDEX idx_emprunt_statut (statut),
                INDEX idx_emprunt_livre_statut (livre_id, statut),
                INDEX idx_emprunt_emprunteur_statut (emprunteur_id, statut)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
}

function synchronizeBookAvailability(mysqli $conn): void
{
    if (!tableExists($conn, 'Livre') || !tableExists($conn, 'Emprunt')) {
        return;
    }

    $conn->query(
        "UPDATE Livre AS l
        LEFT JOIN (
            SELECT livre_id, COUNT(*) AS active_loans
            FROM Emprunt
            WHERE statut = 'en_cours' AND date_retour IS NULL
            GROUP BY livre_id
        ) AS e ON e.livre_id = l.id
        SET l.disponible = IF(COALESCE(e.active_loans, 0) > 0, 0, 1)"
    );
}

function tableExists(mysqli $conn, string $table): bool
{
    $schema = DB_NAME;
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS total
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?"
    );
    $stmt->bind_param('ss', $schema, $table);
    $stmt->execute();
    $total = (int) $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    return $total > 0;
}

function columnExists(mysqli $conn, string $table, string $column): bool
{
    $schema = DB_NAME;
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS total
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $stmt->bind_param('sss', $schema, $table, $column);
    $stmt->execute();
    $total = (int) $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    return $total > 0;
}

function columnAllowsNull(mysqli $conn, string $table, string $column): bool
{
    $schema = DB_NAME;
    $stmt = $conn->prepare(
        "SELECT IS_NULLABLE
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $stmt->bind_param('sss', $schema, $table, $column);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return ($result['IS_NULLABLE'] ?? 'YES') === 'YES';
}

function indexExists(mysqli $conn, string $table, string $index): bool
{
    $schema = DB_NAME;
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS total
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?"
    );
    $stmt->bind_param('sss', $schema, $table, $index);
    $stmt->execute();
    $total = (int) $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    return $total > 0;
}

function foreignKeyExists(mysqli $conn, string $table, string $constraint): bool
{
    $schema = DB_NAME;
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS total
        FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY'"
    );
    $stmt->bind_param('sss', $schema, $table, $constraint);
    $stmt->execute();
    $total = (int) $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    return $total > 0;
}

function seedDefaultCategories(mysqli $conn): void
{
    $total = (int) $conn->query("SELECT COUNT(*) AS total FROM Categorie")->fetch_assoc()['total'];
    if ($total > 0) {
        return;
    }

    $conn->query(
        "INSERT INTO Categorie (nom, description) VALUES
            ('Non classé', 'Catégorie par défaut'),
            ('Informatique', 'Livres d''informatique et technologies'),
            ('Programmation', 'Langages et développement logiciel'),
            ('Base de données', 'Modélisation et systèmes de gestion'),
            ('Génie logiciel', 'Méthodes, architecture et qualité logicielle')"
    );
}

function ensureDefaultCategory(mysqli $conn): int
{
    $defaultName = 'Non classé';
    $defaultDescription = 'Catégorie par défaut';

    $insertStmt = $conn->prepare(
        "INSERT INTO Categorie (nom, description)
        SELECT ?, ?
        FROM DUAL
        WHERE NOT EXISTS (SELECT 1 FROM Categorie WHERE nom = ?)"
    );
    $insertStmt->bind_param('sss', $defaultName, $defaultDescription, $defaultName);
    $insertStmt->execute();
    $insertStmt->close();

    $selectStmt = $conn->prepare('SELECT id FROM Categorie WHERE nom = ?');
    $selectStmt->bind_param('s', $defaultName);
    $selectStmt->execute();
    $category = $selectStmt->get_result()->fetch_assoc();
    $selectStmt->close();

    return (int) ($category['id'] ?? 0);
}

function getCategories(mysqli $conn): array
{
    return fetchAllRows(
        $conn->query(
            "SELECT id, nom, description
            FROM Categorie
            ORDER BY nom ASC"
        )
    );
}

function cleanInput(?string $input): string
{
    return trim((string) $input);
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function formatDate(?string $date, bool $withTime = true): string
{
    if (empty($date)) {
        return '-';
    }

    $format = $withTime ? 'd/m/Y H:i' : 'd/m/Y';
    return date($format, strtotime($date));
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function setFlash(string $type, string $message): void
{
    $_SESSION['flash_messages'][] = [
        'type' => $type,
        'message' => $message,
    ];
}

function getFlashes(): array
{
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}

function bindStatementParams(mysqli_stmt $stmt, string $types, array $params): void
{
    if ($types === '' || $params === []) {
        return;
    }

    $stmt->bind_param($types, ...$params);
}

function fetchAllRows(mysqli_result $result): array
{
    $rows = [];

    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    return $rows;
}

function getDashboardStats(mysqli $conn): array
{
    $result = $conn->query(
        "SELECT
            (SELECT COUNT(*) FROM Livre) AS total_livres,
            (SELECT COUNT(*) FROM Categorie) AS total_categories,
            (SELECT COUNT(*) FROM Livre WHERE disponible = TRUE) AS livres_disponibles,
            (SELECT COUNT(*) FROM Emprunteur) AS total_emprunteurs,
            (SELECT COUNT(*) FROM Emprunt WHERE statut = 'en_cours') AS emprunts_en_cours,
            (SELECT COUNT(*) FROM Emprunt WHERE statut = 'retourne') AS emprunts_retournes,
            (SELECT COUNT(*) FROM Emprunt WHERE statut = 'en_cours' AND date_retour_prevue IS NOT NULL AND date_retour_prevue < CURDATE()) AS emprunts_en_retard"
    );

    return $result->fetch_assoc() ?: [];
}

function mapFlashToBootstrap(string $type): string
{
    return match ($type) {
        'success' => 'success',
        'warning' => 'warning',
        'danger', 'error' => 'danger',
        default => 'info',
    };
}
?>
