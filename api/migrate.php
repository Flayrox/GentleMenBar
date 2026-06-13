<?php
declare(strict_types=1);

// Activer l'affichage des erreurs pour voir la progression de la migration
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../config/db.php';

try {
    echo "--- Début de la migration de la base de données ---<br>";
    
    // Récupérer les colonnes actuelles de la table `matchs`
    $existingColumns = [];
    $stmt = $pdo->query("DESCRIBE `matchs`");
    while ($row = $stmt->fetch()) {
        $existingColumns[] = $row['Field'];
    }

    // Définir les colonnes manquantes à ajouter
    $columnsToAdd = [
        'image_path_away' => "ALTER TABLE `matchs` ADD COLUMN `image_path_away` VARCHAR(255) DEFAULT NULL",
        'sport'           => "ALTER TABLE `matchs` ADD COLUMN `sport` VARCHAR(100) NOT NULL DEFAULT 'Soccer'",
        'is_featured'     => "ALTER TABLE `matchs` ADD COLUMN `is_featured` TINYINT(1) NOT NULL DEFAULT 0",
        'api_event_id'    => "ALTER TABLE `matchs` ADD COLUMN `api_event_id` VARCHAR(255) DEFAULT NULL"
    ];

    $changes = 0;
    foreach ($columnsToAdd as $col => $sql) {
        if (!in_array($col, $existingColumns, true)) {
            $pdo->exec($sql);
            echo "✅ Colonne <strong>$col</strong> ajoutée avec succès.<br>";
            $changes++;
        } else {
            echo "ℹ️ Colonne <strong>$col</strong> déjà présente.<br>";
        }
    }

    if ($changes > 0) {
        echo "🎉 Migration terminée avec succès ! ($changes modification(s))<br>";
    } else {
        echo "🎉 La table `matchs` est déjà à jour !<br>";
    }

} catch (Throwable $e) {
    echo "❌ Erreur de migration : " . $e->getMessage() . "<br>";
}
