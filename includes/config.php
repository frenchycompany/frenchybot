<?php
/**
 * FrenchyBot - Configuration & Connexion BDD
 */

// Empêcher l'accès direct
if (!defined('FRENCHYBOT') && basename($_SERVER['PHP_SELF']) === 'config.php') {
    http_response_code(403);
    exit('Accès interdit');
}

// ============================================
// CONFIGURATION
// ============================================

define('FB_VERSION', '1.0.0');
define('FB_BASE_URL', 'https://bot.frenchycompany.fr');
define('FB_ROOT', dirname(__DIR__));

// BDD
define('DB_HOST', getenv('FB_DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('FB_DB_NAME') ?: 'frenchybot');
define('DB_USER', getenv('FB_DB_USER') ?: 'root');
define('DB_PASS', '**Baycpq25**');
define('DB_CHARSET', 'utf8mb4');

// Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Timezone
date_default_timezone_set('Europe/Paris');

// ============================================
// CONNEXION PDO
// ============================================

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    error_log('FrenchyBot DB Error: ' . $e->getMessage());
    if (php_sapi_name() !== 'cli') {
        http_response_code(500);
        echo json_encode(['error' => 'Erreur serveur']);
    }
    exit;
}
