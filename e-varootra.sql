-- ============================================
-- BASE DE DONNÉES E-VAROOTRA
-- ============================================

-- Supprimer la base si elle existe
DROP DATABASE IF EXISTS `e-varootra`;
CREATE DATABASE `e-varootra` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `e-varootra`;

-- ============================================
-- TABLE: utilisateurs
-- ============================================
CREATE TABLE `utilisateurs` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `nom_complet` VARCHAR(100) NOT NULL,
  `pseudo` VARCHAR(50) NOT NULL UNIQUE,
  `mot_de_passe` VARCHAR(255) NOT NULL,
  `date_creation` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLE: unites (pièce, paquet, kilo, etc.)
-- ============================================
CREATE TABLE `unites` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `nom` VARCHAR(50) NOT NULL UNIQUE,
  `symbole` VARCHAR(10) NOT NULL,
  `actif` BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insertion des unités par défaut
INSERT INTO `unites` (`nom`, `symbole`) VALUES
('Pièce', 'pcs'),
('Paquet', 'pqt'),
('Kilogramme', 'kg'),
('Carton', 'ctn'),
('Sac', 'sac'),
('Litre', 'L'),
('Gramme', 'g'),
('Douzaine', 'dz');

-- ============================================
-- TABLE: produits
-- Chaque produit a UNE SEULE unité + quantité
-- Ex: "Farine Sac 50kg" et "Farine Paquet 1kg" = 2 produits DIFFÉRENTS
-- ============================================
CREATE TABLE `produits` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `nom` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `quantite_unite` DECIMAL(10,2) NOT NULL COMMENT 'Ex: 50 pour un sac de 50kg',
  `unite_id` INT NOT NULL,
  `prix_unitaire` DECIMAL(10,2) NOT NULL COMMENT 'Prix actuel du produit',
  `stock_disponible` DECIMAL(10,2) DEFAULT 0,
  `seuil_alerte` DECIMAL(10,2) DEFAULT 10 COMMENT 'Alerte stock bas',
  `actif` BOOLEAN DEFAULT TRUE,
  `date_creation` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `date_modification` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`unite_id`) REFERENCES `unites`(`id`),
  INDEX `idx_nom` (`nom`),
  INDEX `idx_actif` (`actif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLE: historique_prix
-- Garde l'historique des changements de prix
-- ============================================
CREATE TABLE `historique_prix` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `produit_id` INT NOT NULL,
  `ancien_prix` DECIMAL(10,2) NOT NULL,
  `nouveau_prix` DECIMAL(10,2) NOT NULL,
  `utilisateur_id` INT NOT NULL,
  `date_modification` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`produit_id`) REFERENCES `produits`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs`(`id`),
  INDEX `idx_produit` (`produit_id`),
  INDEX `idx_date` (`date_modification`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLE: clients
-- ============================================
CREATE TABLE `clients` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `nom_complet` VARCHAR(100) NOT NULL,
  `telephone` VARCHAR(20),
  `adresse` TEXT,
  `actif` BOOLEAN DEFAULT TRUE,
  `date_creation` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_nom` (`nom_complet`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLE: dettes
-- IMPORTANT: Le prix est FIGÉ au moment de la création
-- ============================================
CREATE TABLE `dettes` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `client_id` INT NOT NULL,
  `produit_id` INT NOT NULL,
  `quantite` DECIMAL(10,2) NOT NULL,
  
  -- PRIX FIGÉ (ne change JAMAIS même si le prix du produit change)
  `prix_unitaire_fige` DECIMAL(10,2) NOT NULL COMMENT 'Prix au moment de la dette',
  `montant_total` DECIMAL(10,2) NOT NULL COMMENT 'quantite * prix_unitaire_fige',
  
  -- PAIEMENTS
  `montant_paye` DECIMAL(10,2) DEFAULT 0,
  `montant_restant` DECIMAL(10,2) NOT NULL,
  `statut` ENUM('active', 'payee', 'partiellement_payee') DEFAULT 'active',
  
  -- TRAÇABILITÉ
  `enregistre_par` INT NOT NULL COMMENT 'Utilisateur qui a créé la dette',
  `date_dette` DATE NOT NULL,
  `date_creation` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `date_modification` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`),
  FOREIGN KEY (`produit_id`) REFERENCES `produits`(`id`),
  FOREIGN KEY (`enregistre_par`) REFERENCES `utilisateurs`(`id`),
  INDEX `idx_client` (`client_id`),
  INDEX `idx_statut` (`statut`),
  INDEX `idx_date` (`date_dette`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLE: paiements_dette
-- Historique de tous les paiements
-- ============================================
CREATE TABLE `paiements_dette` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `dette_id` INT NOT NULL,
  `montant_paye` DECIMAL(10,2) NOT NULL,
  `mode_paiement` ENUM('especes', 'mobile_money', 'cheque', 'virement') DEFAULT 'especes',
  `reference_paiement` VARCHAR(100) COMMENT 'Numéro de transaction, chèque, etc.',
  `note` TEXT,
  
  -- TRAÇABILITÉ
  `enregistre_par` INT NOT NULL COMMENT 'Utilisateur qui a enregistré le paiement',
  `date_paiement` DATE NOT NULL,
  `date_creation` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (`dette_id`) REFERENCES `dettes`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`enregistre_par`) REFERENCES `utilisateurs`(`id`),
  INDEX `idx_dette` (`dette_id`),
  INDEX `idx_date` (`date_paiement`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLE: mouvements_stock
-- Historique des entrées/sorties de stock
-- ============================================
CREATE TABLE `mouvements_stock` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `produit_id` INT NOT NULL,
  `type_mouvement` ENUM('entree', 'sortie', 'ajustement') NOT NULL,
  `quantite` DECIMAL(10,2) NOT NULL,
  `stock_avant` DECIMAL(10,2) NOT NULL,
  `stock_apres` DECIMAL(10,2) NOT NULL,
  `motif` VARCHAR(255),
  `utilisateur_id` INT NOT NULL,
  `date_mouvement` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (`produit_id`) REFERENCES `produits`(`id`),
  FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs`(`id`),
  INDEX `idx_produit` (`produit_id`),
  INDEX `idx_date` (`date_mouvement`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- VUES UTILES
-- ============================================

-- Vue: Produits avec leurs unités
CREATE VIEW `vue_produits_complets` AS
SELECT 
  p.id,
  p.nom,
  p.description,
  p.quantite_unite,
  u.nom AS unite_nom,
  u.symbole AS unite_symbole,
  CONCAT(p.nom, ' - ', p.quantite_unite, u.symbole) AS designation_complete,
  p.prix_unitaire,
  p.stock_disponible,
  p.seuil_alerte,
  p.actif,
  p.date_creation
FROM produits p
JOIN unites u ON p.unite_id = u.id;

-- Vue: Dettes avec toutes les infos
CREATE VIEW `vue_dettes_completes` AS
SELECT 
  d.id AS dette_id,
  c.nom_complet AS client_nom,
  c.telephone AS client_telephone,
  p.nom AS produit_nom,
  p.quantite_unite,
  u.symbole AS unite_symbole,
  CONCAT(p.nom, ' - ', p.quantite_unite, u.symbole) AS produit_designation,
  d.quantite,
  d.prix_unitaire_fige,
  d.montant_total,
  d.montant_paye,
  d.montant_restant,
  d.statut,
  usr.nom_complet AS enregistre_par_nom,
  d.date_dette,
  d.date_creation
FROM dettes d
JOIN clients c ON d.client_id = c.id
JOIN produits p ON d.produit_id = p.id
JOIN unites u ON p.unite_id = u.id
JOIN utilisateurs usr ON d.enregistre_par = usr.id;

-- Vue: Statistiques globales
CREATE VIEW `vue_statistiques` AS
SELECT 
  (SELECT COUNT(*) FROM produits WHERE actif = TRUE) AS total_produits,
  (SELECT COUNT(*) FROM dettes WHERE statut IN ('active', 'partiellement_payee')) AS dettes_actives,
  (SELECT COUNT(*) FROM dettes WHERE statut = 'payee') AS dettes_payees,
  (SELECT COALESCE(SUM(montant_restant), 0) FROM dettes WHERE statut IN ('active', 'partiellement_payee')) AS total_dettes_restantes,
  (SELECT COUNT(*) FROM clients WHERE actif = TRUE) AS total_clients;

-- ============================================
-- TRIGGERS
-- ============================================

-- Trigger: Mettre à jour le montant_restant automatiquement
DELIMITER //
CREATE TRIGGER `before_dette_insert` BEFORE INSERT ON `dettes`
FOR EACH ROW
BEGIN
  SET NEW.montant_total = NEW.quantite * NEW.prix_unitaire_fige;
  SET NEW.montant_restant = NEW.montant_total - NEW.montant_paye;
END//

CREATE TRIGGER `before_dette_update` BEFORE UPDATE ON `dettes`
FOR EACH ROW
BEGIN
  SET NEW.montant_restant = NEW.montant_total - NEW.montant_paye;
  
  -- Mettre à jour le statut automatiquement
  IF NEW.montant_restant <= 0 THEN
    SET NEW.statut = 'payee';
  ELSEIF NEW.montant_paye > 0 AND NEW.montant_restant > 0 THEN
    SET NEW.statut = 'partiellement_payee';
  ELSE
    SET NEW.statut = 'active';
  END IF;
END//

-- Trigger: Enregistrer changement de prix dans l'historique
CREATE TRIGGER `after_produit_prix_update` AFTER UPDATE ON `produits`
FOR EACH ROW
BEGIN
  IF OLD.prix_unitaire != NEW.prix_unitaire THEN
    INSERT INTO historique_prix (produit_id, ancien_prix, nouveau_prix, utilisateur_id)
    VALUES (NEW.id, OLD.prix_unitaire, NEW.prix_unitaire, 1); -- À remplacer par l'ID utilisateur connecté
  END IF;
END//

DELIMITER ;


-- Ajouter le numéro de facture unique à la table dettes
ALTER TABLE `dettes` 
ADD COLUMN `numero_facture` VARCHAR(50) UNIQUE NOT NULL AFTER `id`;

-- Fonction pour générer le prochain numéro de facture
DELIMITER //

CREATE TRIGGER `before_dette_facture_insert` BEFORE INSERT ON `dettes`
FOR EACH ROW
BEGIN
  DECLARE next_num INT;
  DECLARE year_part VARCHAR(4);
  DECLARE new_facture VARCHAR(50);
  
  -- Obtenir l'année actuelle
  SET year_part = YEAR(CURDATE());
  
  -- Trouver le dernier numéro de l'année
  SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(numero_facture, '-', -1) AS UNSIGNED)), 0) + 1
  INTO next_num
  FROM dettes
  WHERE numero_facture LIKE CONCAT('DET-', year_part, '-%');
  
  -- Générer le nouveau numéro avec zéros
  SET new_facture = CONCAT('DET-', year_part, '-', LPAD(next_num, 4, '0'));
  SET NEW.numero_facture = new_facture;
END//

DELIMITER ;

-- Mettre à jour les dettes existantes avec des numéros de facture
SET @counter = 0;
UPDATE dettes 
SET numero_facture = CONCAT('DET-', YEAR(date_dette), '-', LPAD(@counter := @counter + 1, 4, '0'))
ORDER BY id;


-- ============================================
-- CORRECTION: Numéro de facture unique par dette
-- Version sécurisée qui gère tous les cas
-- ============================================

-- 1. Supprimer le trigger problématique s'il existe
DROP TRIGGER IF EXISTS `before_dette_facture_insert`;

-- 2. Vérifier et supprimer les index existants
-- Cette approche fonctionne même si l'index n'existe pas

-- Supprimer l'index unique s'il existe
SET @exist := (SELECT COUNT(*) FROM information_schema.statistics 
               WHERE table_schema = DATABASE() 
               AND table_name = 'dettes' 
               AND index_name = 'numero_facture');
SET @sqlstmt := IF(@exist > 0, 
                   'ALTER TABLE `dettes` DROP INDEX `numero_facture`', 
                   'SELECT ''Index numero_facture n existe pas''');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Supprimer l'index idx_numero_facture s'il existe
SET @exist := (SELECT COUNT(*) FROM information_schema.statistics 
               WHERE table_schema = DATABASE() 
               AND table_name = 'dettes' 
               AND index_name = 'idx_numero_facture');
SET @sqlstmt := IF(@exist > 0, 
                   'ALTER TABLE `dettes` DROP INDEX `idx_numero_facture`', 
                   'SELECT ''Index idx_numero_facture n existe pas''');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. Ajouter un index non-unique
ALTER TABLE `dettes` 
ADD INDEX `idx_numero_facture` (`numero_facture`);

-- 4. Rendre la colonne nullable (pour éviter les erreurs si elle était NOT NULL)
ALTER TABLE `dettes` 
MODIFY `numero_facture` VARCHAR(50) NULL;

-- 5. Supprimer la fonction si elle existe déjà
DROP FUNCTION IF EXISTS `generer_numero_facture`;

-- 6. Créer la fonction pour générer le prochain numéro
DELIMITER //

CREATE FUNCTION `generer_numero_facture`() 
RETURNS VARCHAR(50) CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci
DETERMINISTIC
BEGIN
  DECLARE next_num INT;
  DECLARE year_part VARCHAR(4);
  DECLARE new_facture VARCHAR(50);
  
  SET year_part = YEAR(CURDATE());
  
  SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(numero_facture, '-', -1) AS UNSIGNED)), 0) + 1
  INTO next_num
  FROM dettes
  WHERE numero_facture LIKE CONCAT('FAC-', year_part, '-%') COLLATE utf8mb4_unicode_ci;
  
  SET new_facture = CONCAT('FAC-', year_part, '-', LPAD(next_num, 3, '0'));
  
  RETURN new_facture;
END//

DELIMITER ;

-- 7. Vérification: Tester la fonction
SELECT generer_numero_facture() AS prochain_numero;

-- 8. Afficher les index actuels de la table dettes pour vérification
SHOW INDEX FROM `dettes` WHERE Key_name LIKE '%numero_facture%';
-- ============================================
-- DONNÉES DE TEST
-- ============================================

-- Ajouter un utilisateur de test
INSERT INTO utilisateurs (nom_complet, pseudo, mot_de_passe) VALUES
('Administrateur', 'admin', 'admin123'),
('Jean Rakoto', 'jean', 'jean123');

-- Ajouter des produits exemples
INSERT INTO produits (nom, description, quantite_unite, unite_id, prix_unitaire, stock_disponible) VALUES
('Farine', 'Farine de blé', 50, 5, 45000, 100),  -- Sac de 50kg
('Farine', 'Farine de blé petit format', 1, 2, 2500, 200),  -- Paquet de 1kg
('Riz', 'Riz blanc premium', 25, 5, 35000, 80),  -- Sac de 25kg
('Sucre', 'Sucre cristallisé', 1, 3, 4500, 150),  -- Kilo
('Huile', 'Huile végétale', 1, 6, 8000, 60),  -- Litre
('Savon', 'Savon de toilette', 1, 1, 1500, 300);  -- Pièce

-- Ajouter des clients
INSERT INTO clients (nom_complet, telephone, adresse) VALUES
('Rakoto Jean', '0340000001', 'Antananarivo'),
('Rasoa Marie', '0340000002', 'Antsirabe'),
('Rabe Paul', '0340000003', 'Fianarantsoa');