<?php
/**
 * FrenchyBot Admin - Fiche detail lead
 */
define('FRENCHYBOT', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$lead_id = intval($_GET['id'] ?? 0);
if (!$lead_id) {
    flash('error', 'Lead introuvable.');
    header('Location: leads.php');
    exit;
}

// Handle POST: update status or add notes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_status') {
        $new_status = $_POST['status'] ?? 'new';
        $allowed = ['new', 'contacted', 'qualified', 'converted', 'lost'];
        if (in_array($new_status, $allowed)) {
            $stmt = $pdo->prepare("UPDATE leads SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$new_status, $lead_id]);
            flash('success', 'Statut mis a jour.');
        }
        header('Location: lead-view.php?id=' . $lead_id);
        exit;
    }

    if ($action === 'add_notes') {
        $notes = trim($_POST['notes'] ?? '');
        $stmt = $pdo->prepare("UPDATE leads SET notes = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$notes, $lead_id]);
        flash('success', 'Notes mises a jour.');
        header('Location: lead-view.php?id=' . $lead_id);
        exit;
    }
}

// Load lead with chatbot name
$stmt = $pdo->prepare("
    SELECT l.*, c.name as chatbot_name
    FROM leads l
    LEFT JOIN chatbots c ON l.chatbot_id = c.id
    WHERE l.id = ?
");
$stmt->execute([$lead_id]);
$lead = $stmt->fetch();

if (!$lead) {
    flash('error', 'Lead introuvable.');
    header('Location: leads.php');
    exit;
}

// Find associated conversation
$stmt = $pdo->prepare("SELECT * FROM chatbot_conversations WHERE lead_id = ? ORDER BY started_at DESC LIMIT 1");
$stmt->execute([$lead_id]);
$conversation = $stmt->fetch();

// Load conversation messages if conversation exists
$messages = [];
if ($conversation) {
    $stmt = $pdo->prepare("SELECT * FROM chatbot_messages WHERE conversation_id = ? ORDER BY created_at ASC");
    $stmt->execute([$conversation['id']]);
    $messages = $stmt->fetchAll();
}

$page_title = 'Lead - ' . ($lead['prenom'] ?? '') . ' ' . ($lead['nom'] ?? '');

include __DIR__ . '/includes/admin-header.php';
?>

<div class="admin-content">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
        <h1 class="admin-title" style="margin-bottom:0;">
            Lead : <?= e(($lead['prenom'] ?? '') . ' ' . ($lead['nom'] ?? '')) ?>
            <?= statusBadge($lead['status']) ?>
        </h1>
        <div style="display:flex;gap:8px;">
            <?php if ($conversation): ?>
            <a href="chatbot-view.php?id=<?= $conversation['id'] ?>" class="btn btn-outline">Voir la conversation</a>
            <?php endif; ?>
            <a href="leads.php<?= $lead['chatbot_id'] ? '?chatbot_id=' . $lead['chatbot_id'] : '' ?>" class="btn btn-outline">&larr; Retour aux leads</a>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
        <!-- Infos lead -->
        <div>
            <div class="admin-section">
                <h2 style="font-size:18px;margin-bottom:16px;">Informations du lead</h2>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:14px;">
                    <div>
                        <label style="font-size:12px;color:var(--color-gray);display:block;">Nom</label>
                        <strong><?= e($lead['nom'] ?? '-') ?></strong>
                    </div>
                    <div>
                        <label style="font-size:12px;color:var(--color-gray);display:block;">Prenom</label>
                        <strong><?= e($lead['prenom'] ?? '-') ?></strong>
                    </div>
                    <div>
                        <label style="font-size:12px;color:var(--color-gray);display:block;">Email</label>
                        <?php if ($lead['email']): ?>
                        <a href="mailto:<?= e($lead['email']) ?>"><?= e($lead['email']) ?></a>
                        <?php else: ?>
                        <span style="color:#999;">-</span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label style="font-size:12px;color:var(--color-gray);display:block;">Telephone</label>
                        <?php if ($lead['telephone']): ?>
                        <a href="tel:<?= e($lead['telephone']) ?>"><?= e($lead['telephone']) ?></a>
                        <?php else: ?>
                        <span style="color:#999;">-</span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label style="font-size:12px;color:var(--color-gray);display:block;">Departement</label>
                        <span><?= e($lead['departement'] ?? '-') ?></span>
                    </div>
                    <div>
                        <label style="font-size:12px;color:var(--color-gray);display:block;">Surface souhaitee</label>
                        <span><?= e($lead['surface_souhaitee'] ?? '-') ?></span>
                    </div>
                    <div>
                        <label style="font-size:12px;color:var(--color-gray);display:block;">Budget estime</label>
                        <span><?= e($lead['budget_estime'] ?? '-') ?></span>
                    </div>
                    <div>
                        <label style="font-size:12px;color:var(--color-gray);display:block;">Terrain prevu</label>
                        <span><?= $lead['terrain_prevu'] ? 'Oui' : 'Non' ?></span>
                    </div>
                    <div>
                        <label style="font-size:12px;color:var(--color-gray);display:block;">Type de demande</label>
                        <span><?= e($lead['type_demande'] ?? '-') ?></span>
                    </div>
                    <div>
                        <label style="font-size:12px;color:var(--color-gray);display:block;">Source</label>
                        <span><?= e($lead['source'] ?? '-') ?></span>
                    </div>
                    <div>
                        <label style="font-size:12px;color:var(--color-gray);display:block;">Page source</label>
                        <span style="word-break:break-all;"><?= e($lead['page_source'] ?? '-') ?></span>
                    </div>
                    <div>
                        <label style="font-size:12px;color:var(--color-gray);display:block;">Adresse IP</label>
                        <span><?= e($lead['ip_address'] ?? '-') ?></span>
                    </div>
                </div>

                <hr style="border:none;border-top:1px solid #eee;margin:16px 0;">

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:14px;">
                    <div>
                        <label style="font-size:12px;color:var(--color-gray);display:block;">Chatbot</label>
                        <?php if ($lead['chatbot_name']): ?>
                        <a href="chatbot-edit.php?id=<?= $lead['chatbot_id'] ?>"><?= e($lead['chatbot_name']) ?></a>
                        <?php else: ?>
                        <span style="color:#999;">-</span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label style="font-size:12px;color:var(--color-gray);display:block;">Cree le</label>
                        <span><?= dateFR($lead['created_at']) ?></span>
                    </div>
                    <div>
                        <label style="font-size:12px;color:var(--color-gray);display:block;">Mis a jour le</label>
                        <span><?= dateFR($lead['updated_at']) ?></span>
                    </div>
                </div>
            </div>

            <!-- Statut -->
            <div class="admin-section">
                <h2 style="font-size:18px;margin-bottom:16px;">Statut</h2>
                <form method="POST" style="display:flex;gap:12px;align-items:flex-end;">
                    <input type="hidden" name="action" value="update_status">
                    <div class="form-group" style="margin-bottom:0;flex:1;">
                        <select name="status" class="form-control">
                            <option value="new" <?= $lead['status'] === 'new' ? 'selected' : '' ?>>New</option>
                            <option value="contacted" <?= $lead['status'] === 'contacted' ? 'selected' : '' ?>>Contacted</option>
                            <option value="qualified" <?= $lead['status'] === 'qualified' ? 'selected' : '' ?>>Qualified</option>
                            <option value="converted" <?= $lead['status'] === 'converted' ? 'selected' : '' ?>>Converted</option>
                            <option value="lost" <?= $lead['status'] === 'lost' ? 'selected' : '' ?>>Lost</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Mettre a jour</button>
                </form>
            </div>

            <!-- Notes -->
            <div class="admin-section">
                <h2 style="font-size:18px;margin-bottom:16px;">Notes</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="add_notes">
                    <div class="form-group">
                        <textarea name="notes" class="form-control" rows="5" placeholder="Ajouter des notes sur ce lead..."><?= e($lead['notes'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Enregistrer les notes</button>
                </form>
            </div>
        </div>

        <!-- Conversation transcript -->
        <div>
            <div class="admin-section">
                <h2 style="font-size:18px;margin-bottom:16px;">
                    Conversation
                    <?php if ($conversation): ?>
                    <a href="chatbot-view.php?id=<?= $conversation['id'] ?>" class="btn btn-sm btn-outline" style="margin-left:8px;">Ouvrir</a>
                    <?php endif; ?>
                </h2>

                <?php if (empty($messages)): ?>
                <div style="text-align:center;padding:40px;color:var(--color-gray);">
                    <p>Aucune conversation associee a ce lead.</p>
                </div>
                <?php else: ?>
                <div style="max-height:600px;overflow-y:auto;padding:8px;">
                    <?php foreach ($messages as $msg): ?>
                    <div style="display:flex;flex-direction:column;align-items:<?= $msg['type'] === 'user' ? 'flex-end' : 'flex-start' ?>;margin-bottom:12px;">
                        <div style="max-width:85%;padding:10px 14px;border-radius:12px;font-size:13px;line-height:1.5;
                            <?php if ($msg['type'] === 'user'): ?>
                            background:var(--color-primary);color:#fff;border-bottom-right-radius:4px;
                            <?php elseif ($msg['type'] === 'system'): ?>
                            background:#fef3c7;color:#92400e;border-bottom-left-radius:4px;font-style:italic;
                            <?php else: ?>
                            background:#f0f0f0;color:#333;border-bottom-left-radius:4px;
                            <?php endif; ?>
                        ">
                            <?= nl2br(e($msg['message'])) ?>
                        </div>
                        <div style="font-size:11px;color:var(--color-gray);margin-top:2px;">
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
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
