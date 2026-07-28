<?php
declare(strict_types=1);

// Activer l'affichage des erreurs pour le débuggage sur Hostinger
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Charge la configuration et la connexion PDO
require_once __DIR__ . '/../config/db.php';

// Augmenter le temps d'exécution maximal pour l'analyse iCal (120 secondes max)
@set_time_limit(120);
@ini_set('max_execution_time', '120');

// Fuseau horaire de référence
date_default_timezone_set('Europe/Paris');

// Logique de protection : Limitation à un import par jour
$todayStr = date('Y-m-d');
$lockFile = __DIR__ . '/last_import.txt';

// On n'active le verrou de date que si on n'est pas en mode CLI et qu'on ne force pas l'importation
if (php_sapi_name() !== 'cli' && (!isset($_GET['force']) || (int)$_GET['force'] !== 1)) {
    if (file_exists($lockFile) && trim((string)@file_get_contents($lockFile)) === $todayStr) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'message' => 'Déjà synchronisé aujourd\'hui (verrou actif)']);
        exit;
    }
}

// Configuration de la fenêtre d'importation (aujourd'hui + 15 jours)
$todayStart = new DateTime('today', new DateTimeZone('Europe/Paris'));
$maxLimit = (new DateTime('today', new DateTimeZone('Europe/Paris')))->modify('+15 days');
$maxLimit->setTime(23, 59, 59);

// Récupération des mots-clés configurables depuis la BDD (avec fallback)
$dbKeywordsRaw = config_value('auto_import_keywords', '');
if (!empty($dbKeywordsRaw)) {
    $keywords = array_filter(array_map('trim', explode(',', $dbKeywordsRaw)));
} else {
$keywords = [
    // Ligue 1 & Clubs Français Majeurs
    'PSG', 'Paris SG', 'Paris Saint-Germain', 'Marseille', 'OM', 'Olympique de Marseille', 
    'Lyon', 'OL', 'Olympique Lyonnais', 'Lens', 'RCL', 'Lille', 'LOSC', 'Monaco', 'ASM', 
    'Rennes', 'Stade Rennais', 'Nice', 'OGC Nice', 'Strasbourg', 'Toulouse', 'FC Nantes', 'Saint-Étienne', 'ASSE',
    
    // Équipes Nationales Majeures (Foot & Rugby)
    'France', 'XV de France', 'Bleus', 'Angleterre', 'England', 'Irlande', 'Ireland', 
    'All Blacks', 'Nouvelle-Zélande', 'Afrique du Sud', 'Springboks', 'Espagne', 'Spain', 
    'Allemagne', 'Germany', 'Italie', 'Italy', 'Brésil', 'Brazil', 'Argentine', 'Argentina',
    
    // Top Clubs Européens (Football)
    'Real Madrid', 'Real', 'Barcelona', 'Barça', 'Barcelone', 'Atletico', 'Bayern', 'Bayern Munich', 
    'Dortmund', 'BVB', 'Arsenal', 'Manchester United', 'Man United', 'Manchester City', 'Man City', 
    'Liverpool', 'Chelsea', 'Tottenham', 'Juventus', 'Juve', 'AC Milan', 'Milan', 'Inter', 'Inter Milan', 'Napoli',
    
    // Rugby (Top 14 & Champions Cup)
    'Stade Toulousain', 'Toulouse Rugby', 'La Rochelle', 'Stade Rochelais', 'Racing 92', 
    'Stade Français', 'UBB', 'Bordeaux-Bègles', 'Toulon', 'RCT', 'Clermont', 'ASM Clermont'
];
}

// Liste des calendriers iCal (.ics) à importer (Football, Rugby, NFL, Basketball)
$calendars = [
    'Football' => [
        'Coupe du Monde' => 'https://ics.fixtur.es/v2/league/fifa-world-cup-2026.ics',
        'Ligue 1' => 'https://ics.fixtur.es/v2/league/ligue-1.ics',
        'Champions League' => 'https://ics.fixtur.es/v2/league/champions-league.ics',
        'Europa League' => 'https://ics.fixtur.es/v2/league/europa-league.ics',
        'Premier League' => 'https://ics.fixtur.es/v2/league/premier-league.ics',
        'La Liga' => 'https://ics.fixtur.es/v2/league/primera-division.ics',
        'Serie A' => 'https://ics.fixtur.es/v2/league/serie-a.ics'
    ],
    'Rugby' => [
        'Six Nations' => 'https://ics.fixtur.es/v2/league/six-nations.ics',
        'Top 14' => 'https://ics.fixtur.es/v2/league/top-14.ics'
    ],
    'NFL' => [
        'NFL Regular Season' => 'https://ics.fixtur.es/v2/league/nfl.ics',
        'Super Bowl' => 'https://ics.fixtur.es/v2/league/nfl.ics'
    ],
    'Basketball' => [
        'NBA' => 'https://ics.fixtur.es/v2/league/nba.ics'
    ]
];

$autoFeatJson = config_value('auto_feature_competitions', '["Coupe du Monde", "Ligue 1", "Champions League", "Premier League", "NFL Regular Season", "Super Bowl", "NBA"]');
$enabledCompetitions = json_decode($autoFeatJson, true) ?: [];

$importedCount = 0;

foreach ($calendars as $sportCategory => $list) {
    $sport = $sportCategory;
    foreach ($list as $competition => $url) {
        // Si le championnat est décoché dans l'Admin, on passe l'import de cette ligue
        if (!empty($enabledCompetitions) && !in_array($competition, $enabledCompetitions, true)) {
            if (php_sapi_name() === 'cli') {
                echo " - Ignoré (championnat désactivé dans l'Admin) : {$competition}\n";
            }
            continue;
        }

        if (php_sapi_name() === 'cli') {
            echo "Téléchargement de [{$sport}] {$competition} ({$url})...\n";
        }

    // Téléchargement sécurisé via cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
    curl_setopt($ch, CURLOPT_USERAGENT, 'GentlemanPubBot/1.0');
    $icsContent = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);


    if ($icsContent === false || $httpCode !== 200) {
        if (php_sapi_name() === 'cli') {
            echo "Erreur lors du téléchargement de {$competition} (code HTTP {$httpCode}). Erreur cURL : {$curlError}\n";
        }
        continue;
    }

    // Dépliage des lignes du fichier iCal (RFC 5545)
    // Les lignes pliées commencent par un espace ou une tabulation sur la ligne suivante
    $icsContent = preg_replace('/\r?\n[ \t]/', '', $icsContent);

    // Découpage par événement VEVENT
    $events = explode('BEGIN:VEVENT', $icsContent);
    array_shift($events); // Enlève l'en-tête VCALENDAR

    if (php_sapi_name() === 'cli') {
        echo "Nombre d'événements trouvés : " . count($events) . "\n";
    }

    // Détermination du sport (tous les flux iCal sont du football)
    $sport = 'Soccer';

    foreach ($events as $eventBlock) {
        // Extraction de SUMMARY
        $summary = '';
        if (preg_match('/^SUMMARY:(.*)$/im', $eventBlock, $m)) {
            $summary = trim($m[1]);
            $summary = str_replace(['\\,', '\\;', '\\n', '\\r'], [',', ';', "\n", "\r"], $summary);
        }

        if ($summary === '') {
            continue;
        }

    // Filtrage strict : Le match doit obligatoirement inclure au moins UNE équipe majeure/populaire de la liste officiellement configurée (avec limites de mots)
    $hasKeyword = false;
    foreach ($keywords as $kw) {
        $pattern = '/\b' . preg_quote($kw, '/') . '\b/i';
        if (preg_match($pattern, $summary) === 1) {
            $hasKeyword = true;
            break;
        }
    }

    if (!$hasKeyword) {
        continue;
    }

        // Extraction de DTSTART
        $matchDate = '';
        if (preg_match('/^DTSTART(?:;TZID=([^:]+))?:([0-9]{8}T[0-9]{6}Z?)/im', $eventBlock, $m)) {
            $tzName = !empty($m[1]) ? $m[1] : (strpos($m[2], 'Z') !== false ? 'UTC' : 'Europe/Paris');
            $rawTime = str_replace('Z', '', $m[2]);
            try {
                $dateObj = new DateTime($rawTime, new DateTimeZone($tzName));
                $dateObj->setTimezone(new DateTimeZone('Europe/Paris'));
                $matchDate = $dateObj->format('Y-m-d H:i:s');
            } catch (Throwable $e) {
                continue;
            }
        } else if (preg_match('/^DTSTART;VALUE=DATE:([0-9]{8})/im', $eventBlock, $m)) {
            $rawTime = $m[1] . 'T120000';
            try {
                $dateObj = new DateTime($rawTime, new DateTimeZone('Europe/Paris'));
                $matchDate = $dateObj->format('Y-m-d H:i:s');
            } catch (Throwable $e) {
                continue;
            }
        }

        if ($matchDate === '') {
            continue;
        }

        // Contrôle de la fenêtre temporelle
        $matchDateTime = new DateTime($matchDate, new DateTimeZone('Europe/Paris'));
        if ($matchDateTime < $todayStart || $matchDateTime > $maxLimit) {
            if (php_sapi_name() === 'cli') {
                echo " - Ignoré (date hors limite) : {$summary} ({$matchDate})\n";
            }
            continue;
        }

        // Séparation Equipe 1 / Equipe 2
        $teams = preg_split('/\s+(?:-|vs|c\.)\s+/i', $summary);
        if (count($teams) >= 2) {
            $equipe1 = trim($teams[0]);
            $equipe2 = trim($teams[1]);
        } else {
            $equipe1 = $summary;
            $equipe2 = 'Adversaire';
        }

        // Nettoyage des étiquettes entre crochets (ex: [CL], [EL], [L1], [PL])
        $equipe1 = trim((string)preg_replace('/\[.*?\]/', '', $equipe1));
        $equipe2 = trim((string)preg_replace('/\[.*?\]/', '', $equipe2));

        // Récupération de l'UID de l'événement iCal pour éviter les doublons absolus
        $apiEventId = null;
        if (preg_match('/^UID:(.*)$/im', $eventBlock, $m)) {
            $apiEventId = trim($m[1]);
        }

        if (php_sapi_name() === 'cli') {
            echo " - Match détecté : {$equipe1} vs {$equipe2} ({$matchDate}) [Sport: {$sport}]\n";
        }

        // Détermination du slug de manière intelligente
        $slug = null;

        // 1. Tenter de retrouver le slug du match existant via son UID iCal
        if ($apiEventId !== null) {
            $stmt = $pdo->prepare('SELECT slug FROM matchs WHERE api_event_id = :uid LIMIT 1');
            $stmt->execute([':uid' => $apiEventId]);
            $slug = $stmt->fetchColumn() ?: null;
        }

        // 2. Chercher s'il y a un match des mêmes équipes à une date proche (+/- 3 jours)
        if ($slug === null) {
            $dayStart = (clone $matchDateTime)->modify('-3 days')->format('Y-m-d H:i:s');
            $dayEnd = (clone $matchDateTime)->modify('+3 days')->format('Y-m-d H:i:s');
            $stmt = $pdo->prepare('SELECT slug FROM matchs WHERE equipe_1 = :e1 AND equipe_2 = :e2 AND date_match BETWEEN :dstart AND :dend LIMIT 1');
            $stmt->execute([
                ':e1' => $equipe1,
                ':e2' => $equipe2,
                ':dstart' => $dayStart,
                ':dend' => $dayEnd
            ]);
            $slug = $stmt->fetchColumn() ?: null;
        }

        // 3. Si c'est un tout nouveau match, on génère un nouveau slug unique
        if ($slug === null) {
            $slug = generate_unique_match_slug($pdo, $equipe1, $equipe2, $matchDate);
        }

        // Résolution automatique des logos si disponibles
        $homeBadge = get_team_logo($equipe1);
        $awayBadge = get_team_logo($equipe2);

        // Insertion ou mise à jour de la date d'un match existant
        $stmt = $pdo->prepare('INSERT INTO matchs (slug, equipe_1, equipe_2, competition, date_match, sport, api_event_id, image_path, image_path_away, statut, is_active) 
            VALUES (:slug, :e1, :e2, :competition, :date_match, :sport, :api_event_id, :img_home, :img_away, \'scheduled\', 1)
            ON DUPLICATE KEY UPDATE date_match = VALUES(date_match), api_event_id = VALUES(api_event_id), image_path = COALESCE(VALUES(image_path), image_path), image_path_away = COALESCE(VALUES(image_path_away), image_path_away)');
        $stmt->execute([
            ':slug' => $slug,
            ':e1' => $equipe1,
            ':e2' => $equipe2,
            ':competition' => $competition,
            ':date_match' => $matchDate,
            ':sport' => $sport,
            ':api_event_id' => $apiEventId,
            ':img_home' => !empty($homeBadge) ? $homeBadge : null,
            ':img_away' => !empty($awayBadge) ? $awayBadge : null
        ]);
        $importedCount++;
    }
}
}

// --- DEBUT DE L'IMPORTATION RUGBY VIA THESPORTSDB ---
$sportsdbApiKey = config_value('sportsdb_api_key', '3');
if ($sportsdbApiKey === '') {
    $sportsdbApiKey = '3';
}
$rugbyLeagues = [
    'Top 14' => '4430',
    'Six Nations' => '4714'
];

foreach ($rugbyLeagues as $competition => $leagueId) {
    if (php_sapi_name() === 'cli') {
        echo "Téléchargement Rugby de : {$competition} via TheSportsDB...\n";
    }
    
    $url = "https://www.thesportsdb.com/api/v1/json/" . urlencode($sportsdbApiKey) . "/eventsnextleague.php?id=" . urlencode($leagueId);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
    curl_setopt($ch, CURLOPT_USERAGENT, 'GentlemanPubBot/1.0');
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    
    if ($response === false || $httpCode !== 200) {
        if (php_sapi_name() === 'cli') {
            echo "Erreur lors du téléchargement de Rugby {$competition} (code HTTP {$httpCode}).\n";
        }
        continue;
    }
    
    $data = json_decode($response, true);
    if (empty($data['events'])) {
        if (php_sapi_name() === 'cli') {
            echo "Aucun événement Rugby trouvé pour {$competition}.\n";
        }
        continue;
    }
    
    foreach ($data['events'] as $event) {
        $apiEventId = (string)($event['idEvent'] ?? '');
        $summary = trim((string)($event['strEvent'] ?? ''));
        if ($summary === '' || $apiEventId === '') {
            continue;
        }
        
        $matchDate = '';
        if (!empty($event['strTimestamp'])) {
            try {
                $dateObj = new DateTime($event['strTimestamp'], new DateTimeZone('UTC'));
                $dateObj->setTimezone(new DateTimeZone('Europe/Paris'));
                $matchDate = $dateObj->format('Y-m-d H:i:s');
            } catch (Throwable $e) {
                continue;
            }
        } elseif (!empty($event['dateEvent']) && !empty($event['strTime'])) {
            try {
                $dateObj = new DateTime($event['dateEvent'] . 'T' . $event['strTime'], new DateTimeZone('UTC'));
                $dateObj->setTimezone(new DateTimeZone('Europe/Paris'));
                $matchDate = $dateObj->format('Y-m-d H:i:s');
            } catch (Throwable $e) {
                continue;
            }
        }
        
        if ($matchDate === '') {
            continue;
        }
        
        $matchDateTime = new DateTime($matchDate, new DateTimeZone('Europe/Paris'));
        if ($matchDateTime < $todayStart || $matchDateTime > $maxLimit) {
            if (php_sapi_name() === 'cli') {
                echo " - Ignoré Rugby (date hors limite) : {$summary} ({$matchDate})\n";
            }
            continue;
        }
        
        $equipe1 = trim((string)($event['strHomeTeam'] ?? ''));
        $equipe2 = trim((string)($event['strAwayTeam'] ?? ''));
        if ($equipe1 === '' || $equipe2 === '') {
            $teams = preg_split('/\s+(?:-|vs|c\.)\s+/i', $summary);
            if (count($teams) >= 2) {
                $equipe1 = trim($teams[0]);
                $equipe2 = trim($teams[1]);
            } else {
                $equipe1 = $summary;
                $equipe2 = 'Adversaire';
            }
        }
        
        $isBypass = ($competition === 'Six Nations');
        if (!$isBypass) {
            $hasKeyword = false;
            foreach ($keywords as $kw) {
                if (stripos($equipe1, $kw) !== false || stripos($equipe2, $kw) !== false) {
                    $hasKeyword = true;
                    break;
                }
            }
            if (!$hasKeyword) {
                continue;
            }
        }
        
        $slug = null;
        
        // 1. Tenter de retrouver le slug du match existant via son UID
        $stmt = $pdo->prepare('SELECT slug FROM matchs WHERE api_event_id = :uid LIMIT 1');
        $stmt->execute([':uid' => $apiEventId]);
        $slug = $stmt->fetchColumn() ?: null;
        
        // 2. Chercher s'il y a un match des mêmes équipes à une date proche (+/- 3 jours)
        if ($slug === null) {
            $dayStartObj = (clone $matchDateTime)->modify('-3 days');
            $dayEndObj = (clone $matchDateTime)->modify('+3 days');
            $stmt = $pdo->prepare('SELECT slug FROM matchs WHERE equipe_1 = :e1 AND equipe_2 = :e2 AND date_match BETWEEN :dstart AND :dend LIMIT 1');
            $stmt->execute([
                ':e1' => $equipe1,
                ':e2' => $equipe2,
                ':dstart' => $dayStartObj->format('Y-m-d H:i:s'),
                ':dend' => $dayEndObj->format('Y-m-d H:i:s')
            ]);
            $slug = $stmt->fetchColumn() ?: null;
        }
        
        // 3. Nouveau slug
        if ($slug === null) {
            $slug = generate_unique_match_slug($pdo, $equipe1, $equipe2, $matchDate);
        }
        
        $homeBadge = !empty($event['strHomeTeamBadge']) ? $event['strHomeTeamBadge'] : null;
        $awayBadge = !empty($event['strAwayTeamBadge']) ? $event['strAwayTeamBadge'] : null;
        
        // Insertion ou mise à jour
        $stmt = $pdo->prepare('INSERT INTO matchs (slug, equipe_1, equipe_2, competition, date_match, sport, api_event_id, image_path, image_path_away, statut, is_active) 
            VALUES (:slug, :e1, :e2, :competition, :date_match, \'Rugby\', :api_event_id, :img_home, :img_away, \'scheduled\', 1)
            ON DUPLICATE KEY UPDATE date_match = VALUES(date_match), api_event_id = VALUES(api_event_id), image_path = COALESCE(VALUES(image_path), image_path), image_path_away = COALESCE(VALUES(image_path_away), image_path_away)');
        $stmt->execute([
            ':slug' => $slug,
            ':e1' => $equipe1,
            ':e2' => $equipe2,
            ':competition' => $competition,
            ':date_match' => $matchDate,
            ':api_event_id' => $apiEventId,
            ':img_home' => $homeBadge,
            ':img_away' => $awayBadge
        ]);
        
        $importedCount++;
    }
}
// --- FIN DE L'IMPORTATION RUGBY ---

// Sauvegarde de la date d'importation dans le fichier local
try {
    file_put_contents($lockFile, $todayStr);
} catch (Throwable $e) {
    error_log('Erreur lors de l\'enregistrement du verrou last_import.txt : ' . $e->getMessage());
}

if (php_sapi_name() === 'cli') {
    echo "SUCCESS: Importation terminée. Matchs importés : {$importedCount}\n";
} else {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'message' => "Importation terminée. {$importedCount} matchs importés."]);
}
