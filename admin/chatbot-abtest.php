<?php
/**
 * FrenchyBot Admin - A/B Testing
 */
define('FRENCHYBOT', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$admin_user = requireAdmin();
$page_title = 'A/B Testing';

$chatbot_id = intval($_GET['chatbot_id'] ?? 0);
if (!$chatbot_id) { header('Location: dashboard.php'); exit; }

$chatbot = getChatbotById($chatbot_id);
if (!$chatbot) { header('Location: dashboard.php'); exit; }

// Create test
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_test'])) {
    $name = trim($_POST['name'] ?? '');
    $type = trim($_POST['test_type'] ?? 'welcome_message');
    $va = trim($_POST['variant_a'] ?? '');
    $vb = trim($_POST['variant_b'] ?? '');

    if ($name && $va && $vb) {
        $pdo->prepare("INSERT INTO chatbot_ab_tests (chatbot_id, name, test_type, variant_a_value, variant_b_value, status) VALUES (?, ?, ?, ?, ?, 'active')")
            ->execute([$chatbot_id, $name, $type, $va, $vb]);
        flash('success', 'Test A/B cree');
    } else {
        flash('error', 'Tous les champs sont requis');
    }
    header('Location: chatbot-abtest.php?chatbot_id=' . $chatbot_id);
    exit;
}

// Update status
if (isset($_GET['action']) && isset($_GET['test_id'])) {
    $test_id = intval($_GET['test_id']);
    $action = $_GET['action'];
    $status_map = ['pause' => 'paused', 'resume' => 'active', 'complete' => 'completed'];
    if (isset($status_map[$action])) {
        $updates = "status = ?";
        $params = [$status_map[$action]];
        if ($action === 'complete') {
            $updates .= ", ended_at = NOW()";
        }
        $pdo->prepare("UPDATE chatbot_ab_tests SET $updates WHERE id = ? AND chatbot_id = ?")
            ->execute(array_merge($params, [$test_id, $chatbot_id]));
    }
    header('Location: chatbot-abtest.php?chatbot_id=' . $chatbot_id);
    exit;
}

// Load tests
$stmt = $pdo->prepare("SELECT * FROM chatbot_ab_tests WHERE chatbot_id = ? ORDER BY created_at DESC");
$stmt->execute([$chatbot_id]);
$tests = $stmt->fetchAll();

// Stats per test
$test_stats = [];
foreach ($tests as $t) {
    $stmt = $pdo->prepare("SELECT ab_variant, COUNT(*) as cnt, SUM(CASE WHEN lead_id IS NOT NULL THEN 1 ELSE 0 END) as leads
        FROM chatbot_conversations WHERE ab_test_id = ? GROUP BY ab_variant");
    $stmt->execute([$t['id']]);
    $test_stats[$t['id']] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR | PDO::FETCH_GROUP) ?: [];
    // Reformat
    $stats_a = ['cnt' => 0, 'leads' => 0];
    $stats_b = ['cnt' => 0, 'leads' => 0];
    $stmt2 = $pdo->prepare("SELECT ab_variant, COUNT(*) as cnt, SUM(CASE WHEN lead_id IS NOT NULL THEN 1 ELSE 0 END) as leads
        FROM chatbot_conversations WHERE ab_test_id = ? GROUP BY ab_variant");
    $stmt2->execute([$t['id']]);
    foreach ($stmt2->fetchAll() as $row) {
        if ($row['ab_variant'] === 'A') $stats_a = $row;
        if ($row['ab_variant'] === 'B') $stats_b = $row;
    }
    $test_stats[$t['id']] = ['A' => $stats_a, 'B' => $stats_b];
}

include 'includes/admin-header.php';
?>

<div style="max-width:900px;margin:0 auto;">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;">
        <a href="chatbot-edit.php?id=<?= $chatbot_id ?>" style="color:#1a5653;text-decoration:none;font-size:20px;">&#8592;</a>
        <h1 style="margin:0;">A/B Testing - <?= e($chatbot['name']) ?></h1>
    </div>

    <?php foreach (getFlash() as $f): ?>
        <div style="padding:12px 16px;background:<?= $f['type'] === 'error' ? '#fee2e2' : '#d1fae5' ?>;border-radius:8px;margin-bottom:16px;"><?= $f['message'] ?></div>
    <?php endforeach; ?>

    <!-- Existing tests -->
    <?php foreach ($tests as $t): ?>
    <div style="background:#fff;border-radius:12px;padding:24px;box-shadow:0 1px 3px rgba(0,0,0,0.06);margin-bottom:16px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <div>
                <h3 style="margin:0;"><?= e($t['name']) ?></h3>
                <small style="color:#6b7280;"><?= e($t['test_type']) ?> - <?= dateFR($t['created_at']) ?></small>
            </div>
            <div style="display:flex;gap:8px;align-items:center;">
                <?= statusBadge($t['status']) ?>
                <?php if ($t['status'] === 'active'): ?>
                    <a href="?chatbot_id=<?= $chatbot_id ?>&action=pause&test_id=<?= $t['id'] ?>" style="color:#f59e0b;font-size:13px;">Pause</a>
                    <a href="?chatbot_id=<?= $chatbot_id ?>&action=complete&test_id=<?= $t['id'] ?>" style="color:#10b981;font-size:13px;">Terminer</a>
                <?php elseif ($t['status'] === 'paused'): ?>
                    <a href="?chatbot_id=<?= $chatbot_id ?>&action=resume&test_id=<?= $t['id'] ?>" style="color:#3b82f6;font-size:13px;">Reprendre</a>
                <?php endif; ?>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <?php $sa = $test_stats[$t['id']]['A'] ?? ['cnt'=>0,'leads'=>0]; $sb = $test_stats[$t['id']]['B'] ?? ['cnt'=>0,'leads'=>0]; ?>
            <div style="background:#f0fdf4;border-radius:8px;padding:16px;">
                <div style="font-weight:600;margin-bottom:8px;">Variante A</div>
                <div style="font-size:13px;color:#6b7280;margin-bottom:8px;"><?= e(truncate($t['variant_a_value'], 80)) ?></div>
                <div><strong><?= intval($sa['cnt']) ?></strong> conversations - <strong><?= intval($sa['leads']) ?></strong> leads
                    <?php if (intval($sa['cnt']) > 0): ?> (<?= round(intval($sa['leads'])/intval($sa['cnt'])*100, 1) ?>%)<?php endif; ?>
                </div>
            </div>
            <div style="background:#eff6ff;border-radius:8px;padding:16px;">
                <div style="font-weight:600;margin-bottom:8px;">Variante B</div>
                <div style="font-size:13px;color:#6b7280;margin-bottom:8px;"><?= e(truncate($t['variant_b_value'], 80)) ?></div>
                <div><strong><?= intval($sb['cnt']) ?></strong> conversations - <strong><?= intval($sb['leads']) ?></strong> leads
                    <?php if (intval($sb['cnt']) > 0): ?> (<?= round(intval($sb['leads'])/intval($sb['cnt'])*100, 1) ?>%)<?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- New test form -->
    <div style="background:#fff;border-radius:12px;padding:24px;box-shadow:0 1px 3px rgba(0,0,0,0.06);">
        <h3 style="margin:0 0 16px;color:#1a5653;">Creer un nouveau test</h3>
        <form method="post">
            <input type="hidden" name="create_test" value="1">
            <div style="margin-bottom:12px;">
                <label style="display:block;font-weight:600;margin-bottom:4px;">Nom du test</label>
                <input type="text" name="name" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;box-sizing:border-box;">
            </div>
            <div style="margin-bottom:12px;">
                <label style="display:block;font-weight:600;margin-bottom:4px;">Type</label>
                <select name="test_type" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;">
                    <option value="welcome_message">Message de bienvenue</option>
                </select>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:12px;">
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;">Variante A</label>
                    <textarea name="variant_a" rows="3" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;box-sizing:border-box;resize:vertical;"></textarea>
                </div>
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;">Variante B</label>
                    <textarea name="variant_b" rows="3" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;box-sizing:border-box;resize:vertical;"></textarea>
                </div>
            </div>
            <button type="submit" style="padding:10px 24px;background:#1a5653;color:#fff;border:none;border-radius:8px;cursor:pointer;font-weight:600;">Lancer le test</button>
        </form>
    </div>
</div>

<?php include 'includes/admin-footer.php'; ?>
