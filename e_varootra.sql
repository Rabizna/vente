-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : dim. 11 jan. 2026 à 15:01
-- Version du serveur : 8.4.7
-- Version de PHP : 8.3.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `e_varootra`
--

DELIMITER $$
--
-- Fonctions
--
DROP FUNCTION IF EXISTS `generer_numero_facture`$$
CREATE DEFINER=`root`@`localhost` FUNCTION `generer_numero_facture` () RETURNS VARCHAR(50) CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci DETERMINISTIC BEGIN
  DECLARE next_num INT;
  DECLARE year_part VARCHAR(4);
  DECLARE new_facture VARCHAR(50);

  SET year_part = YEAR(CURDATE());

  SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(numero_facture, '-', -1) AS UNSIGNED)), 0) + 1
  INTO next_num
  FROM dettes
  WHERE numero_facture LIKE CONCAT('FAC-', year_part, '-%');

  SET new_facture = CONCAT('FAC-', year_part, '-', LPAD(next_num, 3, '0'));

  RETURN new_facture;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Structure de la table `clients`
--

DROP TABLE IF EXISTS `clients`;
CREATE TABLE IF NOT EXISTS `clients` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom_complet` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telephone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adresse` text COLLATE utf8mb4_unicode_ci,
  `actif` tinyint(1) DEFAULT '1',
  `date_creation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `clients`
--

INSERT INTO `clients` (`id`, `nom_complet`, `telephone`, `adresse`, `actif`, `date_creation`) VALUES
(1, 'Madame Tahina', '0347135473', 'Sahavalo', 1, '2026-01-11 13:10:27'),
(2, 'Mamazety', '0347135473', 'Ambodivohitra', 1, '2026-01-11 13:13:31'),
(3, 'Maman\'i Jeremia', '0345677493', 'Sahavalo\r\n', 0, '2026-01-11 13:32:44');

-- --------------------------------------------------------

--
-- Structure de la table `dettes`
--

DROP TABLE IF EXISTS `dettes`;
CREATE TABLE IF NOT EXISTS `dettes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `numero_facture` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `client_id` int NOT NULL,
  `produit_unite_id` int NOT NULL COMMENT 'Référence vers produits_unites',
  `quantite` decimal(10,2) NOT NULL,
  `prix_unitaire_fige` decimal(10,2) NOT NULL,
  `montant_total` decimal(10,2) NOT NULL,
  `montant_paye` decimal(10,2) DEFAULT '0.00',
  `montant_restant` decimal(10,2) NOT NULL,
  `statut` enum('active','payee','partiellement_payee') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `enregistre_par` int NOT NULL,
  `date_dette` date NOT NULL,
  `date_creation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  KEY `produit_unite_id` (`produit_unite_id`),
  KEY `enregistre_par` (`enregistre_par`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `dettes`
--

INSERT INTO `dettes` (`id`, `numero_facture`, `client_id`, `produit_unite_id`, `quantite`, `prix_unitaire_fige`, `montant_total`, `montant_paye`, `montant_restant`, `statut`, `enregistre_par`, `date_dette`, `date_creation`, `date_modification`) VALUES
(1, 'FAC-2026-001', 1, 1, 1.00, 25000.00, 25000.00, 25000.00, 0.00, 'payee', 3, '2026-01-11', '2026-01-11 13:11:27', '2026-01-11 13:11:57'),
(2, 'FAC-2026-001', 1, 9, 1.00, 45000.00, 45000.00, 45000.00, 0.00, 'payee', 3, '2026-01-11', '2026-01-11 13:11:27', '2026-01-11 13:12:39'),
(3, 'FAC-2026-001', 1, 6, 1.00, 50000.00, 50000.00, 50000.00, 0.00, 'payee', 3, '2026-01-11', '2026-01-11 13:11:27', '2026-01-11 13:12:39'),
(4, 'FAC-2026-002', 2, 3, 10.00, 500.00, 5000.00, 5000.00, 0.00, 'payee', 3, '2026-01-11', '2026-01-11 13:14:05', '2026-01-11 13:14:31'),
(5, 'FAC-2026-002', 2, 4, 3.00, 2500.00, 7500.00, 7500.00, 0.00, 'payee', 3, '2026-01-11', '2026-01-11 13:14:05', '2026-01-11 13:20:28'),
(6, 'FAC-2026-003', 2, 3, 10.00, 500.00, 5000.00, 5000.00, 0.00, 'payee', 4, '2026-01-11', '2026-01-11 13:21:08', '2026-01-11 13:22:08'),
(7, 'FAC-2026-003', 2, 4, 3.00, 2500.00, 7500.00, 7500.00, 0.00, 'payee', 4, '2026-01-11', '2026-01-11 13:21:08', '2026-01-11 13:32:15'),
(8, 'FAC-2026-004', 3, 12, 3.00, 5000.00, 15000.00, 15000.00, 0.00, 'payee', 3, '2026-01-11', '2026-01-11 13:33:58', '2026-01-11 13:35:06'),
(9, 'FAC-2026-004', 3, 10, 5.00, 2500.00, 12500.00, 12500.00, 0.00, 'payee', 3, '2026-01-11', '2026-01-11 13:33:58', '2026-01-11 13:35:06'),
(10, 'FAC-2026-004', 3, 3, 2.00, 500.00, 1000.00, 1000.00, 0.00, 'payee', 3, '2026-01-11', '2026-01-11 13:33:58', '2026-01-11 13:35:06'),
(11, 'FAC-2026-005', 2, 11, 12.00, 35000.00, 420000.00, 50000.00, 370000.00, 'partiellement_payee', 4, '2026-01-11', '2026-01-11 14:17:11', '2026-01-11 14:25:57'),
(12, 'FAC-2026-005', 2, 2, 1.00, 8000.00, 8000.00, 0.00, 8000.00, 'active', 4, '2026-01-11', '2026-01-11 14:17:11', '2026-01-11 14:17:11');

--
-- Déclencheurs `dettes`
--
DROP TRIGGER IF EXISTS `before_dette_insert`;
DELIMITER $$
CREATE TRIGGER `before_dette_insert` BEFORE INSERT ON `dettes` FOR EACH ROW BEGIN
  SET NEW.montant_total = NEW.quantite * NEW.prix_unitaire_fige;
  SET NEW.montant_restant = NEW.montant_total - NEW.montant_paye;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `before_dette_update`;
DELIMITER $$
CREATE TRIGGER `before_dette_update` BEFORE UPDATE ON `dettes` FOR EACH ROW BEGIN
  SET NEW.montant_restant = NEW.montant_total - NEW.montant_paye;
  IF NEW.montant_restant <= 0 THEN
    SET NEW.statut = 'payee';
  ELSEIF NEW.montant_paye > 0 THEN
    SET NEW.statut = 'partiellement_payee';
  ELSE
    SET NEW.statut = 'active';
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Structure de la table `historique_prix`
--

DROP TABLE IF EXISTS `historique_prix`;
CREATE TABLE IF NOT EXISTS `historique_prix` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `produits_unites_id` int NOT NULL,
  `ancien_prix` decimal(10,2) NOT NULL,
  `nouveau_prix` decimal(10,2) NOT NULL,
  `utilisateur_id` int NOT NULL,
  `date_modification` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_modification` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Optionnel - adresse IP',
  PRIMARY KEY (`id`),
  KEY `idx_produits_unites` (`produits_unites_id`),
  KEY `idx_date_modif` (`date_modification`),
  KEY `fk_historique_user` (`utilisateur_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `paiements_dette`
--

DROP TABLE IF EXISTS `paiements_dette`;
CREATE TABLE IF NOT EXISTS `paiements_dette` (
  `id` int NOT NULL AUTO_INCREMENT,
  `dette_id` int NOT NULL,
  `montant_paye` decimal(10,2) NOT NULL,
  `mode_paiement` enum('especes','mobile_money','cheque','virement') COLLATE utf8mb4_unicode_ci DEFAULT 'especes',
  `reference_paiement` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Numéro de transaction, chèque, etc.',
  `note` text COLLATE utf8mb4_unicode_ci,
  `enregistre_par` int NOT NULL COMMENT 'Utilisateur qui a enregistré le paiement',
  `date_paiement` date NOT NULL,
  `date_creation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `enregistre_par` (`enregistre_par`),
  KEY `idx_dette` (`dette_id`),
  KEY `idx_date` (`date_paiement`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `paiements_dette`
--

INSERT INTO `paiements_dette` (`id`, `dette_id`, `montant_paye`, `mode_paiement`, `reference_paiement`, `note`, `enregistre_par`, `date_paiement`, `date_creation`) VALUES
(1, 1, 50000.00, 'especes', 'MADAME-20260111', NULL, 3, '2026-01-11', '2026-01-11 13:11:57'),
(2, 1, 70000.00, 'especes', 'MADAME-20260111-2', NULL, 3, '2026-01-11', '2026-01-11 13:12:39'),
(3, 4, 7500.00, 'especes', 'MAMAZETY-20260111', NULL, 4, '2026-01-11', '2026-01-11 13:14:31'),
(4, 4, 5000.00, 'especes', 'MAMAZETY-20260111-2', NULL, 4, '2026-01-11', '2026-01-11 13:20:28'),
(5, 6, 7500.00, 'especes', 'MAMAZETY-20260111-3', NULL, 4, '2026-01-11', '2026-01-11 13:22:08'),
(6, 6, 2500.00, 'especes', 'MAMAZETY-20260111-4', NULL, 4, '2026-01-11', '2026-01-11 13:31:25'),
(7, 6, 2500.00, 'especes', 'MAMAZETY-20260111-5', NULL, 3, '2026-01-11', '2026-01-11 13:32:15'),
(8, 8, 3500.00, 'especes', 'MAMANI-20260111', NULL, 3, '2026-01-11', '2026-01-11 13:34:20'),
(9, 8, 25000.00, 'especes', 'MAMANI-20260111-2', NULL, 4, '2026-01-11', '2026-01-11 13:35:06'),
(10, 11, 50000.00, 'especes', 'MAMAZETY-20260111-6', NULL, 4, '2026-01-11', '2026-01-11 14:25:57');

-- --------------------------------------------------------

--
-- Structure de la table `produits`
--

DROP TABLE IF EXISTS `produits`;
CREATE TABLE IF NOT EXISTS `produits` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `actif` tinyint(1) DEFAULT '1',
  `date_creation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `produits`
--

INSERT INTO `produits` (`id`, `nom`, `description`, `actif`, `date_creation`, `date_modification`) VALUES
(1, 'Huile', 'Huile végétale', 1, '2026-01-11 13:08:29', '2026-01-11 13:08:29'),
(2, 'Biscuits', 'Biscuits assortis', 1, '2026-01-11 13:08:29', '2026-01-11 13:08:29'),
(3, 'Sucre', 'Sucre cristallisé', 1, '2026-01-11 13:08:29', '2026-01-11 13:08:29'),
(4, 'Farine', 'Farine de blé', 1, '2026-01-11 13:08:29', '2026-01-11 13:08:29'),
(5, 'Riz', 'Riz premium', 1, '2026-01-11 13:08:29', '2026-01-11 13:08:29');

-- --------------------------------------------------------

--
-- Structure de la table `produits_unites`
--

DROP TABLE IF EXISTS `produits_unites`;
CREATE TABLE IF NOT EXISTS `produits_unites` (
  `id` int NOT NULL AUTO_INCREMENT,
  `produit_id` int NOT NULL,
  `unite_id` int NOT NULL,
  `prix_unitaire` decimal(10,2) NOT NULL,
  `actif` tinyint(1) DEFAULT '1',
  `date_creation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_produit_unite` (`produit_id`,`unite_id`),
  KEY `unite_id` (`unite_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `produits_unites`
--

INSERT INTO `produits_unites` (`id`, `produit_id`, `unite_id`, `prix_unitaire`, `actif`, `date_creation`, `date_modification`) VALUES
(1, 1, 9, 30000.00, 1, '2026-01-11 13:08:30', '2026-01-11 15:00:42'),
(2, 1, 6, 8000.00, 1, '2026-01-11 13:08:30', '2026-01-11 13:08:30'),
(3, 2, 1, 500.00, 1, '2026-01-11 13:08:30', '2026-01-11 13:08:30'),
(4, 2, 2, 2500.00, 1, '2026-01-11 13:08:30', '2026-01-11 13:08:30'),
(5, 2, 4, 12000.00, 1, '2026-01-11 13:08:30', '2026-01-11 13:08:30'),
(6, 3, 5, 50000.00, 1, '2026-01-11 13:08:30', '2026-01-11 13:08:30'),
(7, 3, 3, 4500.00, 1, '2026-01-11 13:08:30', '2026-01-11 13:08:30'),
(8, 3, 10, 1000.00, 1, '2026-01-11 13:08:30', '2026-01-11 13:08:30'),
(9, 4, 5, 45000.00, 1, '2026-01-11 13:08:30', '2026-01-11 13:08:30'),
(10, 4, 2, 2500.00, 1, '2026-01-11 13:08:30', '2026-01-11 13:08:30'),
(11, 5, 5, 35000.00, 1, '2026-01-11 13:08:30', '2026-01-11 13:08:30'),
(12, 5, 3, 5000.00, 1, '2026-01-11 13:08:30', '2026-01-11 13:08:30');

-- --------------------------------------------------------

--
-- Structure de la table `unites`
--

DROP TABLE IF EXISTS `unites`;
CREATE TABLE IF NOT EXISTS `unites` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `symbole` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `actif` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `nom` (`nom`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `unites`
--

INSERT INTO `unites` (`id`, `nom`, `symbole`, `actif`) VALUES
(1, 'Pièce', 'pcs', 1),
(2, 'Paquet', 'pqt', 1),
(3, 'Kilogramme', 'kg', 1),
(4, 'Carton', 'ctn', 1),
(5, 'Sac', 'sac', 1),
(6, 'Litre', 'L', 1),
(7, 'Gramme', 'g', 1),
(8, 'Douzaine', 'dz', 1),
(9, 'Bidon', 'bd', 1),
(10, 'Kapoka', 'kpk', 1);

-- --------------------------------------------------------

--
-- Structure de la table `utilisateurs`
--

DROP TABLE IF EXISTS `utilisateurs`;
CREATE TABLE IF NOT EXISTS `utilisateurs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom_complet` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pseudo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mot_de_passe` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_creation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pseudo` (`pseudo`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `utilisateurs`
--

INSERT INTO `utilisateurs` (`id`, `nom_complet`, `pseudo`, `mot_de_passe`, `date_creation`) VALUES
(1, 'Administrateur', 'admin', 'admin123', '2026-01-07 10:26:28'),
(2, 'Jean Rakoto', 'jean', 'jean123', '2026-01-07 10:26:28'),
(3, 'Bryan Scott Fanambinantsoa', 'Voaybe', 'MOODkyle35', '2026-01-07 10:27:41'),
(4, 'Marie Angeline', 'Maman Ndoh', 'Arnandoh', '2026-01-09 17:10:27');

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `vue_dettes_completes`
-- (Voir ci-dessous la vue réelle)
--
DROP VIEW IF EXISTS `vue_dettes_completes`;
CREATE TABLE IF NOT EXISTS `vue_dettes_completes` (
`client_nom` varchar(100)
,`client_telephone` varchar(20)
,`date_creation` timestamp
,`date_dette` date
,`designation` varchar(113)
,`dette_id` int
,`enregistre_par_nom` varchar(100)
,`montant_paye` decimal(10,2)
,`montant_restant` decimal(10,2)
,`montant_total` decimal(10,2)
,`numero_facture` varchar(50)
,`prix_unitaire_fige` decimal(10,2)
,`produit_nom` varchar(100)
,`quantite` decimal(10,2)
,`statut` enum('active','payee','partiellement_payee')
,`unite_symbole` varchar(10)
);

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `vue_produits_complets`
-- (Voir ci-dessous la vue réelle)
--
DROP VIEW IF EXISTS `vue_produits_complets`;
CREATE TABLE IF NOT EXISTS `vue_produits_complets` (
`description` text
,`designation_complete` varchar(113)
,`prix_unitaire` decimal(10,2)
,`produit_actif` tinyint(1)
,`produit_id` int
,`produit_nom` varchar(100)
,`produit_unite_id` int
,`unite_active` tinyint(1)
,`unite_id` int
,`unite_nom` varchar(50)
,`unite_symbole` varchar(10)
);

-- --------------------------------------------------------

--
-- Structure de la vue `vue_dettes_completes`
--
DROP TABLE IF EXISTS `vue_dettes_completes`;

DROP VIEW IF EXISTS `vue_dettes_completes`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vue_dettes_completes`  AS SELECT `d`.`id` AS `dette_id`, `d`.`numero_facture` AS `numero_facture`, `c`.`nom_complet` AS `client_nom`, `c`.`telephone` AS `client_telephone`, `p`.`nom` AS `produit_nom`, `u`.`symbole` AS `unite_symbole`, concat(`p`.`nom`,' (',`u`.`symbole`,')') AS `designation`, `d`.`quantite` AS `quantite`, `d`.`prix_unitaire_fige` AS `prix_unitaire_fige`, `d`.`montant_total` AS `montant_total`, `d`.`montant_paye` AS `montant_paye`, `d`.`montant_restant` AS `montant_restant`, `d`.`statut` AS `statut`, `usr`.`nom_complet` AS `enregistre_par_nom`, `d`.`date_dette` AS `date_dette`, `d`.`date_creation` AS `date_creation` FROM (((((`dettes` `d` join `clients` `c` on((`d`.`client_id` = `c`.`id`))) join `produits_unites` `pu` on((`d`.`produit_unite_id` = `pu`.`id`))) join `produits` `p` on((`pu`.`produit_id` = `p`.`id`))) join `unites` `u` on((`pu`.`unite_id` = `u`.`id`))) join `utilisateurs` `usr` on((`d`.`enregistre_par` = `usr`.`id`))) ;

-- --------------------------------------------------------

--
-- Structure de la vue `vue_produits_complets`
--
DROP TABLE IF EXISTS `vue_produits_complets`;

DROP VIEW IF EXISTS `vue_produits_complets`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vue_produits_complets`  AS SELECT `p`.`id` AS `produit_id`, `p`.`nom` AS `produit_nom`, `p`.`description` AS `description`, `pu`.`id` AS `produit_unite_id`, `u`.`id` AS `unite_id`, `u`.`nom` AS `unite_nom`, `u`.`symbole` AS `unite_symbole`, `pu`.`prix_unitaire` AS `prix_unitaire`, concat(`p`.`nom`,' (',`u`.`symbole`,')') AS `designation_complete`, `p`.`actif` AS `produit_actif`, `pu`.`actif` AS `unite_active` FROM ((`produits` `p` join `produits_unites` `pu` on((`p`.`id` = `pu`.`produit_id`))) join `unites` `u` on((`pu`.`unite_id` = `u`.`id`))) ;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `dettes`
--
ALTER TABLE `dettes`
  ADD CONSTRAINT `dettes_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  ADD CONSTRAINT `dettes_ibfk_2` FOREIGN KEY (`produit_unite_id`) REFERENCES `produits_unites` (`id`),
  ADD CONSTRAINT `dettes_ibfk_3` FOREIGN KEY (`enregistre_par`) REFERENCES `utilisateurs` (`id`);

--
-- Contraintes pour la table `historique_prix`
--
ALTER TABLE `historique_prix`
  ADD CONSTRAINT `fk_historique_pu` FOREIGN KEY (`produits_unites_id`) REFERENCES `produits_unites` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_historique_user` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Contraintes pour la table `paiements_dette`
--
ALTER TABLE `paiements_dette`
  ADD CONSTRAINT `paiements_dette_ibfk_1` FOREIGN KEY (`dette_id`) REFERENCES `dettes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `paiements_dette_ibfk_2` FOREIGN KEY (`enregistre_par`) REFERENCES `utilisateurs` (`id`);

--
-- Contraintes pour la table `produits_unites`
--
ALTER TABLE `produits_unites`
  ADD CONSTRAINT `produits_unites_ibfk_1` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `produits_unites_ibfk_2` FOREIGN KEY (`unite_id`) REFERENCES `unites` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
