<?php
declare(strict_types=1);
// Connexion PDO sécurisée à la base MySQL
// Configurez les variables d'environnement DB_HOST, DB_NAME, DB_USER, DB_PASS
$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbName = getenv('DB_NAME') ?: 'legentlemanpub';
$dbUser = getenv('DB_USER') ?: 'db_user';
$dbPass = getenv('DB_PASS') ?: 'db_pass';

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

// Petit helper global pour échapper en sortie
function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Définit timezone FR pour toutes les pages
date_default_timezone_set('Europe/Paris');

// $pdo est disponible pour les inclusions
