<?php
/**
 * FrenchyBot - Authentification tokens + CORS
 */

/**
 * Recuperer un chatbot par son token public
 */
function getChatbotByToken(string $token): ?array {
    global $pdo;
    if (empty($token)) return null;

    $stmt = $pdo->prepare("SELECT * FROM chatbots WHERE token = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$token]);
    $chatbot = $stmt->fetch();

    return $chatbot ?: null;
}

/**
 * Recuperer un chatbot par son ID
 */
function getChatbotById(int $id): ?array {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM chatbots WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $chatbot = $stmt->fetch();

    return $chatbot ?: null;
}

/**
 * Verifier le token API et retourner le chatbot
 * Arrete l'execution si le token est invalide
 */
function requireValidToken(): array {
    $token = $_POST['token'] ?? $_GET['token'] ?? '';
    $chatbot = getChatbotByToken($token);

    if (!$chatbot) {
        http_response_code(401);
        echo json_encode(['error' => 'Token invalide']);
        exit;
    }

    return $chatbot;
}

/**
 * Verifier CORS : l'origin doit correspondre au domaine du chatbot
 */
function checkCORS(array $chatbot): void {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    // Si pas de domaine configure, on accepte tout (dev)
    if (empty($chatbot['domain'])) {
        if ($origin) {
            header('Access-Control-Allow-Origin: ' . $origin);
        }
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        return;
    }

    // Verifier que l'origin correspond au domaine
    if ($origin && strpos($origin, $chatbot['domain']) !== false) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        return;
    }

    // Pas d'origin (appel direct) -> on laisse passer
    if (empty($origin)) {
        return;
    }

    // Origin ne correspond pas au domaine
    http_response_code(403);
    echo json_encode(['error' => 'Origine non autorisee']);
    exit;
}

/**
 * Gerer les requetes OPTIONS (preflight CORS)
 */
function handlePreflight(array $chatbot): void {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        checkCORS($chatbot);
        http_response_code(204);
        exit;
    }
}

/**
 * Generer un token aleatoire
 */
function generateToken(int $length = 32): string {
    return bin2hex(random_bytes($length));
}

/**
 * Generer un token public pour un chatbot (prefixe pub_)
 */
function generatePublicToken(): string {
    return 'pub_' . generateToken(28);
}

/**
 * Generer une cle secrete pour un chatbot (prefixe sec_)
 */
function generateSecretKey(): string {
    return 'sec_' . generateToken(28);
}

/**
 * Verifier la session admin
 */
function requireAdminLogin(): void {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Location: index.php');
        exit;
    }
}

/**
 * Verifier le mot de passe admin
 */
function verifyAdminLogin(string $username, string $password): ?array {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password_hash'])) {
        // Mettre a jour last_login
        $pdo->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?")->execute([$admin['id']]);
        return $admin;
    }

    return null;
}
