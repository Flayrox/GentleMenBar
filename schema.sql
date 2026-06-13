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
  `image_path_away` VARCHAR(255) DEFAULT NULL,
  `score_equipe_1` INT DEFAULT NULL,
  `score_equipe_2` INT DEFAULT NULL,
  `minute_actuelle` INT DEFAULT NULL,
  `statut` ENUM('scheduled', 'live', 'finished') DEFAULT 'scheduled',
  `sport` VARCHAR(100) NOT NULL DEFAULT 'Soccer',
  `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `api_event_id` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug_unique` (`slug`),
  KEY `date_match_idx` (`date_match`),
  KEY `statut_idx` (`statut`)
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

-- Nettoyage des anciennes données de démarrage
DELETE FROM `site_config`;
DELETE FROM `carte_produits`;

-- Insertion de la configuration officielle du Gentleman Pub
INSERT INTO `site_config` (`cle`, `valeur`) VALUES
('site_name', 'Le Gentleman Pub'),
('site_tagline', 'Pub Irlandais & Sports Bar à Paris'),
('hero_title', 'Le Gentleman Pub'),
('hero_subtitle', 'Pintes fraîches, sports en direct et soirées karaoké au cœur de Saint-Germain.'),
('hero_cta_primary', 'Voir les matchs ce soir'),
('hero_cta_secondary', 'Découvrir la carte'),
('hero_bg_image', '/assets/uploads/hero-bg.jpg'),
('bar_adresse', '14 Rue Saint Germain, 75006 Paris'),
('bar_telephone', '01 71 71 71 71'),
('insta_link', 'https://www.instagram.com/legentlemanpub/'),
('facebook_link', 'https://facebook.com/'),
('horaires_semaine', '11:00 - 02:00'),
('horaires_weekend', '11:00 - 05:00'),
('horaires_dimanche', '12:00 - 00:00'),
('nav_matchs_label', 'Matchs'),
('nav_carte_label', 'Carte'),
('nav_infos_label', 'Infos'),
('section_matchs_title', 'Les Événements & Matchs'),
('section_carte_title', 'Boissons & Cocktails'),
('no_matchs_message', 'Aucun match prévu aujourd’hui. Rejoignez-nous pour l’Happy Hour !'),
('footer_copy_text', 'Tous droits réservés'),
('footer_privacy_label', 'Espace Privé'),
('footer_hours_title', 'Horaires'),
('footer_socials_title', 'Suivez-nous'),
('footer_address_title', 'Adresse'),
('footer_phone_label', 'Téléphone');

-- Insertion de la vraie sélection de boissons
INSERT INTO `carte_produits` (`categorie`, `nom`, `description`, `prix_normal`, `prix_happy_hour`) VALUES
('Bières', 'Gentleman Lager', 'La bière de la maison, légère et rafraîchissante', 8.00, 4.00),
('Bières', 'Guinness', 'L’incontournable stout irlandaise noire', 9.00, 6.00),
('Bières', 'Leffe Blonde', 'Bière d’abbaye belge de caractère', 9.00, 6.00),
('Bières', 'Tripel Karmeliet', 'Bière blonde belge triple aux trois céréales', 9.00, 6.00),
('Bières', 'Goose Midway IPA', 'Une India Pale Ale aux notes d’agrumes légères', 9.00, 6.00),
('Cocktails', 'Sex On The Beach', 'Vodka, crème de pêche, jus d’orange, jus de cranberry', 8.00, 6.00),
('Cocktails', 'Mojito', 'Rhum, sucre, citron vert, menthe fraîche, soda', 8.00, 6.00),
('Cocktails', 'Dark & Stormy', 'Rhum ambré, citron vert, ginger beer', 8.00, 6.00),
('Cocktails', 'Moscow Mule', 'Vodka, citron vert, ginger beer', 8.00, 6.00),
('Cocktails', 'Whiskey Sour', 'Jack Daniel’s, sour mix, blanc d’œuf, bitter', 8.00, 7.00),
('Cocktails', 'Espresso Martini', 'Vodka, Kahlúa, café expresso', 8.00, 7.00),
('Cocktails', 'Negroni', 'Campari, gin, vermouth rouge', 8.00, 7.00),
('Softs', 'Virgin Mojito', 'Menthe fraîche, sucre, soda, citron vert', 6.00, NULL),
('Softs', 'Coca-Cola / Zero', '33cl', 4.50, NULL),
('Food', 'B-52', 'Kahlúa, Bailey’s, triple sec', 5.50, NULL),
('Food', 'Baby Guinness', 'Kahlúa, Bailey’s (Le shot visuel)', 5.50, NULL);
