<?php
/**
 * FrenchyBot Admin - Apprendre depuis une conversation
 * Permet de corriger chaque echange : mauvaise detection, message non reconnu
 */
define('FRENCHYBOT', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$admin_user = requireAdmin();

$chatbot_id = intval($_GET['chatbot_id'] ?? 0);
$conversation_id = intval($_GET['conversation_id'] ?? 0);

if (!$chatbot_id || !$conversation_id) {
    flash('error', 'Parametres manquants (chatbot_id et conversation_id requis).');
    header('Location: dashboard.php');
    exit;
}

$client_id = getClientChatbotId();
if ($client_id) requireChatbotAccess($chatbot_id);

$chatbot = getChatbotById($chatbot_id);
if (!$chatbot) { header('Location: dashboard.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM chatbot_conversations WHERE id = ? AND chatbot_id = ?");
$stmt->execute([$conversation_id, $chatbot_id]);
$conversation = $stmt->fetch();
if (!$conversation) { header('Location: chatbot-stats.php?chatbot_id=' . $chatbot_id); exit; }

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $message_id = intval($_POST['message_id'] ?? 0);
    $message_text = trim($_POST['message_text'] ?? '');

    if ($action === 'assign_existing') {
        $intention_id = intval($_POST['intention_id'] ?? 0);
        $keywords_to_add = trim($_POST['keywords_to_add'] ?? '');

        if ($intention_id && !empty($keywords_to_add)) {
            $stmt = $pdo->prepare("SELECT id, intention_key, keywords FROM chatbot_intentions WHERE id = ? AND chatbot_id = ?");
            $stmt->execute([$intention_id, $chatbot_id]);
            $intention = $stmt->fetch();

            if ($intention) {
                // Ajouter les mots-cles sans doublons
                $existing = array_map('trim', explode(',', mb_strtolower($intention['keywords'])));
                $new = array_map('trim', explode(',', mb_strtolower($keywords_to_add)));
                $merged = array_unique(array_merge($existing, $new));
                $pdo->prepare("UPDATE chatbot_intentions SET keywords = ?, updated_at = NOW() WHERE id = ?")
                    ->execute([implode(',', $merged), $intention_id]);

                // Marquer le message comme reconnu
                if ($message_id) {
                    $pdo->prepare("UPDATE chatbot_messages SET intention_detected = ? WHERE id = ?")
                        ->execute([$intention['intention_key'], $message_id]);
                }
                flash('success', 'Mots-cles ajoutes a "' . e($intention['intention_key']) . '"');
            }
        }
        header('Location: chatbot-learn.php?chatbot_id=' . $chatbot_id . '&conversation_id=' . $conversation_id);
        exit;
    }

    if ($action === 'correct_intention') {
        $intention_id = intval($_POST['intention_id'] ?? 0);
        $keywords_to_add = trim($_POST['keywords_to_add'] ?? '');

        if ($intention_id) {
            $stmt = $pdo->prepare("SELECT intention_key, keywords FROM chatbot_intentions WHERE id = ? AND chatbot_id = ?");
            $stmt->execute([$intention_id, $chatbot_id]);
            $intention = $stmt->fetch();

            if ($intention) {
                if (!empty($keywords_to_add)) {
                    $existing = array_map('trim', explode(',', mb_strtolower($intention['keywords'])));
                    $new = array_map('trim', explode(',', mb_strtolower($keywords_to_add)));
                    $merged = array_unique(array_merge($existing, $new));
                    $pdo->prepare("UPDATE chatbot_intentions SET keywords = ?, updated_at = NOW() WHERE id = ?")
                        ->execute([implode(',', $merged), $intention_id]);
                }
                if ($message_id) {
                    $pdo->prepare("UPDATE chatbot_messages SET intention_detected = ? WHERE id = ?")
                        ->execute([$intention['intention_key'], $message_id]);
                }
                flash('success', 'Intention corrigee vers "' . e($intention['intention_key']) . '"');
            }
        }
        header('Location: chatbot-learn.php?chatbot_id=' . $chatbot_id . '&conversation_id=' . $conversation_id);
        exit;
    }

    if ($action === 'create_new') {
        $intention_key = strtolower(trim($_POST['intention_key'] ?? ''));
        $keywords = trim($_POST['keywords'] ?? '');
        $response_text = trim($_POST['response_text'] ?? '');
        $intention_action = trim($_POST['intention_action'] ?? '');
        $priority = intval($_POST['priority'] ?? 10);

        if (empty($intention_key) || empty($keywords) || empty($response_text)) {
            flash('error', 'Cle, mots-cles et reponse sont obligatoires.');
        } else {
            $pdo->prepare("INSERT INTO chatbot_intentions (chatbot_id, intention_key, keywords, response_text, action, priority, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)")
                ->execute([$chatbot_id, $intention_key, $keywords, $response_text, $intention_action ?: null, $priority]);

            if ($message_id) {
                $pdo->prepare("UPDATE chatbot_messages SET intention_detected = ? WHERE id = ?")
                    ->execute([$intention_key, $message_id]);
            }
            flash('success', 'Nouvelle intention "' . e($intention_key) . '" creee !');
        }
        header('Location: chatbot-learn.php?chatbot_id=' . $chatbot_id . '&conversation_id=' . $conversation_id);
        exit;
    }
}

// Load messages
$stmt = $pdo->prepare("SELECT * FROM chatbot_messages WHERE conversation_id = ? ORDER BY created_at ASC");
$stmt->execute([$conversation_id]);
$messages = $stmt->fetchAll();

// Load intentions
$stmt = $pdo->prepare("SELECT id, intention_key, keywords, response_text FROM chatbot_intentions WHERE chatbot_id = ? AND is_active = 1 ORDER BY intention_key ASC");
$stmt->execute([$chatbot_id]);
$intentions = $stmt->fetchAll();

// Stats
$user_msgs = array_filter($messages, fn($m) => $m['type'] === 'user');
$unrecognized = array_filter($user_msgs, fn($m) => empty($m['intention_detected']));
$wrong_detected = array_filter($user_msgs, fn($m) => !empty($m['intention_detected']));

$page_title = 'Apprendre - Conv #' . $conversation_id;
include __DIR__ . '/includes/admin-header.php';
?>

<style>
.exchange { border:1px solid #eee; border-radius:12px; padding:20px; margin-bottom:16px; transition: border-color 0.2s; }
.exchange:hover { border-color: var(--color-primary); }
.exchange.has-problem { border-left: 4px solid #f59e0b; }
.exchange.no-problem { border-left: 4px solid #10b981; }
.msg-bubble { padding:10px 16px; border-radius:12px; display:inline-block; max-width:85%; font-size:14px; line-height:1.5; }
.msg-user { background:var(--color-primary); color:#fff; border-bottom-right-radius:4px; }
.msg-bot { background:#f1f5f9; color:#333; border-bottom-left-radius:4px; }
.correction-panel { background:#fafbfc; border-radius:8px; padding:16px; margin-top:12px; display:none; }
.correction-panel.active { display:block; }
.toggle-correct { cursor:pointer; font-size:12px; padding:4px 10px; border-radius:6px; border:1px solid #ddd; background:#fff; color:#666; transition:all .15s; }
.toggle-correct:hover { border-color:var(--color-primary); color:var(--color-primary); }
</style>

<div class="admin-content">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
        <div>
            <h1 class="admin-title" style="margin-bottom:4px;">Apprendre de cette conversation</h1>
            <div style="font-size:13px;color:var(--color-gray);">
                <?= e($chatbot['name']) ?> — Conv #<?= $conversation_id ?>
                — <?= dateFR($conversation['started_at']) ?>
                — <?= count($user_msgs) ?> messages visiteur
                (<?= count($unrecognized) ?> non reconnus)
            </div>
        </div>
        <div style="display:flex;gap:8px;">
            <a href="chatbot-view.php?id=<?= $conversation_id ?>" class="btn btn-outline btn-sm">Transcript</a>
            <a href="chatbot-stats.php?chatbot_id=<?= $chatbot_id ?>" class="btn btn-outline btn-sm">&larr; Retour</a>
        </div>
    </div>

    <?php foreach (getFlash() as $f): ?>
    <div class="alert alert-<?= $f['type'] === 'error' ? 'error' : 'success' ?>"><?= $f['message'] ?></div>
    <?php endforeach; ?>

    <!-- Chaque echange : message visiteur + reponse bot -->
    <?php
    $pairs = [];
    $currentPair = null;
    foreach ($messages as $msg) {
        if ($msg['type'] === 'user') {
            if ($currentPair) $pairs[] = $currentPair;
            $currentPair = ['user' => $msg, 'bot' => null];
        } elseif ($msg['type'] === 'bot' && $currentPair) {
            $currentPair['bot'] = $msg;
            $pairs[] = $currentPair;
            $currentPair = null;
        } elseif ($msg['type'] === 'bot' && !$currentPair) {
            // Bot message without user (welcome, etc.) — skip
        }
    }
    if ($currentPair) $pairs[] = $currentPair;
    ?>

    <?php foreach ($pairs as $i => $pair):
        $userMsg = $pair['user'];
        $botMsg = $pair['bot'];
        $detected = $userMsg['intention_detected'] ?? '';
        $isUnrecognized = empty($detected);
        $isNavigation = in_array($detected, ['navigation', 'scenario_step_10', 'scenario_step_11', 'scenario_step_12']);
        $hasProblem = $isUnrecognized && !$isNavigation;
        $panelId = 'panel-' . $userMsg['id'];
    ?>
    <div class="exchange <?= $hasProblem ? 'has-problem' : 'no-problem' ?>">
        <!-- Message visiteur -->
        <div style="display:flex;justify-content:flex-end;margin-bottom:8px;">
            <div>
                <div class="msg-bubble msg-user"><?= e($userMsg['message']) ?></div>
                <div style="text-align:right;margin-top:4px;font-size:11px;color:var(--color-gray);">
                    <?= dateFR($userMsg['created_at']) ?>
                    <?php if ($detected): ?>
                        — detecte : <strong style="color:<?= $isNavigation ? '#6b7280' : 'var(--color-primary)' ?>;"><?= e($detected) ?></strong>
                    <?php else: ?>
                        — <span style="color:#f59e0b;font-weight:600;">non reconnu</span>
                    <?php endif; ?>
                    <button class="toggle-correct" onclick="togglePanel('<?= $panelId ?>')">
                        <?= $hasProblem ? 'Enseigner' : 'Corriger' ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Reponse bot -->
        <?php if ($botMsg): ?>
        <div style="display:flex;justify-content:flex-start;">
            <div>
                <div class="msg-bubble msg-bot"><?= e(truncate($botMsg['message'], 200)) ?></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Panel de correction -->
        <div id="<?= $panelId ?>" class="correction-panel <?= $hasProblem ? 'active' : '' ?>">
            <?php if ($detected && !$isNavigation): ?>
            <div style="background:#fef3c7;padding:8px 12px;border-radius:6px;margin-bottom:12px;font-size:13px;">
                Detection actuelle : <strong><?= e($detected) ?></strong>
                — si c'est incorrect, corrigez ci-dessous.
            </div>
            <?php endif; ?>

            <!-- Assigner a une intention existante -->
            <form method="post" style="margin-bottom:12px;">
                <input type="hidden" name="action" value="<?= $detected ? 'correct_intention' : 'assign_existing' ?>">
                <input type="hidden" name="message_id" value="<?= $userMsg['id'] ?>">
                <input type="hidden" name="message_text" value="<?= e($userMsg['message']) ?>">

                <div style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
                    <div style="flex:1;min-width:200px;">
                        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">
                            <?= $detected ? 'Corriger vers :' : 'Assigner a :' ?>
                        </label>
                        <select name="intention_id" class="form-control" style="font-size:13px;" required>
                            <option value="">-- Choisir une intention --</option>
                            <?php foreach ($intentions as $int): ?>
                            <option value="<?= $int['id'] ?>" title="<?= e($int['response_text']) ?>">
                                <?= e($int['intention_key']) ?> (<?= e(truncate($int['keywords'], 40)) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="flex:1;min-width:200px;">
                        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Mots-cles a ajouter</label>
                        <input type="text" name="keywords_to_add" class="form-control" style="font-size:13px;"
                               value="<?= e(mb_strtolower(trim($userMsg['message']))) ?>"
                               placeholder="financement,pret,courtier">
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary"><?= $detected ? 'Corriger' : 'Assigner' ?></button>
                </div>
            </form>

            <!-- Ou creer une nouvelle intention -->
            <details style="margin-top:8px;">
                <summary style="cursor:pointer;font-size:13px;font-weight:600;color:var(--color-primary);">
                    Ou creer une nouvelle intention
                </summary>
                <form method="post" style="margin-top:12px;">
                    <input type="hidden" name="action" value="create_new">
                    <input type="hidden" name="message_id" value="<?= $userMsg['id'] ?>">
                    <input type="hidden" name="message_text" value="<?= e($userMsg['message']) ?>">

                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:8px;">
                        <div>
                            <label style="font-size:12px;font-weight:600;">Cle *</label>
                            <input type="text" name="intention_key" class="form-control" style="font-size:13px;"
                                   placeholder="financement" required>
                        </div>
                        <div>
                            <label style="font-size:12px;font-weight:600;">Mots-cles *</label>
                            <input type="text" name="keywords" class="form-control" style="font-size:13px;"
                                   value="<?= e(mb_strtolower(trim($userMsg['message']))) ?>"
                                   placeholder="financement,pret,credit" required>
                        </div>
                        <div>
                            <label style="font-size:12px;font-weight:600;">Action</label>
                            <select name="intention_action" class="form-control" style="font-size:13px;">
                                <option value="">Aucune (reponse texte)</option>
                                <option value="collect_info">Collecter des infos</option>
                                <option value="create_lead">Creer un lead</option>
                                <option value="create_lead_priority">Lead prioritaire</option>
                                <option value="scenario_terrain">Scenario terrain</option>
                                <option value="scenario_devis">Scenario devis</option>
                                <option value="afficher_modeles">Afficher modeles</option>
                                <option value="transfert_humain">Transfert humain</option>
                                <option value="close">Fermer</option>
                            </select>
                        </div>
                    </div>
                    <div style="margin-bottom:8px;">
                        <label style="font-size:12px;font-weight:600;">Reponse du bot *</label>
                        <textarea name="response_text" class="form-control" style="font-size:13px;" rows="2"
                                  placeholder="La reponse que le bot donnera quand il detecte cette intention..." required></textarea>
                    </div>
                    <input type="hidden" name="priority" value="10">
                    <button type="submit" class="btn btn-sm btn-primary">Creer l'intention</button>
                </form>
            </details>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if (empty($pairs)): ?>
    <div class="admin-section" style="text-align:center;padding:40px;color:var(--color-gray);">
        Aucun message visiteur dans cette conversation.
    </div>
    <?php endif; ?>
</div>

<script>
function togglePanel(id) {
    var panel = document.getElementById(id);
    if (panel) panel.classList.toggle('active');
}
</script>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
