<?php
declare(strict_types=1);
// Connexion PDO sécurisée à la base MySQL
// Configurez les variables d'environnement DB_HOST, DB_NAME, DB_USER, DB_PASS
$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbName = getenv('DB_NAME') ?: 'legentlemanpub';
//$dbUser = getenv('DB_USER') ?: 'db_user';
//$dbPass = getenv('DB_PASS') ?: 'db_pass';

$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';


$dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
];

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
} catch (PDOException $e) {
    // Ne pas divulguer d'informations sensibles à l'utilisateur
    error_log('DB Connection Error: ' . $e->getMessage());
    http_response_code(500);
    echo "Erreur de connexion à la base de données.";
    exit;
}

// Charge la configuration globale du site en tableau accessible partout
$config = [];
try {
    $stmt = $pdo->query('SELECT `cle`, `valeur` FROM site_config');
    foreach ($stmt->fetchAll() as $row) {
        $config[(string)$row['cle']] = (string)$row['valeur'];
    }
} catch (Throwable $e) {
    error_log('Config load error: ' . $e->getMessage());
}

function config_value(string $key, string $default = ''): string
{
    global $config;
    return isset($config[$key]) && $config[$key] !== '' ? (string)$config[$key] : $default;
}

// Petit helper global pour échapper en sortie
function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Définit timezone FR pour toutes les pages
date_default_timezone_set('Europe/Paris');

/**
 * Retourne le badge de statut du match de manière dynamique en fonction du temps écoulé
 */
function get_match_status_badge(array $match): string
{
    $dbStatut = $match['statut'] ?? 'scheduled';
    if ($dbStatut === 'finished') {
        return 'FINISHED';
    }

    $startTime = isset($match['date_match']) ? strtotime($match['date_match']) : 0;
    if ($startTime === 0) {
        return 'UPCOMING';
    }

    $now = time();
    $diff = $now - $startTime;

    // En cours : entre le début et 120 minutes plus tard
    if ($diff >= 0 && $diff <= 120 * 60) {
        $minute = $match['minute_actuelle'] !== null ? (int)$match['minute_actuelle'] : (int)floor($diff / 60);
        if ($minute > 90) {
            $minute = 90;
        }
        return "LIVE {$minute}'";
    }

    // Terminé : plus de 120 minutes après le début
    if ($diff > 120 * 60) {
        return 'FINISHED';
    }

    return 'UPCOMING';
}

/**
 * Vérifie si un match est en direct de manière dynamique
 */
function is_match_live(array $match): bool
{
    $dbStatut = $match['statut'] ?? 'scheduled';
    if ($dbStatut === 'finished') {
        return false;
    }

    $startTime = isset($match['date_match']) ? strtotime($match['date_match']) : 0;
    if ($startTime === 0) {
        return false;
    }

    $now = time();
    $diff = $now - $startTime;

    return ($diff >= 0 && $diff <= 120 * 60);
}

/**
 * Formate le score du match "2 - 1" ou retourne null si pas disponible
 */
function format_score(?int $score1, ?int $score2): ?string
{
    if ($score1 === null || $score2 === null) {
        return null;
    }
    return "{$score1} - {$score2}";
}

/**
 * Automatiquement met à jour les scores des matchs passés depuis l'API TheSportsDB
 */
function auto_update_past_scores(PDO $pdo): void
{
    try {
        // Sélectionne un match passé sans score ou non terminé avec un ID d'événement valide
        $stmt = $pdo->query("SELECT id, api_event_id FROM matchs WHERE date_match <= NOW() AND api_event_id IS NOT NULL AND (score_equipe_1 IS NULL OR score_equipe_2 IS NULL OR statut != 'finished') LIMIT 1");
        $match = $stmt->fetch();
        if (!$match) {
            return;
        }

        $eventId = $match['api_event_id'];
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 1.5, // Timeout court de 1.5s pour ne jamais figer la page
            ]
        ]);

        $url = "https://www.thesportsdb.com/api/v1/json/3/lookupevent.php?id=" . urlencode($eventId);
        $response = @file_get_contents($url, false, $ctx);
        if ($response === false) {
            return;
        }

        $data = json_decode($response, true);
        if (isset($data['events'][0])) {
            $event = $data['events'][0];
            $score1 = $event['intHomeScore'];
            $score2 = $event['intAwayScore'];
            
            if ($score1 !== null && $score2 !== null) {
                $upStmt = $pdo->prepare("UPDATE matchs SET score_equipe_1 = :s1, score_equipe_2 = :s2, statut = 'finished' WHERE id = :id");
                $upStmt->execute([
                    ':s1' => (int)$score1,
                    ':s2' => (int)$score2,
                    ':id' => (int)$match['id']
                ]);
            } elseif (($event['strStatus'] ?? '') === 'FT') {
                // Si le match est marqué terminé sur l'API mais sans scores, on évite de tourner en boucle
                $upStmt = $pdo->prepare("UPDATE matchs SET statut = 'finished' WHERE id = :id");
                $upStmt->execute([':id' => (int)$match['id']]);
            }
        }
    } catch (Throwable $e) {
        error_log('Auto score update error: ' . $e->getMessage());
    }
}

// Lancement de l'auto-score
auto_update_past_scores($pdo);

