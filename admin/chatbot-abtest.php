<?php
/**
 * FrenchyBot Admin - A/B Testing
 */
define('FRENCHYBOT', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$chatbot_id = intval($_GET['chatbot_id'] ?? 0);
if (!$chatbot_id) {
    flash('error', 'Chatbot non specifie.');
    header('Location: dashboard.php');
    exit;
}

$chatbot = getChatbotById($chatbot_id);
if (!$chatbot) {
    flash('error', 'Chatbot introuvable.');
    header('Location: dashboard.php');
    exit;
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $test_type = trim($_POST['test_type'] ?? 'welcome_message');
        $variant_a = trim($_POST['variant_a_value'] ?? '');
        $variant_b = trim($_POST['variant_b_value'] ?? '');

        if (empty($name) || empty($variant_a) || empty($variant_b)) {
            flash('error', 'Tous les champs sont obligatoires.');
        } else {
            $stmt = $pdo->prepare("INSERT INTO chatbot_ab_tests (chatbot_id, name, test_type, variant_a_value, variant_b_value, status)
                                   VALUES (?, ?, ?, ?, ?, 'active')");
            $stmt->execute([$chatbot_id, $name, $test_type, $variant_a, $variant_b]);
            flash('success', 'Test A/B "' . e($name) . '" cree avec succes.');
        }
        header('Location: chatbot-abtest.php?chatbot_id=' . $chatbot_id);
        exit;
    }

    if ($action === 'update_status') {
        $test_id = intval($_POST['test_id'] ?? 0);
        $new_status = $_POST['status'] ?? '';
        $allowed = ['active', 'paused', 'completed'];

        if ($test_id && in_array($new_status, $allowed)) {
            if ($new_status === 'completed') {
                $stmt = $pdo->prepare("UPDATE chatbot_ab_tests SET status = ?, ended_at = NOW() WHERE id = ? AND chatbot_id = ?");
            } else {
                $stmt = $pdo->prepare("UPDATE chatbot_ab_tests SET status = ?, ended_at = NULL WHERE id = ? AND chatbot_id = ?");
            }
            $stmt->execute([$new_status, $test_id, $chatbot_id]);
            flash('success', 'Statut du test mis a jour.');
        }
        header('Location: chatbot-abtest.php?chatbot_id=' . $chatbot_id);
        exit;
    }
}

// Load tests with stats
$stmt = $pdo->prepare("SELECT * FROM chatbot_ab_tests WHERE chatbot_id = ? ORDER BY created_at DESC");
$stmt->execute([$chatbot_id]);
$tests = $stmt->fetchAll();

// Get conversation counts per variant for each test
$test_stats = [];
foreach ($tests as $test) {
    $stmt = $pdo->prepare("
        SELECT ab_variant, COUNT(*) as count
        FROM chatbot_conversations
        WHERE chatbot_id = ? AND ab_test_id = ?
        GROUP BY ab_variant
    ");
    $stmt->execute([$chatbot_id, $test['id']]);
    $variants = $stmt->fetchAll();
    $stats = ['A' => 0, 'B' => 0];
    foreach ($variants as $v) {
        if (isset($stats[$v['ab_variant']])) {
            $stats[$v['ab_variant']] = $v['count'];
        }
    }
    $test_stats[$test['id']] = $stats;
}

$page_title = 'A/B Testing - ' . $chatbot['name'];

include __DIR__ . '/includes/admin-header.php';
?>

<div class="admin-content">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
        <h1 class="admin-title" style="margin-bottom:0;">A/B Testing : <?= e($chatbot['name']) ?></h1>
        <div style="display:flex;gap:8px;">
            <a href="chatbot-edit.php?id=<?= $chatbot_id ?>" class="btn btn-outline">Editer chatbot</a>
            <a href="dashboard.php" class="btn btn-outline">&larr; Dashboard</a>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value" style="color:var(--color-primary);"><?= count($tests) ?></div>
            <div class="stat-label">Tests au total</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" style="color:var(--color-success);"><?= count(array_filter($tests, fn($t) => $t['status'] === 'active')) ?></div>
            <div class="stat-label">Tests actifs</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" style="color:var(--color-info);"><?= count(array_filter($tests, fn($t) => $t['status'] === 'completed')) ?></div>
            <div class="stat-label">Tests termines</div>
        </div>
    </div>

    <!-- Create new test -->
    <div class="admin-section">
        <h2 style="font-size:18px;margin-bottom:16px;">Creer un nouveau test</h2>

        <form method="POST">
            <input type="hidden" name="action" value="create">

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div class="form-group">
                    <label for="name">Nom du test *</label>
                    <input type="text" id="name" name="name" class="form-control" required
                           placeholder="Ex: Test message bienvenue v2">
                </div>
                <div class="form-group">
                    <label for="test_type">Type de test</label>
                    <select id="test_type" name="test_type" class="form-control">
                        <option value="welcome_message">Message de bienvenue</option>
                    </select>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div class="form-group">
                    <label for="variant_a_value">Variante A *</label>
                    <textarea id="variant_a_value" name="variant_a_value" class="form-control" rows="3" required
                              placeholder="Message de la variante A..."></textarea>
                </div>
                <div class="form-group">
                    <label for="variant_b_value">Variante B *</label>
                    <textarea id="variant_b_value" name="variant_b_value" class="form-control" rows="3" required
                              placeholder="Message de la variante B..."></textarea>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Creer le test</button>
        </form>
    </div>

    <!-- List of tests -->
    <div class="admin-section">
        <h2 style="font-size:18px;margin-bottom:16px;">Tests existants</h2>

        <?php if (empty($tests)): ?>
        <div style="text-align:center;padding:40px;color:var(--color-gray);">
            <p>Aucun test A/B pour ce chatbot.</p>
        </div>
        <?php else: ?>
        <?php foreach ($tests as $test): ?>
        <?php $stats = $test_stats[$test['id']] ?? ['A' => 0, 'B' => 0]; ?>
        <?php $total = $stats['A'] + $stats['B']; ?>
        <div style="border:1px solid #eee;border-radius:12px;padding:20px;margin-bottom:16px;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px;">
                <div>
                    <h3 style="font-size:16px;margin-bottom:4px;"><?= e($test['name']) ?></h3>
                    <div style="font-size:12px;color:var(--color-gray);">
                        Type: <?= e($test['test_type']) ?>
                        | Cree le: <?= dateFR($test['created_at']) ?>
                        <?php if ($test['ended_at']): ?>
                        | Termine le: <?= dateFR($test['ended_at']) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="display:flex;gap:8px;align-items:center;">
                    <?= statusBadge($test['status']) ?>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="test_id" value="<?= $test['id'] ?>">
                        <?php if ($test['status'] === 'active'): ?>
                        <button type="submit" name="status" value="paused" class="btn btn-sm btn-outline">Pause</button>
                        <button type="submit" name="status" value="completed" class="btn btn-sm btn-danger">Terminer</button>
                        <?php elseif ($test['status'] === 'paused'): ?>
                        <button type="submit" name="status" value="active" class="btn btn-sm btn-primary">Reprendre</button>
                        <button type="submit" name="status" value="completed" class="btn btn-sm btn-danger">Terminer</button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <!-- Variant A -->
                <div style="background:#f8f9fa;border-radius:8px;padding:16px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                        <strong style="color:var(--color-primary);">Variante A</strong>
                        <span style="font-size:24px;font-weight:700;"><?= $stats['A'] ?></span>
                    </div>
                    <div style="font-size:13px;color:#666;margin-bottom:8px;"><?= nl2br(e(truncate($test['variant_a_value'], 200))) ?></div>
                    <?php if ($total > 0): ?>
                    <div style="background:#e5e7eb;border-radius:4px;height:8px;overflow:hidden;">
                        <div style="background:var(--color-primary);height:100%;width:<?= round(($stats['A'] / $total) * 100) ?>%;border-radius:4px;"></div>
                    </div>
                    <div style="font-size:12px;color:var(--color-gray);margin-top:4px;"><?= round(($stats['A'] / $total) * 100, 1) ?>%</div>
                    <?php endif; ?>
                </div>

                <!-- Variant B -->
                <div style="background:#f8f9fa;border-radius:8px;padding:16px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                        <strong style="color:var(--color-info);">Variante B</strong>
                        <span style="font-size:24px;font-weight:700;"><?= $stats['B'] ?></span>
                    </div>
                    <div style="font-size:13px;color:#666;margin-bottom:8px;"><?= nl2br(e(truncate($test['variant_b_value'], 200))) ?></div>
                    <?php if ($total > 0): ?>
                    <div style="background:#e5e7eb;border-radius:4px;height:8px;overflow:hidden;">
                        <div style="background:var(--color-info);height:100%;width:<?= round(($stats['B'] / $total) * 100) ?>%;border-radius:4px;"></div>
                    </div>
                    <div style="font-size:12px;color:var(--color-gray);margin-top:4px;"><?= round(($stats['B'] / $total) * 100, 1) ?>%</div>
                    <?php endif; ?>
                </div>
            </div>

            <div style="text-align:center;margin-top:12px;font-size:13px;color:var(--color-gray);">
                Total: <?= numberFR($total) ?> conversations
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
