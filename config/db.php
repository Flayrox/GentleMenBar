<?php
declare(strict_types=1);
// Connexion PDO sécurisée à la base MySQL (Support local et Hostinger)
$isLocal = in_array(($_SERVER['REMOTE_ADDR'] ?? ''), ['127.0.0.1', '::1'], true) || (php_sapi_name() === 'cli' && !getenv('HOSTINGER_ENV'));

if ($isLocal) {
    $dbHost = 'localhost';
    $dbName = 'legentlemanpub';
    $dbUser = 'root';
    $dbPass = '';
} else {
    $dbHost = 'localhost';
    $dbName = 'u123456789_nom_de_la_base';
    $dbUser = 'u123456789_utilisateur';
    $dbPass = 'MOT_DE_PASSE_ICI';
}


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

        $apiKey = config_value('sportsdb_api_key', '3');
        $eventId = $match['api_event_id'];
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 2.0, // Timeout court pour ne jamais figer la page
            ]
        ]);

        $url = "https://www.thesportsdb.com/api/v1/json/" . urlencode($apiKey) . "/lookupevent.php?id=" . urlencode($eventId);
        $response = @file_get_contents($url, false, $ctx);
        if ($response === false) {
            return;
        }

        $data = json_decode($response, true);
        if (isset($data['events'][0])) {
            $event = $data['events'][0];
            $score1 = $event['intHomeScore'];
            $score2 = $event['intAwayScore'];
            $status = $event['strStatus'] ?? '';
            $isFinished = ($status === 'FT' || $status === 'Match Finished');
            $isLive = in_array($status, ['1H', '2H', 'HT', 'Live', 'In Progress'], true);
            $progress = isset($event['strProgress']) ? (int)$event['strProgress'] : null;
            
            if ($score1 !== null && $score2 !== null) {
                $upStmt = $pdo->prepare("UPDATE matchs SET score_equipe_1 = :s1, score_equipe_2 = :s2, statut = :statut, minute_actuelle = :min WHERE id = :id");
                $upStmt->execute([
                    ':s1' => (int)$score1,
                    ':s2' => (int)$score2,
                    ':statut' => $isFinished ? 'finished' : ($isLive ? 'live' : 'scheduled'),
                    ':min' => $progress,
                    ':id' => (int)$match['id']
                ]);
            } elseif ($isFinished) {
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

function slugify(string $value): string
{
    $value = trim($value);
    $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if ($transliterated !== false) {
        $value = $transliterated;
    }
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?? '';
    $value = trim($value, '-');
    return $value !== '' ? $value : 'match';
}

function apply_slug_synonyms(string $value): string
{
    $normalized = trim($value);
    $synonyms = [
      '/\b(paris\s*saint[\s-]*germain|paris\s+sg|paris)\b/i' => 'psg',
      '/\b(olympique\s+de\s+marseille|marseille)\b/i' => 'om',
    ];

    foreach ($synonyms as $pattern => $replacement) {
      $normalized = preg_replace($pattern, $replacement, $normalized) ?? $normalized;
    }

    return $normalized;
}

function sanitize_custom_slug(string $slug): string
{
    return slugify($slug);
}

function generate_unique_match_slug(PDO $pdo, string $equipe1, string $equipe2, string $dateMatch, string $customSlug = '', int $ignoreId = 0): string
{
    if ($customSlug !== '') {
      $candidate = sanitize_custom_slug($customSlug);
      $stmt = $pdo->prepare('SELECT COUNT(*) FROM matchs WHERE slug = :slug' . ($ignoreId > 0 ? ' AND id != :ignore_id' : ''));
      $params = [':slug' => $candidate];
      if ($ignoreId > 0) {
        $params[':ignore_id'] = $ignoreId;
      }
      $stmt->execute($params);
      if ((int)$stmt->fetchColumn() === 0) {
        return $candidate;
      }
    }

    $year = (new DateTimeImmutable($dateMatch, new DateTimeZone('Europe/Paris')))->format('Y');
    $team1 = apply_slug_synonyms($equipe1);
    $team2 = apply_slug_synonyms($equipe2);
    $base = slugify($team1 . ' ' . $team2 . ' ' . $year);
    $slug = $base;
    $index = 2;

    while (true) {
      $sql = 'SELECT COUNT(*) FROM matchs WHERE slug = :slug';
      if ($ignoreId > 0) {
        $sql .= ' AND id != :ignore_id';
      }
      $stmt = $pdo->prepare($sql);
      $params = [':slug' => $slug];
      if ($ignoreId > 0) {
        $params[':ignore_id'] = $ignoreId;
      }
      $stmt->execute($params);
        if ((int)$stmt->fetchColumn() === 0) {
            return $slug;
        }
        $slug = $base . '-' . $index;
        $index++;
    }
}

