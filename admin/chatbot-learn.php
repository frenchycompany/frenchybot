<?php
/**
 * FrenchyBot Admin - Apprendre depuis une conversation
 */
define('FRENCHYBOT', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$admin_user = requireAdmin();
$page_title = 'Apprendre';

$chatbot_id = intval($_GET['chatbot_id'] ?? 0);
$conversation_id = intval($_GET['conversation_id'] ?? 0);

if (!$chatbot_id || !$conversation_id) { header('Location: dashboard.php'); exit; }

$chatbot = getChatbotById($chatbot_id);
if (!$chatbot) { header('Location: dashboard.php'); exit; }

// Load conversation
$stmt = $pdo->prepare("SELECT * FROM chatbot_conversations WHERE id = ? AND chatbot_id = ?");
$stmt->execute([$conversation_id, $chatbot_id]);
$conversation = $stmt->fetch();
if (!$conversation) { header('Location: dashboard.php'); exit; }

// Load messages
$stmt = $pdo->prepare("SELECT * FROM chatbot_messages WHERE conversation_id = ? ORDER BY created_at ASC");
$stmt->execute([$conversation_id]);
$messages = $stmt->fetchAll();

// Unrecognized messages
$unrecognized = array_filter($messages, fn($m) => $m['type'] === 'user' && empty($m['intention_detected']));

// Existing intentions for dropdown
$stmt = $pdo->prepare("SELECT id, intention_key, keywords FROM chatbot_intentions WHERE chatbot_id = ? ORDER BY intention_key");
$stmt->execute([$chatbot_id]);
$existing_intentions = $stmt->fetchAll();

// Handle learning
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $msg_text = trim($_POST['message'] ?? '');
    $action_type = $_POST['action_type'] ?? '';

    if ($action_type === 'add_to_existing') {
        $intention_id = intval($_POST['intention_id'] ?? 0);
        $keyword = mb_strtolower(trim($msg_text));
        if ($intention_id && $keyword) {
            $stmt = $pdo->prepare("SELECT keywords FROM chatbot_intentions WHERE id = ? AND chatbot_id = ?");
            $stmt->execute([$intention_id, $chatbot_id]);
            $current = $stmt->fetchColumn();
            if ($current !== false) {
                $keywords = $current . ',' . $keyword;
                $pdo->prepare("UPDATE chatbot_intentions SET keywords = ? WHERE id = ?")->execute([$keywords, $intention_id]);
                // Mark as recognized
                $pdo->prepare("UPDATE chatbot_messages SET intention_detected = 'learned' WHERE conversation_id = ? AND type = 'user' AND message = ?")
                    ->execute([$conversation_id, $msg_text]);
                flash('success', "Mot-cle ajoute a l'intention");
            }
        }
    } elseif ($action_type === 'create_new') {
        $key = strtolower(trim($_POST['new_key'] ?? ''));
        $keywords = trim($_POST['new_keywords'] ?? '');
        $response = trim($_POST['new_response'] ?? '');
        $act = trim($_POST['new_action'] ?? '');
        if ($key && $keywords && $response) {
            $pdo->prepare("INSERT INTO chatbot_intentions (chatbot_id, intention_key, keywords, response_text, action, priority) VALUES (?, ?, ?, ?, ?, 10)")
                ->execute([$chatbot_id, $key, $keywords, $response, $act]);
            $pdo->prepare("UPDATE chatbot_messages SET intention_detected = ? WHERE conversation_id = ? AND type = 'user' AND message = ?")
                ->execute([$key, $conversation_id, $msg_text]);
            flash('success', "Nouvelle intention creee");
        }
    }

    header('Location: chatbot-learn.php?chatbot_id=' . $chatbot_id . '&conversation_id=' . $conversation_id);
    exit;
}

include 'includes/admin-header.php';
?>

<div style="max-width:900px;margin:0 auto;">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;">
        <a href="chatbot-view.php?id=<?= $conversation_id ?>" style="color:#1a5653;text-decoration:none;font-size:20px;">&#8592;</a>
        <h1 style="margin:0;">Apprendre - Conv #<?= $conversation_id ?></h1>
    </div>

    <?php foreach (getFlash() as $f): ?>
        <div style="padding:12px 16px;background:#d1fae5;border-radius:8px;margin-bottom:16px;color:#065f46;"><?= $f['message'] ?></div>
    <?php endforeach; ?>

    <?php if (empty($unrecognized)): ?>
        <div style="background:#fff;border-radius:12px;padding:32px;text-align:center;color:#6b7280;">
            Tous les messages de cette conversation ont ete reconnus.
        </div>
    <?php else: ?>
        <?php foreach ($unrecognized as $msg): ?>
        <div style="background:#fff;border-radius:12px;padding:24px;box-shadow:0 1px 3px rgba(0,0,0,0.06);margin-bottom:16px;">
            <div style="background:#fef3c7;padding:12px 16px;border-radius:8px;margin-bottom:16px;">
                <strong>Message non reconnu :</strong> "<?= e($msg['message']) ?>"
                <span style="color:#6b7280;font-size:12px;margin-left:8px;"><?= dateFR($msg['created_at']) ?></span>
            </div>

            <!-- Option 1: Add to existing -->
            <form method="post" style="margin-bottom:16px;padding:16px;background:#f9fafb;border-radius:8px;">
                <input type="hidden" name="message" value="<?= e($msg['message']) ?>">
                <input type="hidden" name="action_type" value="add_to_existing">
                <div style="font-weight:600;margin-bottom:8px;">Ajouter a une intention existante</div>
                <div style="display:flex;gap:8px;">
                    <select name="intention_id" style="flex:1;padding:8px;border:1px solid #ddd;border-radius:6px;">
                        <?php foreach ($existing_intentions as $ei): ?>
                        <option value="<?= $ei['id'] ?>"><?= e($ei['intention_key']) ?> (<?= e(truncate($ei['keywords'], 40)) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" style="padding:8px 16px;background:#3b82f6;color:#fff;border:none;border-radius:6px;cursor:pointer;">Ajouter</button>
                </div>
            </form>

            <!-- Option 2: Create new -->
            <form method="post" style="padding:16px;background:#f9fafb;border-radius:8px;">
                <input type="hidden" name="message" value="<?= e($msg['message']) ?>">
                <input type="hidden" name="action_type" value="create_new">
                <div style="font-weight:600;margin-bottom:8px;">Ou creer une nouvelle intention</div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px;">
                    <input type="text" name="new_key" placeholder="Cle (ex: financement)" style="padding:8px;border:1px solid #ddd;border-radius:6px;">
                    <input type="text" name="new_keywords" placeholder="Mots-cles (separes par virgule)" value="<?= e(mb_strtolower($msg['message'])) ?>" style="padding:8px;border:1px solid #ddd;border-radius:6px;">
                </div>
                <textarea name="new_response" rows="2" placeholder="Reponse du chatbot..." style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;margin-bottom:8px;box-sizing:border-box;resize:vertical;"></textarea>
                <div style="display:flex;gap:8px;">
                    <input type="text" name="new_action" placeholder="Action (optionnel)" style="flex:1;padding:8px;border:1px solid #ddd;border-radius:6px;">
                    <button type="submit" style="padding:8px 16px;background:#10b981;color:#fff;border:none;border-radius:6px;cursor:pointer;">Creer</button>
                </div>
            </form>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include 'includes/admin-footer.php'; ?>
