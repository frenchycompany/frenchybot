<?php
/**
 * FrenchyBot Admin - Creer un nouveau chatbot
 */
define('FRENCHYBOT', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$admin_user = requireAdmin();

// Seul l'admin peut creer des chatbots
if (!isAdmin()) {
    header('Location: dashboard.php');
    exit;
}

$page_title = 'Nouveau chatbot';
$error = '';

// Charger les chatbots existants pour le clonage
$existingChatbots = $pdo->query("SELECT id, name FROM chatbots ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $domain = trim($_POST['domain'] ?? '');
    $welcome_message = trim($_POST['welcome_message'] ?? '');
    $primary_color = trim($_POST['primary_color'] ?? '#1a5653');
    $notification_email = trim($_POST['notification_email'] ?? '');
    $clone_from = intval($_POST['clone_from'] ?? 0);

    if (empty($name)) {
        $error = 'Le nom du chatbot est obligatoire.';
    } else {
        try {
            $token = generateToken(16);
            $secret_key = generateToken(32);

            $stmt = $pdo->prepare("INSERT INTO chatbots (name, domain, token, secret_key, welcome_message, primary_color, notification_email)
                                   VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $domain, $token, $secret_key, $welcome_message, $primary_color, $notification_email]);
            $newId = $pdo->lastInsertId();

            // Cloner les intentions si demande
            if ($clone_from > 0) {
                $stmt = $pdo->prepare("INSERT INTO chatbot_intentions (chatbot_id, intention_key, keywords, response_text, action, priority, category, is_active)
                    SELECT ?, intention_key, keywords, response_text, action, priority, category, is_active
                    FROM chatbot_intentions WHERE chatbot_id = ?");
                $stmt->execute([$newId, $clone_from]);
            }

            flash('success', 'Chatbot "' . e($name) . '" cree avec succes !');
            header('Location: chatbot-edit.php?id=' . $newId);
            exit;
        } catch (PDOException $ex) {
            $error = 'Erreur lors de la creation : ' . $ex->getMessage();
        }
    }
}

include __DIR__ . '/includes/admin-header.php';
?>

<div class="admin-content">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
        <h1 class="admin-title" style="margin-bottom:0;">Nouveau chatbot</h1>
        <a href="dashboard.php" class="btn btn-outline">&larr; Retour au dashboard</a>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="admin-section" style="max-width:700px;">
        <form method="POST">
            <div class="form-group">
                <label for="name">Nom du chatbot *</label>
                <input type="text" id="name" name="name" class="form-control" required
                       placeholder="Ex: Chatbot ORCA, Support MonSite..."
                       value="<?= e($_POST['name'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="domain">Domaine autorise</label>
                <input type="text" id="domain" name="domain" class="form-control"
                       placeholder="Ex: www.monsite.fr (laisser vide pour autoriser tous les domaines)"
                       value="<?= e($_POST['domain'] ?? '') ?>">
                <small>Le widget ne fonctionnera que sur ce domaine. Laisser vide pour ne pas restreindre.</small>
            </div>

            <div class="form-group">
                <label for="welcome_message">Message de bienvenue</label>
                <textarea id="welcome_message" name="welcome_message" class="form-control" rows="3"
                          placeholder="Bonjour ! Comment puis-je vous aider ?"><?= e($_POST['welcome_message'] ?? '') ?></textarea>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div class="form-group">
                    <label for="primary_color">Couleur principale</label>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <input type="color" id="primary_color" name="primary_color"
                               value="<?= e($_POST['primary_color'] ?? '#1a5653') ?>"
                               style="width:50px;height:38px;border:1px solid #ddd;border-radius:6px;cursor:pointer;padding:2px;">
                        <input type="text" id="primary_color_text" class="form-control" style="width:120px;"
                               value="<?= e($_POST['primary_color'] ?? '#1a5653') ?>"
                               oninput="document.getElementById('primary_color').value=this.value">
                    </div>
                </div>

                <div class="form-group">
                    <label for="notification_email">Email de notification</label>
                    <input type="email" id="notification_email" name="notification_email" class="form-control"
                           placeholder="admin@monsite.fr"
                           value="<?= e($_POST['notification_email'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="clone_from">Cloner les intentions depuis</label>
                <select id="clone_from" name="clone_from" class="form-control">
                    <option value="0">-- Aucun (chatbot vierge) --</option>
                    <?php foreach ($existingChatbots as $bot): ?>
                    <option value="<?= $bot['id'] ?>" <?= (($_POST['clone_from'] ?? '') == $bot['id']) ? 'selected' : '' ?>>
                        <?= e($bot['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <small>Copier toutes les intentions d'un chatbot existant pour demarrer plus vite.</small>
            </div>

            <div style="display:flex;gap:12px;margin-top:24px;">
                <button type="submit" class="btn btn-primary" style="padding:12px 32px;">Creer le chatbot</button>
                <a href="dashboard.php" class="btn btn-outline" style="padding:12px 32px;">Annuler</a>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('primary_color').addEventListener('input', function() {
    document.getElementById('primary_color_text').value = this.value;
});
</script>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
