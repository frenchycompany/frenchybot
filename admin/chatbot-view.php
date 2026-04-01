<?php
/**
 * FrenchyBot Admin - Transcript d'une conversation
 */
define('FRENCHYBOT', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$conversation_id = intval($_GET['id'] ?? 0);
if (!$conversation_id) {
    flash('error', 'Conversation introuvable.');
    header('Location: dashboard.php');
    exit;
}

// Load conversation with chatbot info
$stmt = $pdo->prepare("
    SELECT cc.*, c.name as chatbot_name, c.id as chatbot_id
    FROM chatbot_conversations cc
    JOIN chatbots c ON cc.chatbot_id = c.id
    WHERE cc.id = ?
");
$stmt->execute([$conversation_id]);
$conversation = $stmt->fetch();

if (!$conversation) {
    flash('error', 'Conversation introuvable.');
    header('Location: dashboard.php');
    exit;
}

// Load messages
$stmt = $pdo->prepare("SELECT * FROM chatbot_messages WHERE conversation_id = ? ORDER BY created_at ASC");
$stmt->execute([$conversation_id]);
$messages = $stmt->fetchAll();

// Parse collected data
$data_collected = null;
if (!empty($conversation['data_collected'])) {
    $data_collected = is_string($conversation['data_collected'])
        ? json_decode($conversation['data_collected'], true)
        : $conversation['data_collected'];
}

$page_title = 'Conversation #' . $conversation_id;

include __DIR__ . '/includes/admin-header.php';
?>

<style>
    .chat-container {
        max-width: 700px;
        margin: 0 auto;
        padding: 16px 0;
    }
    .chat-bubble {
        max-width: 80%;
        padding: 12px 16px;
        border-radius: 16px;
        font-size: 14px;
        line-height: 1.6;
        margin-bottom: 4px;
        word-wrap: break-word;
    }
    .chat-bubble-bot {
        background: #f0f0f0;
        color: #333;
        border-bottom-left-radius: 4px;
        align-self: flex-start;
    }
    .chat-bubble-user {
        background: var(--color-primary);
        color: #fff;
        border-bottom-right-radius: 4px;
        align-self: flex-end;
    }
    .chat-bubble-system {
        background: #fef3c7;
        color: #92400e;
        border-bottom-left-radius: 4px;
        align-self: flex-start;
        font-style: italic;
    }
    .chat-row {
        display: flex;
        flex-direction: column;
        margin-bottom: 16px;
    }
    .chat-row-user {
        align-items: flex-end;
    }
    .chat-row-bot, .chat-row-system {
        align-items: flex-start;
    }
    .chat-meta {
        font-size: 11px;
        color: var(--color-gray);
        margin-top: 3px;
        padding: 0 4px;
    }
</style>

<div class="admin-content">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
        <h1 class="admin-title" style="margin-bottom:0;">
            Conversation #<?= $conversation_id ?>
            <?php if ($conversation['is_active']): ?>
            <span class="badge badge-success" style="vertical-align:middle;margin-left:8px;">Active</span>
            <?php else: ?>
            <span class="badge badge-error" style="vertical-align:middle;margin-left:8px;">Terminee</span>
            <?php endif; ?>
        </h1>
        <div style="display:flex;gap:8px;">
            <?php if ($conversation['lead_id']): ?>
            <a href="lead-view.php?id=<?= $conversation['lead_id'] ?>" class="btn btn-outline">Voir le lead</a>
            <?php endif; ?>
            <a href="chatbot-learn.php?chatbot_id=<?= $conversation['chatbot_id'] ?>&conversation_id=<?= $conversation_id ?>" class="btn btn-outline">Apprendre</a>
            <a href="chatbot-stats.php?chatbot_id=<?= $conversation['chatbot_id'] ?>" class="btn btn-outline">&larr; Retour</a>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 300px;gap:24px;">
        <!-- Messages -->
        <div class="admin-section">
            <h2 style="font-size:16px;margin-bottom:16px;">Transcript (<?= count($messages) ?> messages)</h2>

            <?php if (empty($messages)): ?>
            <div style="text-align:center;padding:40px;color:var(--color-gray);">
                <p>Aucun message dans cette conversation.</p>
            </div>
            <?php else: ?>
            <div class="chat-container">
                <?php foreach ($messages as $msg): ?>
                <div class="chat-row chat-row-<?= e($msg['type']) ?>">
                    <div class="chat-bubble chat-bubble-<?= e($msg['type']) ?>">
                        <?= nl2br(e($msg['message'])) ?>
                    </div>
                    <div class="chat-meta">
                        <?= $msg['type'] === 'user' ? 'Visiteur' : ($msg['type'] === 'system' ? 'Systeme' : 'Bot') ?>
                        - <?= dateFR($msg['created_at']) ?>
                        <?php if ($msg['intention_detected']): ?>
                        <span class="badge badge-info" style="font-size:10px;margin-left:4px;"><?= e($msg['intention_detected']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Metadata sidebar -->
        <div>
            <div class="admin-section">
                <h2 style="font-size:16px;margin-bottom:16px;">Metadata</h2>

                <div style="font-size:13px;line-height:2.2;">
                    <div>
                        <label style="font-size:12px;color:var(--color-gray);display:block;">Chatbot</label>
                        <a href="chatbot-edit.php?id=<?= $conversation['chatbot_id'] ?>"><?= e($conversation['chatbot_name']) ?></a>
                    </div>
                    <div>
                        <label style="font-size:12px;color:var(--color-gray);display:block;">Debut</label>
                        <span><?= dateFR($conversation['started_at']) ?></span>
                    </div>
                    <div>
                        <label style="font-size:12px;color:var(--color-gray);display:block;">Derniere activite</label>
                        <span><?= dateFR($conversation['last_activity']) ?></span>
                    </div>
                    <?php if ($conversation['ended_at']): ?>
                    <div>
                        <label style="font-size:12px;color:var(--color-gray);display:block;">Fin</label>
                        <span><?= dateFR($conversation['ended_at']) ?></span>
                    </div>
                    <?php endif; ?>
                    <div>
                        <label style="font-size:12px;color:var(--color-gray);display:block;">Score de completion</label>
                        <span style="font-weight:600;color:var(--color-primary);"><?= numberFR($conversation['completion_score'], 1) ?>%</span>
                    </div>
                    <div>
                        <label style="font-size:12px;color:var(--color-gray);display:block;">Scenario</label>
                        <span><?= e($conversation['scenario_id'] ?? '-') ?></span>
                    </div>
                    <div>
                        <label style="font-size:12px;color:var(--color-gray);display:block;">Etape actuelle</label>
                        <span><?= intval($conversation['current_step']) ?></span>
                    </div>
                    <div>
                        <label style="font-size:12px;color:var(--color-gray);display:block;">Page source</label>
                        <span style="word-break:break-all;"><?= e($conversation['page_source'] ?? '-') ?></span>
                    </div>
                    <div>
                        <label style="font-size:12px;color:var(--color-gray);display:block;">Adresse IP</label>
                        <span><?= e($conversation['ip_address'] ?? '-') ?></span>
                    </div>
                    <?php if ($conversation['ab_test_id']): ?>
                    <div>
                        <label style="font-size:12px;color:var(--color-gray);display:block;">A/B Test</label>
                        <span>Test #<?= intval($conversation['ab_test_id']) ?> - Variante <?= e($conversation['ab_variant'] ?? '-') ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($conversation['lead_id']): ?>
                    <div>
                        <label style="font-size:12px;color:var(--color-gray);display:block;">Lead</label>
                        <a href="lead-view.php?id=<?= $conversation['lead_id'] ?>">Lead #<?= $conversation['lead_id'] ?></a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($data_collected && is_array($data_collected)): ?>
            <div class="admin-section">
                <h2 style="font-size:16px;margin-bottom:16px;">Donnees collectees</h2>
                <div style="font-size:13px;">
                    <?php foreach ($data_collected as $key => $value): ?>
                    <div style="margin-bottom:8px;">
                        <label style="font-size:12px;color:var(--color-gray);display:block;"><?= e($key) ?></label>
                        <span><?= is_array($value) ? e(json_encode($value, JSON_UNESCAPED_UNICODE)) : e((string)$value) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
