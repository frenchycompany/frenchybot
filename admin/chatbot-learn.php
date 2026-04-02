<?php
/**
 * FrenchyBot Admin - Apprendre depuis une conversation
 */
define('FRENCHYBOT', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$chatbot_id = intval($_GET['chatbot_id'] ?? 0);
$conversation_id = intval($_GET['conversation_id'] ?? 0);

if (!$chatbot_id || !$conversation_id) {
    flash('error', 'Parametres manquants (chatbot_id et conversation_id requis).');
    header('Location: dashboard.php');
    exit;
}

$chatbot = getChatbotById($chatbot_id);
if (!$chatbot) {
    flash('error', 'Chatbot introuvable.');
    header('Location: dashboard.php');
    exit;
}

// Load conversation
$stmt = $pdo->prepare("SELECT * FROM chatbot_conversations WHERE id = ? AND chatbot_id = ?");
$stmt->execute([$conversation_id, $chatbot_id]);
$conversation = $stmt->fetch();

if (!$conversation) {
    flash('error', 'Conversation introuvable pour ce chatbot.');
    header('Location: chatbot-stats.php?chatbot_id=' . $chatbot_id);
    exit;
}

// Handle POST: assign to existing intention or create new
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $message_text = trim($_POST['message_text'] ?? '');

    if ($action === 'assign_existing') {
        $intention_id = intval($_POST['intention_id'] ?? 0);
        $keywords_to_add = trim($_POST['keywords_to_add'] ?? '');

        if ($intention_id && !empty($keywords_to_add)) {
            // Append keywords to the existing intention
            $stmt = $pdo->prepare("SELECT keywords FROM chatbot_intentions WHERE id = ? AND chatbot_id = ?");
            $stmt->execute([$intention_id, $chatbot_id]);
            $intention = $stmt->fetch();

            if ($intention) {
                $current_keywords = $intention['keywords'];
                $new_keywords = $current_keywords . ', ' . $keywords_to_add;
                $stmt = $pdo->prepare("UPDATE chatbot_intentions SET keywords = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$new_keywords, $intention_id]);
                flash('success', 'Mots-cles ajoutes a l\'intention existante.');
            }
        } else {
            flash('error', 'Veuillez selectionner une intention et fournir des mots-cles.');
        }
        header('Location: chatbot-learn.php?chatbot_id=' . $chatbot_id . '&conversation_id=' . $conversation_id);
        exit;
    }

    if ($action === 'create_new') {
        $intention_key = trim($_POST['intention_key'] ?? '');
        $keywords = trim($_POST['keywords'] ?? '');
        $response_text = trim($_POST['response_text'] ?? '');
        $intention_action = trim($_POST['intention_action'] ?? '');

        if (empty($intention_key) || empty($keywords) || empty($response_text)) {
            flash('error', 'Les champs cle, mots-cles et reponse sont obligatoires.');
        } else {
            $stmt = $pdo->prepare("INSERT INTO chatbot_intentions (chatbot_id, intention_key, keywords, response_text, action, priority, is_active)
                                   VALUES (?, ?, ?, ?, ?, 10, 1)");
            $stmt->execute([$chatbot_id, $intention_key, $keywords, $response_text, $intention_action ?: null]);
            flash('success', 'Nouvelle intention "' . e($intention_key) . '" creee avec succes.');
        }
        header('Location: chatbot-learn.php?chatbot_id=' . $chatbot_id . '&conversation_id=' . $conversation_id);
        exit;
    }
}

// Load all messages
$stmt = $pdo->prepare("SELECT * FROM chatbot_messages WHERE conversation_id = ? ORDER BY created_at ASC");
$stmt->execute([$conversation_id]);
$all_messages = $stmt->fetchAll();

// Filter unrecognized user messages
$unrecognized = array_filter($all_messages, function($msg) {
    return $msg['type'] === 'user' && empty($msg['intention_detected']);
});

// Load existing intentions for this chatbot
$stmt = $pdo->prepare("SELECT id, intention_key, keywords FROM chatbot_intentions WHERE chatbot_id = ? AND is_active = 1 ORDER BY intention_key ASC");
$stmt->execute([$chatbot_id]);
$intentions = $stmt->fetchAll();

$page_title = 'Apprendre - Conversation #' . $conversation_id;

include __DIR__ . '/includes/admin-header.php';
?>

<div class="admin-content">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
        <h1 class="admin-title" style="margin-bottom:0;">Apprendre : <?= e($chatbot['name']) ?></h1>
        <div style="display:flex;gap:8px;">
            <a href="chatbot-view.php?id=<?= $conversation_id ?>" class="btn btn-outline">Voir le transcript</a>
            <a href="chatbot-intentions.php?chatbot_id=<?= $chatbot_id ?>" class="btn btn-outline">Intentions</a>
            <a href="chatbot-stats.php?chatbot_id=<?= $chatbot_id ?>" class="btn btn-outline">&larr; Retour</a>
        </div>
    </div>

    <!-- Conversation context -->
    <div class="admin-section">
        <h2 style="font-size:16px;margin-bottom:12px;">Conversation #<?= $conversation_id ?></h2>
        <div style="font-size:13px;color:var(--color-gray);">
            Debut : <?= dateFR($conversation['started_at']) ?>
            | Messages : <?= count($all_messages) ?>
            | Score : <?= numberFR($conversation['completion_score'], 1) ?>%
        </div>

        <!-- Mini transcript -->
        <div style="max-height:300px;overflow-y:auto;margin-top:12px;padding:12px;background:#f8f9fa;border-radius:8px;">
            <?php foreach ($all_messages as $msg): ?>
            <div style="margin-bottom:8px;font-size:13px;">
                <strong style="color:<?= $msg['type'] === 'user' ? 'var(--color-primary)' : '#666' ?>;">
                    <?= $msg['type'] === 'user' ? 'Visiteur' : 'Bot' ?> :
                </strong>
                <span><?= e(truncate($msg['message'], 150)) ?></span>
                <?php if ($msg['intention_detected']): ?>
                <span class="badge badge-info" style="font-size:10px;"><?= e($msg['intention_detected']) ?></span>
                <?php elseif ($msg['type'] === 'user'): ?>
                <span class="badge badge-warning" style="font-size:10px;">Non reconnu</span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Unrecognized messages -->
    <div class="admin-section">
        <h2 style="font-size:18px;margin-bottom:16px;">
            Messages non reconnus
            <span class="badge badge-warning" style="margin-left:8px;"><?= count($unrecognized) ?></span>
        </h2>

        <?php if (empty($unrecognized)): ?>
        <div style="text-align:center;padding:40px;color:var(--color-gray);">
            <p>Tous les messages de cette conversation ont ete reconnus.</p>
        </div>
        <?php else: ?>
        <?php foreach ($unrecognized as $msg): ?>
        <div style="border:1px solid #eee;border-radius:12px;padding:20px;margin-bottom:20px;">
            <div style="margin-bottom:16px;">
                <div style="font-size:12px;color:var(--color-gray);margin-bottom:4px;"><?= dateFR($msg['created_at']) ?></div>
                <div style="background:var(--color-primary);color:#fff;padding:10px 16px;border-radius:12px;border-bottom-right-radius:4px;display:inline-block;font-size:14px;max-width:80%;">
                    <?= e($msg['message']) ?>
                </div>
            </div>

            <!-- Option 1: Assign to existing intention -->
            <div style="background:#f8f9fa;border-radius:8px;padding:16px;margin-bottom:12px;">
                <h4 style="font-size:14px;margin-bottom:12px;">Assigner a une intention existante</h4>
                <form method="POST">
                    <input type="hidden" name="action" value="assign_existing">
                    <input type="hidden" name="message_text" value="<?= e($msg['message']) ?>">

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label style="font-size:12px;">Intention</label>
                            <select name="intention_id" class="form-control" required>
                                <option value="">-- Choisir --</option>
                                <?php foreach ($intentions as $int): ?>
                                <option value="<?= $int['id'] ?>"><?= e($int['intention_key']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label style="font-size:12px;">Mots-cles a ajouter</label>
                            <input type="text" name="keywords_to_add" class="form-control"
                                   placeholder="mot1, mot2, phrase..."
                                   value="<?= e(mb_strtolower($msg['message'])) ?>">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary" style="margin-top:12px;">Ajouter a l'intention</button>
                </form>
            </div>

            <!-- Option 2: Create new intention -->
            <div style="background:#f0f8f0;border-radius:8px;padding:16px;">
                <h4 style="font-size:14px;margin-bottom:12px;">Creer une nouvelle intention</h4>
                <form method="POST">
                    <input type="hidden" name="action" value="create_new">
                    <input type="hidden" name="message_text" value="<?= e($msg['message']) ?>">

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label style="font-size:12px;">Cle d'intention *</label>
                            <input type="text" name="intention_key" class="form-control" required
                                   placeholder="Ex: demande_prix, info_livraison...">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label style="font-size:12px;">Mots-cles *</label>
                            <input type="text" name="keywords" class="form-control" required
                                   placeholder="mot1, mot2, phrase..."
                                   value="<?= e(mb_strtolower($msg['message'])) ?>">
                        </div>
                    </div>
                    <div class="form-group" style="margin-top:12px;margin-bottom:0;">
                        <label style="font-size:12px;">Reponse du bot *</label>
                        <textarea name="response_text" class="form-control" rows="2" required
                                  placeholder="La reponse que le bot donnera..."></textarea>
                    </div>
                    <div class="form-group" style="margin-top:12px;margin-bottom:0;">
                        <label style="font-size:12px;">Action (optionnel)</label>
                        <input type="text" name="intention_action" class="form-control"
                               placeholder="Ex: link_page, show_form...">
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary" style="margin-top:12px;">Creer l'intention</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
