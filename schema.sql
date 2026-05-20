-- Schema léger pour Le Gentleman Pub
-- Table des matchs
CREATE TABLE IF NOT EXISTS `matchs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(191) NOT NULL,
  `equipe_1` VARCHAR(150) NOT NULL,
  `equipe_2` VARCHAR(150) NOT NULL,
  `competition` VARCHAR(150) DEFAULT NULL,
  `date_match` DATETIME NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug_unique` (`slug`),
  KEY `date_match_idx` (`date_match`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table de la carte des produits
CREATE TABLE IF NOT EXISTS `carte_produits` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `categorie` VARCHAR(100) NOT NULL,
  `nom` VARCHAR(191) NOT NULL,
  `description` TEXT,
  `prix_normal` DECIMAL(7,2) NOT NULL DEFAULT 0.00,
  `prix_happy_hour` DECIMAL(7,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `categorie_idx` (`categorie`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exemples d'insertion (optionnel)
INSERT INTO `matchs` (`slug`,`equipe_1`,`equipe_2`,`competition`,`date_match`,`is_active`) VALUES
('psg-om','PSG','OM','Ligue 1','2026-10-01 21:00:00',1);

INSERT INTO `carte_produits` (`categorie`,`nom`,`description`,`prix_normal`,`prix_happy_hour`) VALUES
('Bières','Guinness pint','Guinness tirée au bar','6.50','4.50'),
('Cocktails','Irish Mule','Whisky, ginger beer, citron','10.00','8.00'),
('Planches','Planche de charcuterie','Sélection locale','14.00','12.00');
