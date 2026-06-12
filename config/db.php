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
 * Retourne le badge de statut du match avec minute si live
 * Exemples: "LIVE 45'", "UPCOMING", "FINISHED"
 */
function get_match_status_badge(?string $statut, ?int $minute = null): string
{
    if ($statut === 'live') {
        return 'LIVE' . ($minute !== null ? " {$minute}'" : '');
    }
    if ($statut === 'finished') {
        return 'FINISHED';
    }
    return 'UPCOMING';
}

/**
 * Vérifie si un match est en direct
 */
function is_match_live(?string $statut): bool
{
    return $statut === 'live';
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

// $pdo est disponible pour les inclusions
