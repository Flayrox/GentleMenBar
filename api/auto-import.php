<?php
declare(strict_types=1);

// Configuration et chargement de la base de données
require_once __DIR__ . '/../config/db.php';

// Limite le déclenchement automatique à une seule fois par jour pour préserver les requêtes
$todayStr = date('Y-m-d');
$lastImport = config_value('last_auto_import_date', '');
if ($lastImport === $todayStr) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'message' => 'Déjà synchronisé aujourd\'hui', 'date' => $todayStr]);
    exit;
}

// Récupère la clé API TheSportsDB configurée (clé par défaut: 3)
$apiKey = config_value('sportsdb_api_key', '3');

// Envoi immédiat d'une réponse 200 OK au client pour continuer le script en arrière-plan sans bloquer (uniquement hors CLI)
if (php_sapi_name() !== 'cli') {
    if (function_exists('fastcgi_finish_request')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'message' => 'Import automatique lancé en arrière-plan']);
        fastcgi_finish_request();
    } else {
        ob_start();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'message' => 'Import automatique lancé en arrière-plan']);
        header('Connection: close');
        header('Content-Length: ' . (string)ob_get_length());
        ob_end_flush();
        flush();
    }
    ignore_user_abort(true);
}

// Configuration d'arrière-plan
set_time_limit(120);

// Championnats à synchroniser avec leurs IDs TheSportsDB
$leaguesToSync = [
    ['id' => '4334', 'name' => 'Ligue 1', 'sport' => 'Soccer'],
    ['id' => '4480', 'name' => 'Champions League', 'sport' => 'Soccer'],
    ['id' => '4426', 'name' => 'Coupe du Monde', 'sport' => 'Soccer'],
    ['id' => '4562', 'name' => 'Euro', 'sport' => 'Soccer'],
    ['id' => '4328', 'name' => 'Premier League', 'sport' => 'Soccer'],
    ['id' => '4335', 'name' => 'La Liga', 'sport' => 'Soccer'],
    ['id' => '4332', 'name' => 'Serie A', 'sport' => 'Soccer'],
    ['id' => '4396', 'name' => 'Ligue 2', 'sport' => 'Soccer'],
    ['id' => '4484', 'name' => 'Coupe de France', 'sport' => 'Soccer'],
    ['id' => '4415', 'name' => 'Top 14 Rugby', 'sport' => 'Rugby'],
    ['id' => '4417', 'name' => 'Six Nations Rugby', 'sport' => 'Rugby']
];

// Fenêtre temporelle : aujourd'hui et les 15 prochains jours
$todayStart = new DateTime('today', new DateTimeZone('Europe/Paris'));
$maxLimit = (new DateTime('today', new DateTimeZone('Europe/Paris')))->modify('+15 days');
$maxLimit->setTime(23, 59, 59);

$ctx = stream_context_create([
    'http' => [
        'timeout' => 4.0
    ]
]);

$importedCount = 0;

foreach ($leaguesToSync as $league) {
    try {
        $url = "https://www.thesportsdb.com/api/v1/json/" . urlencode($apiKey) . "/eventsnextleague.php?id=" . urlencode($league['id']);
        
        if (php_sapi_name() === 'cli') {
            echo "Querying: " . $league['name'] . " - " . $url . "\n";
        }

        $response = @file_get_contents($url, false, $ctx);
        if ($response === false) {
            if (php_sapi_name() === 'cli') {
                echo "Error: file_get_contents returned false for " . $league['name'] . "\n";
            }
            continue;
        }

        $data = json_decode($response, true);
        
        if (php_sapi_name() === 'cli') {
            echo "API Results for " . $league['name'] . ": " . (isset($data['events']) ? count($data['events']) : 0) . " items.\n";
        }

        if (empty($data['events'])) {
            continue;
        }

        foreach ($data['events'] as $item) {
            $apiEventId = (string)($item['idEvent'] ?? '');
            $dateStr = $item['dateEvent'] ?? '';
            $timeStr = $item['strTime'] ?? '00:00:00';
            $homeName = $item['strHomeTeam'] ?? '';
            $awayName = $item['strAwayTeam'] ?? '';
            $homeLogo = $item['strHomeTeamBadge'] ?? '';
            $awayLogo = $item['strAwayTeamBadge'] ?? '';
            $sportType = $item['strSport'] ?? $league['sport'];

            if ($apiEventId === '' || $dateStr === '' || $homeName === '' || $awayName === '') {
                continue;
            }

            // Nettoyage de l'heure pour éviter les décalages de parsing
            $timeStr = explode('+', $timeStr)[0];
            $timeStr = explode('Z', $timeStr)[0];

            // Les heures de TheSportsDB sont en UTC. On parse en UTC puis convertit en heure locale FR
            try {
                $dateObj = new DateTime($dateStr . 'T' . $timeStr, new DateTimeZone('UTC'));
                $dateObj->setTimezone(new DateTimeZone('Europe/Paris'));
            } catch (Throwable $t) {
                continue;
            }

            if (php_sapi_name() === 'cli') {
                echo " - Testing match: " . $homeName . " vs " . $awayName . " (Date: " . $dateObj->format('Y-m-d H:i:s') . ")";
            }

            // Vérification de la fenêtre temporelle de 15 jours
            if ($dateObj < $todayStart || $dateObj > $maxLimit) {
                if (php_sapi_name() === 'cli') {
                    echo " -> Skipped: outside 15-day range (today is " . $todayStart->format('Y-m-d') . ", limit is " . $maxLimit->format('Y-m-d H:i:s') . ")\n";
                }
                continue;
            }

            // Règles de filtrage intelligent (Grand match ?)
            $home = mb_strtolower($homeName, 'UTF-8');
            $away = mb_strtolower($awayName, 'UTF-8');
            $comp = mb_strtolower($league['name'], 'UTF-8');

            $isMajorTournament = (
                strpos($comp, 'world cup') !== false ||
                strpos($comp, 'coupe du monde') !== false ||
                strpos($comp, 'euro') !== false ||
                strpos($comp, 'six nations') !== false
            );

            $hasKeyword = false;
            $keywords = ['psg', 'paris sg', 'paris saint-germain', 'marseille', 'om', 'france', 'toulouse', 'monaco', 'lyon', 'real madrid', 'barcelona', 'toulon', 'racing 92', 'clermont', 'la rochelle', 'leinster', 'saracens'];
            foreach ($keywords as $kw) {
                if (strpos($home, $kw) !== false || strpos($away, $kw) !== false) {
                    $hasKeyword = true;
                    break;
                }
            }

            if (!$isMajorTournament && !$hasKeyword) {
                if (php_sapi_name() === 'cli') {
                    echo " -> Skipped: not a major tournament/team\n";
                }
                continue; // On n'importe pas ce match car il n'est pas considéré comme "grand match"
            }

            if (php_sapi_name() === 'cli') {
                echo " -> MATCH DETECTED FOR IMPORT!\n";
            }

            $dateMatch = $dateObj->format('Y-m-d H:i:s');

            // Vérification des doublons en base
            $exists = false;
            $checkStmt = $pdo->prepare('SELECT COUNT(*) FROM matchs WHERE api_event_id = :api_event_id');
            $checkStmt->execute([':api_event_id' => $apiEventId]);
            if ((int)$checkStmt->fetchColumn() > 0) {
                $exists = true;
            }

            if (!$exists) {
                $dayStart = $dateObj->format('Y-m-d 00:00:00');
                $dayEnd = $dateObj->format('Y-m-d 23:59:59');
                $checkStmt = $pdo->prepare('SELECT COUNT(*) FROM matchs WHERE equipe_1 = :e1 AND equipe_2 = :e2 AND date_match BETWEEN :dstart AND :dend');
                $checkStmt->execute([
                    ':e1' => $homeName,
                    ':e2' => $awayName,
                    ':dstart' => $dayStart,
                    ':dend' => $dayEnd,
                ]);
                if ((int)$checkStmt->fetchColumn() > 0) {
                    $exists = true;
                }
            }

            if ($exists) {
                // Mise à jour de la date, de la compétition et des logos si le match existe déjà
                $updateStmt = $pdo->prepare('UPDATE matchs SET date_match = :date_match, image_path = :image_path_home, image_path_away = :image_path_away, competition = :competition, sport = :sport WHERE api_event_id = :api_event_id');
                $updateStmt->execute([
                    ':date_match' => $dateMatch,
                    ':image_path_home' => $homeLogo !== '' ? $homeLogo : null,
                    ':image_path_away' => $awayLogo !== '' ? $awayLogo : null,
                    ':competition' => $league['name'],
                    ':sport' => $sportType,
                    ':api_event_id' => $apiEventId,
                ]);
            } else {
                // Création et insertion
                $slug = generate_unique_match_slug($pdo, $homeName, $awayName, $dateMatch);
                $insertStmt = $pdo->prepare('INSERT INTO matchs (slug, equipe_1, equipe_2, competition, date_match, image_path, image_path_away, sport, api_event_id, statut, is_active) VALUES (:slug, :equipe_1, :equipe_2, :competition, :date_match, :image_path_home, :image_path_away, :sport, :api_event_id, \'scheduled\', 1)');
                $insertStmt->execute([
                    ':slug' => $slug,
                    ':equipe_1' => $homeName,
                    ':equipe_2' => $awayName,
                    ':competition' => $league['name'],
                    ':date_match' => $dateMatch,
                    ':image_path_home' => $homeLogo !== '' ? $homeLogo : null,
                    ':image_path_away' => $awayLogo !== '' ? $awayLogo : null,
                    ':sport' => $sportType,
                    ':api_event_id' => $apiEventId,
                ]);
                $importedCount++;
            }
        }
    } catch (Throwable $e) {
        error_log('Error auto-importing league ' . $league['name'] . ': ' . $e->getMessage());
    }
}

// Mise à jour de la date d'importation dans site_config pour bloquer les futurs lancements aujourd'hui
try {
    $stmt = $pdo->prepare('INSERT INTO site_config (cle, valeur) VALUES ("last_auto_import_date", :val) ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)');
    $stmt->execute([':val' => $todayStr]);
} catch (Throwable $e) {
    error_log('Error saving last_auto_import_date: ' . $e->getMessage());
}

if (php_sapi_name() === 'cli') {
    echo "SUCCESS: Importation terminee. Matchs importes : " . $importedCount . "\n";
}
