<?php
/**
 * FrenchyBot - Configuration BDD centralisee
 */

session_start();

// --- Configuration BDD ---
define('DB_HOST', getenv('FRENCHYBOT_DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('FRENCHYBOT_DB_NAME') ?: 'frenchybot');
define('DB_USER', getenv('FRENCHYBOT_DB_USER') ?: 'root');
define('DB_PASS', getenv('FRENCHYBOT_DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// --- Configuration app ---
define('APP_NAME', 'FrenchyBot');
define('APP_URL', getenv('FRENCHYBOT_URL') ?: 'https://bot.frenchycompany.fr');
define('APP_VERSION', '1.0.0');

// --- Connexion PDO ---
try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    error_log('FrenchyBot DB Error: ' . $e->getMessage());
    die('Erreur de connexion a la base de donnees.');
}

// --- Charger les fichiers includes ---
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
