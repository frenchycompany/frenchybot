<?php
/**
 * FrenchyBot - Authentification tokens + CORS
 */

require_once __DIR__ . '/config.php';

// ============================================
// FONCTIONS TOKEN / CHATBOT
// ============================================

/**
 * Récupérer un chatbot par son token public
 */
function getChatbotByToken(string $token): ?array {
    global $pdo;
    if (empty($token)) return null;

    $stmt = $pdo->prepare("SELECT * FROM chatbots WHERE token = ? AND is_active = 1");
    $stmt->execute([$token]);
    $chatbot = $stmt->fetch();

    return $chatbot ?: null;
}

/**
 * Récupérer un chatbot par son ID
 */
function getChatbotById(int $id): ?array {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM chatbots WHERE id = ?");
    $stmt->execute([$id]);
    $chatbot = $stmt->fetch();
    return $chatbot ?: null;
}

/**
 * Vérifier le secret_key pour l'API admin
 */
function verifySecretKey(string $token, string $secretKey): ?array {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM chatbots WHERE token = ? AND secret_key = ? AND is_active = 1");
    $stmt->execute([$token, $secretKey]);
    $chatbot = $stmt->fetch();
    return $chatbot ?: null;
}

// ============================================
// CORS
// ============================================

/**
 * Vérifier et appliquer les headers CORS pour un chatbot
 */
function handleCORS(?array $chatbot = null): void {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    // Preflight
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        if ($chatbot && !empty($chatbot['domain'])) {
            if (domainMatches($origin, $chatbot['domain'])) {
                header("Access-Control-Allow-Origin: $origin");
            }
        } else {
            header("Access-Control-Allow-Origin: *");
        }
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
        header('Access-Control-Max-Age: 86400');
        http_response_code(204);
        exit;
    }

    // Requête normale
    if ($chatbot && !empty($chatbot['domain'])) {
        if (!domainMatches($origin, $chatbot['domain'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Origine non autorisee']);
            exit;
        }
        header("Access-Control-Allow-Origin: $origin");
    } else {
        header("Access-Control-Allow-Origin: *");
    }

    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
}

/**
 * Vérifier si l'origine correspond au domaine configuré
 */
function domainMatches(string $origin, string $domain): bool {
    if (empty($origin) || empty($domain)) return true;
    $originHost = parse_url($origin, PHP_URL_HOST) ?: '';
    // Match exact ou sous-domaine
    return $originHost === $domain || str_ends_with($originHost, '.' . $domain);
}

// ============================================
// RATE LIMITING (simple, par IP + token)
// ============================================

function checkRateLimit(string $token, int $maxPerMinute = 30): bool {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = "rl_" . md5($ip . '_' . $token);
    $file = sys_get_temp_dir() . '/' . $key;

    $now = time();
    $data = [];

    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true) ?: [];
        // Nettoyer les entrées > 60s
        $data = array_filter($data, fn($t) => ($now - $t) < 60);
    }

    if (count($data) >= $maxPerMinute) {
        return false;
    }

    $data[] = $now;
    file_put_contents($file, json_encode($data));
    return true;
}

// ============================================
// AUTHENTIFICATION API : extraire et valider le token
// ============================================

/**
 * Authentifier une requête API via le token
 * Retourne le chatbot ou envoie une erreur 401/403
 */
function authenticateRequest(): array {
    $token = $_POST['token'] ?? $_GET['token'] ?? '';

    if (empty($token)) {
        http_response_code(401);
        echo json_encode(['error' => 'Token requis']);
        exit;
    }

    $chatbot = getChatbotByToken($token);
    if (!$chatbot) {
        http_response_code(401);
        echo json_encode(['error' => 'Token invalide']);
        exit;
    }

    // CORS
    handleCORS($chatbot);

    // Rate limiting
    if (!checkRateLimit($token)) {
        http_response_code(429);
        echo json_encode(['error' => 'Trop de requetes']);
        exit;
    }

    return $chatbot;
}

// ============================================
// AUTH ADMIN (session)
// ============================================

function adminLogin(string $username, string $password): ?array {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ? AND is_active = 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return null;
    }

    // Mettre à jour last_login
    $pdo->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);

    $_SESSION['admin_user'] = [
        'id' => $user['id'],
        'username' => $user['username'],
        'role' => $user['role'],
        'chatbot_id' => $user['chatbot_id'] ?? null,
    ];

    return $user;
}

function requireAdmin(): array {
    if (empty($_SESSION['admin_user'])) {
        header('Location: index.php');
        exit;
    }
    return $_SESSION['admin_user'];
}

/**
 * Verifier si l'utilisateur est admin (voit tout)
 */
function isAdmin(): bool {
    return ($_SESSION['admin_user']['role'] ?? '') === 'admin';
}

/**
 * Retourne le chatbot_id du client, ou null si admin
 */
function getClientChatbotId(): ?int {
    if (isAdmin()) return null;
    return $_SESSION['admin_user']['chatbot_id'] ?? null;
}

/**
 * Resoudre le chatbot_id a utiliser dans les pages admin :
 * - Client : force son chatbot_id
 * - Admin : utilise le parametre GET/POST ou le premier chatbot dispo
 */
function resolveAdminChatbotId(array $chatbots_list = []): int {
    $client_id = getClientChatbotId();
    if ($client_id) return $client_id;

    $id = intval($_GET['chatbot_id'] ?? $_POST['chatbot_id'] ?? 0);
    if ($id) return $id;

    return $chatbots_list[0]['id'] ?? 0;
}

/**
 * Filtre SQL pour limiter aux chatbots du client
 * Retourne '' pour admin, 'AND chatbot_id = ?' pour client
 */
function chatbotFilter(string $alias = ''): string {
    if (isAdmin()) return '';
    $col = $alias ? "$alias.chatbot_id" : 'chatbot_id';
    return " AND $col = " . intval(getClientChatbotId());
}

/**
 * Verifier qu'un client a acces a un chatbot_id donne
 */
function requireChatbotAccess(int $chatbot_id): void {
    $client_id = getClientChatbotId();
    if ($client_id && $client_id !== $chatbot_id) {
        http_response_code(403);
        exit('Acces interdit');
    }
}

function adminLogout(): void {
    unset($_SESSION['admin_user']);
    session_destroy();
}

/**
 * Générer un token aléatoire
 */
function generateToken(int $length = 32): string {
    return bin2hex(random_bytes($length));
}
