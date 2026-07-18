-- Base de données SIL Bibliothèque
CREATE DATABASE IF NOT EXISTS bibliotheque_sil CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bibliotheque_sil;

CREATE TABLE IF NOT EXISTS Categorie (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) DEFAULT NULL,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS Livre (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titre VARCHAR(200) NOT NULL,
    auteur VARCHAR(100) NOT NULL,
    categorie_id INT NOT NULL,
    editeur VARCHAR(100),
    annee_publication YEAR,
    isbn VARCHAR(20) UNIQUE,
    nombre_pages INT,
    disponible BOOLEAN DEFAULT TRUE,
    date_ajout TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_livre_categorie FOREIGN KEY (categorie_id) REFERENCES Categorie(id) ON DELETE RESTRICT ON UPDATE CASCADE
);

CREATE TABLE IF NOT EXISTS Emprunteur (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE,
    telephone VARCHAR(20),
    date_inscription TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS Emprunt (
    id INT AUTO_INCREMENT PRIMARY KEY,
    livre_id INT NOT NULL,
    emprunteur_id INT NOT NULL,
    date_emprunt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_retour_prevue DATE DEFAULT NULL,
    date_retour DATETIME DEFAULT NULL,
    statut ENUM('en_cours', 'retourne') NOT NULL DEFAULT 'en_cours',
    notes VARCHAR(255) DEFAULT NULL,
    CONSTRAINT fk_emprunt_livre FOREIGN KEY (livre_id) REFERENCES Livre(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_emprunt_emprunteur FOREIGN KEY (emprunteur_id) REFERENCES Emprunteur(id) ON DELETE RESTRICT ON UPDATE CASCADE
);

-- Données de test
INSERT INTO Categorie (nom, description) VALUES
('InformatiqueRO', 'Livres d informatique et technologies'),
('Programmation', 'Langages et développement logiciel'),
('Base de données', 'Modélisation et systèmes de gestion'),
('Génie logiciel', 'Méthodes et qualité logicielle');

INSERT INTO Livre (titre, auteur, categorie_id, editeur, annee_publication, isbn, nombre_pages) VALUES
('Introduction aux Algorithmes', 'Thomas H. Cormen', 1, 'MIT Press', 2009, '9780262033848', 1292),
('Programmation en Python', 'Jean Dupont', 2, 'Editions Tech', 2020, '9782123456789', 456),
('Base de données relationnelles', 'Marie Martin', 3, 'InfoBooks', 2019, '9783987654321', 623),
('Génie Logiciel', 'Pierre Durand', 4, 'TechPress', 2021, '9784567890123', 789);