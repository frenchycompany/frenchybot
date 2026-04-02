<?php
/**
 * FrenchyBot Admin - Dashboard
 * Liste de tous les chatbots avec stats
 */
define('FRENCHYBOT', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$admin_user = requireAdmin();
$page_title = 'Dashboard';

// Filtre client
$client_filter = '';
$client_filter_leads = '';
$client_filter_conv = '';
$client_id = getClientChatbotId();
if ($client_id) {
    $client_filter = " WHERE c.id = $client_id";
    $client_filter_leads = " WHERE chatbot_id = $client_id";
    $client_filter_conv = " WHERE chatbot_id = $client_id";
}

// Charger les chatbots avec stats
$chatbots = $pdo->query("
    SELECT c.*,
        (SELECT COUNT(*) FROM leads l WHERE l.chatbot_id = c.id) as leads_count,
        (SELECT COUNT(*) FROM chatbot_conversations cc WHERE cc.chatbot_id = c.id AND cc.is_active = 1 AND cc.last_activity > DATE_SUB(NOW(), INTERVAL 30 MINUTE)) as active_conversations,
        (SELECT COUNT(*) FROM chatbot_conversations cc WHERE cc.chatbot_id = c.id) as total_conversations
    FROM chatbots c
    $client_filter
    ORDER BY c.created_at DESC
")->fetchAll();

// Stats globales (filtrees pour client)
$total_leads = $pdo->query("SELECT COUNT(*) FROM leads" . ($client_id ? " WHERE chatbot_id = $client_id" : ""))->fetchColumn();
$total_conversations = $pdo->query("SELECT COUNT(*) FROM chatbot_conversations" . ($client_id ? " WHERE chatbot_id = $client_id" : ""))->fetchColumn();
$active_conversations = $pdo->query("SELECT COUNT(*) FROM chatbot_conversations WHERE is_active = 1 AND last_activity > DATE_SUB(NOW(), INTERVAL 30 MINUTE)" . ($client_id ? " AND chatbot_id = $client_id" : ""))->fetchColumn();

include __DIR__ . '/includes/admin-header.php';
?>

<div class="admin-content">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
        <h1 class="admin-title" style="margin-bottom:0;">Dashboard</h1>
        <?php if (isAdmin()): ?>
        <a href="chatbot-create.php" class="btn btn-primary">+ Nouveau chatbot</a>
        <?php endif; ?>
    </div>

    <!-- Stats globales -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value" style="color:var(--color-primary);"><?= count($chatbots) ?></div>
            <div class="stat-label">Chatbots</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" style="color:var(--color-success);"><?= $active_conversations ?></div>
            <div class="stat-label">Conversations actives</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" style="color:var(--color-info);"><?= $total_conversations ?></div>
            <div class="stat-label">Conversations totales</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" style="color:var(--color-warning);"><?= $total_leads ?></div>
            <div class="stat-label">Leads totaux</div>
        </div>
    </div>

    <!-- Liste des chatbots -->
    <div class="admin-section">
        <h2 style="font-size:18px;margin-bottom:16px;">Chatbots</h2>

        <?php if (empty($chatbots)): ?>
        <div style="text-align:center;padding:40px;color:var(--color-gray);">
            <p style="font-size:16px;margin-bottom:12px;">Aucun chatbot configure</p>
            <a href="chatbot-create.php" class="btn btn-primary">Creer votre premier chatbot</a>
        </div>
        <?php else: ?>
        <div style="overflow-x:auto;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Domaine</th>
                        <th>Statut</th>
                        <th>Conversations</th>
                        <th>Actives</th>
                        <th>Leads</th>
                        <th>Cree le</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($chatbots as $bot): ?>
                    <tr>
                        <td>
                            <strong><?= e($bot['name']) ?></strong>
                            <?php if ($bot['ai_provider'] !== 'none'): ?>
                            <span class="badge badge-info" style="margin-left:4px;"><?= e($bot['ai_provider']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($bot['domain']): ?>
                            <code style="font-size:12px;background:#f0f0f0;padding:2px 6px;border-radius:4px;"><?= e($bot['domain']) ?></code>
                            <?php else: ?>
                            <span style="color:#999;">Non configure</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($bot['is_active']): ?>
                            <span class="badge badge-success">En ligne</span>
                            <?php else: ?>
                            <span class="badge badge-error">Hors ligne</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;font-weight:600;"><?= $bot['total_conversations'] ?></td>
                        <td style="text-align:center;">
                            <?php if ($bot['active_conversations'] > 0): ?>
                            <span style="color:var(--color-success);font-weight:600;"><?= $bot['active_conversations'] ?></span>
                            <?php else: ?>
                            <span style="color:#ccc;">0</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;font-weight:600;"><?= $bot['leads_count'] ?></td>
                        <td style="font-size:12px;color:var(--color-gray);"><?= dateFR($bot['created_at']) ?></td>
                        <td>
                            <a href="chatbot-edit.php?id=<?= $bot['id'] ?>" class="btn btn-sm btn-primary">Editer</a>
                            <a href="chatbot-stats.php?chatbot_id=<?= $bot['id'] ?>" class="btn btn-sm btn-outline">Stats</a>
                            <a href="chatbot-intentions.php?chatbot_id=<?= $bot['id'] ?>" class="btn btn-sm btn-outline">Intentions</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
