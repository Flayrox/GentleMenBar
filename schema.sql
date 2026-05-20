-- Schema léger pour Le Gentleman Pub
-- Table de configuration globale du site
CREATE TABLE IF NOT EXISTS `site_config` (
  `cle` VARCHAR(50) NOT NULL,
  `valeur` TEXT,
  PRIMARY KEY (`cle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des matchs
CREATE TABLE IF NOT EXISTS `matchs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(191) NOT NULL,
  `equipe_1` VARCHAR(150) NOT NULL,
  `equipe_2` VARCHAR(150) NOT NULL,
  `competition` VARCHAR(150) DEFAULT NULL,
  `date_match` DATETIME NOT NULL,
  `image_path` VARCHAR(255) DEFAULT NULL,
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

-- Configuration initiale du site
INSERT INTO `site_config` (`cle`, `valeur`) VALUES
('site_name', 'Le Gentleman Pub'),
('site_tagline', 'Pub irlandais à Saint-Michel, Paris'),
('hero_title', 'Le Gentleman Pub'),
('hero_subtitle', 'Ambiance irlandaise, sports en direct, karaoké et grandes soirées à Saint-Michel.'),
('hero_cta_primary', 'Voir les prochains matchs'),
('hero_cta_secondary', 'Découvrir la carte'),
('hero_bg_image', '/assets/uploads/hero-bg.jpg'),
('bar_adresse', '3 Rue imaginaire, 75005 Paris'),
('bar_telephone', '01 23 45 67 89'),
('insta_link', 'https://instagram.com/'),
('facebook_link', 'https://facebook.com/'),
('horaires_semaine', '11:00 - 02:00'),
('horaires_weekend', '11:00 - 05:00'),
('horaires_dimanche', '12:00 - 00:00'),
('nav_matchs_label', 'Matchs'),
('nav_carte_label', 'Carte'),
('nav_infos_label', 'Infos'),
('section_matchs_title', 'Prochains matchs'),
('section_carte_title', 'La carte'),
('no_matchs_message', 'Aucun match prévu pour le moment'),
('footer_copy_text', 'Tous droits réservés'),
('footer_privacy_label', 'Espace Privé'),
('footer_hours_title', 'Horaires'),
('footer_socials_title', 'Réseaux'),
('footer_contact_title', 'Contact'),
('footer_address_title', 'Adresse'),
('footer_phone_label', 'Téléphone');

-- Exemples d'insertion (optionnel)
INSERT INTO `matchs` (`slug`,`equipe_1`,`equipe_2`,`competition`,`date_match`,`is_active`) VALUES
('psg-om','PSG','OM','Ligue 1','2026-10-01 21:00:00',1);

INSERT INTO `carte_produits` (`categorie`,`nom`,`description`,`prix_normal`,`prix_happy_hour`) VALUES
('Bières','Guinness pint','Guinness tirée au bar','6.50','4.50'),
('Cocktails','Irish Mule','Whisky, ginger beer, citron','10.00','8.00'),
('Planches','Planche de charcuterie','Sélection locale','14.00','12.00');
