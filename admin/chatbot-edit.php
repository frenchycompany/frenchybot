<?php
/**
 * FrenchyBot Admin - Editer un chatbot
 */
define('FRENCHYBOT', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$admin_user = requireAdmin();
$page_title = 'Editer chatbot';
$error = '';

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header('Location: dashboard.php');
    exit;
}

// Client ne peut voir que son chatbot
$client_id = getClientChatbotId();
if ($client_id && $client_id !== $id) {
    header('Location: dashboard.php');
    exit;
}

// Regenerer token (admin only)
if (isset($_POST['regenerate_token']) && isAdmin()) {
    $newToken = generateToken(16);
    $pdo->prepare("UPDATE chatbots SET token = ?, updated_at = NOW() WHERE id = ?")->execute([$newToken, $id]);
    flash('success', 'Token regenere avec succes.');
    header('Location: chatbot-edit.php?id=' . $id);
    exit;
}

// Toggle active
if (isset($_POST['toggle_active'])) {
    $pdo->prepare("UPDATE chatbots SET is_active = NOT is_active, updated_at = NOW() WHERE id = ?")->execute([$id]);
    flash('success', 'Statut du chatbot mis a jour.');
    header('Location: chatbot-edit.php?id=' . $id);
    exit;
}

// Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $data = [
        'name' => trim($_POST['name'] ?? ''),
        'domain' => trim($_POST['domain'] ?? ''),
        'welcome_message' => trim($_POST['welcome_message'] ?? ''),
        'primary_color' => trim($_POST['primary_color'] ?? '#1a5653'),
        'auto_popup' => isset($_POST['auto_popup']) ? 1 : 0,
        'popup_delay' => intval($_POST['popup_delay'] ?? 20),
        'logo_url' => trim($_POST['logo_url'] ?? ''),
        'ai_provider' => $_POST['ai_provider'] ?? 'none',
        'ai_api_key' => trim($_POST['ai_api_key'] ?? ''),
        'ai_model' => trim($_POST['ai_model'] ?? 'gpt-4'),
        'webhook_enabled' => isset($_POST['webhook_enabled']) ? 1 : 0,
        'webhook_url' => trim($_POST['webhook_url'] ?? ''),
        'email_notifications' => isset($_POST['email_notifications']) ? 1 : 0,
        'notification_email' => trim($_POST['notification_email'] ?? ''),
        // BDD externe
        'ext_db_enabled' => isset($_POST['ext_db_enabled']) ? 1 : 0,
        'ext_db_host' => trim($_POST['ext_db_host'] ?? 'localhost'),
        'ext_db_name' => trim($_POST['ext_db_name'] ?? ''),
        'ext_db_user' => trim($_POST['ext_db_user'] ?? ''),
        'ext_db_pass' => trim($_POST['ext_db_pass'] ?? ''),
        'ext_db_products' => trim($_POST['ext_db_products'] ?? ''),
    ];

    if (empty($data['name'])) {
        $error = 'Le nom est obligatoire.';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE chatbots SET
                name=?, domain=?, welcome_message=?, primary_color=?, auto_popup=?, popup_delay=?,
                logo_url=?, ai_provider=?, ai_api_key=?, ai_model=?, webhook_enabled=?, webhook_url=?,
                email_notifications=?, notification_email=?,
                ext_db_enabled=?, ext_db_host=?, ext_db_name=?, ext_db_user=?, ext_db_pass=?, ext_db_products=?,
                updated_at=NOW()
                WHERE id=?");
            $stmt->execute([
                $data['name'], $data['domain'], $data['welcome_message'], $data['primary_color'],
                $data['auto_popup'], $data['popup_delay'], $data['logo_url'], $data['ai_provider'],
                $data['ai_api_key'], $data['ai_model'], $data['webhook_enabled'], $data['webhook_url'],
                $data['email_notifications'], $data['notification_email'],
                $data['ext_db_enabled'], $data['ext_db_host'], $data['ext_db_name'], $data['ext_db_user'],
                $data['ext_db_pass'], $data['ext_db_products'] ?: null, $id
            ]);
            flash('success', 'Chatbot mis a jour.');
            header('Location: chatbot-edit.php?id=' . $id);
            exit;
        } catch (PDOException $ex) {
            $error = 'Erreur : ' . $ex->getMessage();
        }
    }
}

// Load chatbot
$chatbot = getChatbotById($id);
if (!$chatbot) {
    flash('error', 'Chatbot introuvable.');
    header('Location: dashboard.php');
    exit;
}

$page_title = 'Editer - ' . $chatbot['name'];

include __DIR__ . '/includes/admin-header.php';
?>

<div class="admin-content">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
        <h1 class="admin-title" style="margin-bottom:0;">
            <?= e($chatbot['name']) ?>
            <?php if ($chatbot['is_active']): ?>
            <span class="badge badge-success" style="vertical-align:middle;margin-left:8px;">En ligne</span>
            <?php else: ?>
            <span class="badge badge-error" style="vertical-align:middle;margin-left:8px;">Hors ligne</span>
            <?php endif; ?>
        </h1>
        <div style="display:flex;gap:8px;">
            <a href="chatbot-intentions.php?chatbot_id=<?= $id ?>" class="btn btn-outline">Intentions</a>
            <a href="chatbot-stats.php?chatbot_id=<?= $id ?>" class="btn btn-outline">Stats</a>
            <a href="dashboard.php" class="btn btn-outline">&larr; Dashboard</a>
        </div>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;">
        <!-- Main form -->
        <div>
            <form method="POST">
                <input type="hidden" name="save" value="1">

                <!-- General -->
                <div class="admin-section">
                    <h2 style="font-size:18px;margin-bottom:16px;">Configuration generale</h2>

                    <div class="form-group">
                        <label for="name">Nom du chatbot *</label>
                        <input type="text" id="name" name="name" class="form-control" required
                               value="<?= e($chatbot['name']) ?>">
                    </div>

                    <div class="form-group">
                        <label for="domain">Domaine autorise</label>
                        <input type="text" id="domain" name="domain" class="form-control"
                               placeholder="www.monsite.fr"
                               value="<?= e($chatbot['domain'] ?? '') ?>">
                        <small>Laisser vide pour autoriser tous les domaines.</small>
                    </div>

                    <div class="form-group">
                        <label for="welcome_message">Message de bienvenue</label>
                        <textarea id="welcome_message" name="welcome_message" class="form-control" rows="3"><?= e($chatbot['welcome_message'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- Apparence -->
                <div class="admin-section">
                    <h2 style="font-size:18px;margin-bottom:16px;">Apparence</h2>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                        <div class="form-group">
                            <label for="primary_color">Couleur principale</label>
                            <div style="display:flex;gap:8px;align-items:center;">
                                <input type="color" id="primary_color" name="primary_color"
                                       value="<?= e($chatbot['primary_color'] ?? '#1a5653') ?>"
                                       style="width:50px;height:38px;border:1px solid #ddd;border-radius:6px;cursor:pointer;padding:2px;">
                                <input type="text" id="primary_color_text" class="form-control" style="width:120px;"
                                       value="<?= e($chatbot['primary_color'] ?? '#1a5653') ?>"
                                       oninput="document.getElementById('primary_color').value=this.value">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="logo_url">URL du logo</label>
                            <input type="url" id="logo_url" name="logo_url" class="form-control"
                                   placeholder="https://monsite.fr/logo.png"
                                   value="<?= e($chatbot['logo_url'] ?? '') ?>">
                        </div>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                        <div class="form-group">
                            <label style="display:flex;align-items:center;gap:8px;">
                                <input type="checkbox" name="auto_popup" value="1" <?= $chatbot['auto_popup'] ? 'checked' : '' ?>>
                                Popup automatique
                            </label>
                        </div>
                        <div class="form-group">
                            <label for="popup_delay">Delai popup (secondes)</label>
                            <input type="number" id="popup_delay" name="popup_delay" class="form-control"
                                   min="5" max="120" value="<?= intval($chatbot['popup_delay'] ?? 20) ?>">
                        </div>
                    </div>
                </div>

                <!-- IA -->
                <div class="admin-section">
                    <h2 style="font-size:18px;margin-bottom:16px;">Intelligence Artificielle</h2>

                    <div class="form-group">
                        <label for="ai_provider">Provider IA</label>
                        <select id="ai_provider" name="ai_provider" class="form-control">
                            <option value="none" <?= ($chatbot['ai_provider'] ?? 'none') === 'none' ? 'selected' : '' ?>>Desactive</option>
                            <option value="openai" <?= ($chatbot['ai_provider'] ?? '') === 'openai' ? 'selected' : '' ?>>OpenAI</option>
                            <option value="anthropic" <?= ($chatbot['ai_provider'] ?? '') === 'anthropic' ? 'selected' : '' ?>>Anthropic</option>
                        </select>
                    </div>

                    <div style="display:grid;grid-template-columns:2fr 1fr;gap:16px;">
                        <div class="form-group">
                            <label for="ai_api_key">Cle API</label>
                            <input type="password" id="ai_api_key" name="ai_api_key" class="form-control"
                                   placeholder="sk-..."
                                   value="<?= e($chatbot['ai_api_key'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="ai_model">Modele</label>
                            <input type="text" id="ai_model" name="ai_model" class="form-control"
                                   value="<?= e($chatbot['ai_model'] ?? 'gpt-4') ?>">
                        </div>
                    </div>
                </div>

                <!-- Webhook -->
                <div class="admin-section">
                    <h2 style="font-size:18px;margin-bottom:16px;">Webhook / Notifications</h2>

                    <div class="form-group">
                        <label style="display:flex;align-items:center;gap:8px;">
                            <input type="checkbox" name="webhook_enabled" value="1" <?= ($chatbot['webhook_enabled'] ?? 0) ? 'checked' : '' ?>>
                            Activer le webhook (n8n, Zapier, Make...)
                        </label>
                    </div>
                    <div class="form-group">
                        <label for="webhook_url">URL du webhook</label>
                        <input type="url" id="webhook_url" name="webhook_url" class="form-control"
                               placeholder="https://n8n.monserveur.com/webhook/..."
                               value="<?= e($chatbot['webhook_url'] ?? '') ?>">
                    </div>

                    <hr style="border:none;border-top:1px solid #eee;margin:16px 0;">

                    <div class="form-group">
                        <label style="display:flex;align-items:center;gap:8px;">
                            <input type="checkbox" name="email_notifications" value="1" <?= ($chatbot['email_notifications'] ?? 1) ? 'checked' : '' ?>>
                            Notifications par email
                        </label>
                    </div>
                    <div class="form-group">
                        <label for="notification_email">Email de notification</label>
                        <input type="email" id="notification_email" name="notification_email" class="form-control"
                               placeholder="admin@monsite.fr"
                               value="<?= e($chatbot['notification_email'] ?? '') ?>">
                    </div>
                </div>

                <!-- BDD Externe -->
                <div class="admin-section">
                    <h2 style="font-size:18px;margin-bottom:16px;">Base de donnees externe (produits)</h2>
                    <p style="font-size:13px;color:var(--color-gray);margin-bottom:16px;">
                        Connectez le chatbot a la base de donnees de votre client pour qu'il puisse rechercher ses produits (maisons, terrains, vehicules, etc.)
                    </p>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="ext_db_enabled" value="1" <?= ($chatbot['ext_db_enabled'] ?? 0) ? 'checked' : '' ?>>
                            Activer la connexion BDD externe
                        </label>
                        <?php if ($chatbot['ext_db_enabled'] ?? 0): ?>
                        <a href="import-excel.php?chatbot_id=<?= $id ?>" class="btn btn-outline btn-sm" style="margin-left:12px;">Importer un fichier Excel</a>
                        <?php endif; ?>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div class="form-group">
                            <label for="ext_db_host">Host</label>
                            <input type="text" id="ext_db_host" name="ext_db_host" class="form-control"
                                   value="<?= e($chatbot['ext_db_host'] ?? 'localhost') ?>" placeholder="localhost">
                        </div>
                        <div class="form-group">
                            <label for="ext_db_name">Nom de la base</label>
                            <input type="text" id="ext_db_name" name="ext_db_name" class="form-control"
                                   value="<?= e($chatbot['ext_db_name'] ?? '') ?>" placeholder="nom_base_client">
                        </div>
                        <div class="form-group">
                            <label for="ext_db_user">Utilisateur</label>
                            <input type="text" id="ext_db_user" name="ext_db_user" class="form-control"
                                   value="<?= e($chatbot['ext_db_user'] ?? '') ?>" placeholder="root">
                        </div>
                        <div class="form-group">
                            <label for="ext_db_pass">Mot de passe</label>
                            <input type="password" id="ext_db_pass" name="ext_db_pass" class="form-control"
                                   value="<?= e($chatbot['ext_db_pass'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="ext_db_products">Configuration des produits (JSON)</label>
                        <textarea id="ext_db_products" name="ext_db_products" class="form-control" rows="12"
                                  style="font-family:'SF Mono','Fira Code',monospace;font-size:12px;"
                                  placeholder='[{"type":"maison","label":"Maisons","table":"modeles","col_name":"nom","col_price":"prix_base","col_description":"slogan","col_surface":"surface_habitable","col_category":"nb_etages","col_active":"is_active","col_extra":["nb_chambres"],"search_keywords":["maison","modele","construire"]}]'><?= e($chatbot['ext_db_products'] ?? '') ?></textarea>
                        <small>Format : tableau JSON. Chaque objet = un type de produit a rechercher.</small>
                        <details style="margin-top:8px;font-size:12px;color:var(--color-gray);">
                            <summary style="cursor:pointer;font-weight:600;">Voir les champs disponibles</summary>
                            <ul style="margin-top:8px;line-height:2;">
                                <li><code>type</code> : identifiant (maison, terrain, vehicule...)</li>
                                <li><code>label</code> : nom affiche ("Maisons", "Terrains"...)</li>
                                <li><code>table</code> : nom de la table SQL</li>
                                <li><code>col_name</code> : colonne du nom du produit</li>
                                <li><code>col_price</code> : colonne du prix</li>
                                <li><code>col_description</code> : colonne description</li>
                                <li><code>col_location</code> : colonne localisation (dept, ville)</li>
                                <li><code>col_surface</code> : colonne surface</li>
                                <li><code>col_category</code> : colonne categorie/type</li>
                                <li><code>col_active</code> : colonne actif (1/0)</li>
                                <li><code>col_extra</code> : colonnes supplementaires (tableau)</li>
                                <li><code>search_keywords</code> : mots-cles pour detecter ce type dans les messages</li>
                            </ul>
                        </details>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="padding:12px 32px;">Enregistrer les modifications</button>
            </form>
        </div>

        <!-- Sidebar -->
        <div>
            <!-- Integration code -->
            <div class="admin-section">
                <h2 style="font-size:16px;margin-bottom:16px;">Code d'integration</h2>

                <div style="margin-bottom:16px;">
                    <label style="font-size:13px;font-weight:600;margin-bottom:6px;display:block;">Widget flottant (recommande)</label>
                    <div class="code-block">
                        <button class="copy-btn" onclick="copyToClipboard(document.getElementById('code-widget').textContent, this)">Copier</button>
                        <code id="code-widget">&lt;script src="<?= FB_BASE_URL ?>/api/v1/embed.js.php?token=<?= e($chatbot['token']) ?>"&gt;&lt;/script&gt;</code>
                    </div>
                </div>

                <div style="margin-bottom:16px;">
                    <label style="font-size:13px;font-weight:600;margin-bottom:6px;display:block;">Iframe integre</label>
                    <div class="code-block">
                        <button class="copy-btn" onclick="copyToClipboard(document.getElementById('code-iframe').textContent, this)">Copier</button>
                        <code id="code-iframe">&lt;iframe src="<?= FB_BASE_URL ?>/api/v1/chat.php?token=<?= e($chatbot['token']) ?>&amp;mode=inline" width="100%" height="600" frameborder="0"&gt;&lt;/iframe&gt;</code>
                    </div>
                </div>

                <div>
                    <label style="font-size:13px;font-weight:600;margin-bottom:6px;display:block;">Plein ecran</label>
                    <div class="code-block">
                        <button class="copy-btn" onclick="copyToClipboard(document.getElementById('code-full').textContent, this)">Copier</button>
                        <code id="code-full">&lt;iframe src="<?= FB_BASE_URL ?>/api/v1/chat.php?token=<?= e($chatbot['token']) ?>&amp;mode=fullpage" width="100%" height="100%" style="position:fixed;inset:0;border:none;z-index:9999;"&gt;&lt;/iframe&gt;</code>
                    </div>
                </div>
            </div>

            <!-- Token info -->
            <div class="admin-section">
                <h2 style="font-size:16px;margin-bottom:16px;">Informations</h2>

                <div style="margin-bottom:12px;">
                    <label style="font-size:12px;color:var(--color-gray);display:block;">Token public</label>
                    <code style="font-size:13px;background:#f0f0f0;padding:4px 8px;border-radius:4px;word-break:break-all;"><?= e($chatbot['token']) ?></code>
                </div>

                <div style="margin-bottom:12px;">
                    <label style="font-size:12px;color:var(--color-gray);display:block;">Secret key</label>
                    <code style="font-size:13px;background:#f0f0f0;padding:4px 8px;border-radius:4px;word-break:break-all;"><?= e(substr($chatbot['secret_key'], 0, 8)) ?>...****</code>
                </div>

                <div style="margin-bottom:12px;">
                    <label style="font-size:12px;color:var(--color-gray);display:block;">Cree le</label>
                    <span style="font-size:13px;"><?= dateFR($chatbot['created_at']) ?></span>
                </div>

                <div style="margin-bottom:16px;">
                    <label style="font-size:12px;color:var(--color-gray);display:block;">Derniere modification</label>
                    <span style="font-size:13px;"><?= dateFR($chatbot['updated_at']) ?></span>
                </div>

                <form method="POST" style="margin-bottom:8px;">
                    <button type="submit" name="regenerate_token" value="1" class="btn btn-outline btn-sm" style="width:100%;"
                            onclick="return confirm('Regenerer le token ? Les integrations existantes cesseront de fonctionner.')">
                        Regenerer le token
                    </button>
                </form>

                <form method="POST">
                    <?php if ($chatbot['is_active']): ?>
                    <button type="submit" name="toggle_active" value="1" class="btn btn-danger btn-sm" style="width:100%;"
                            onclick="return confirm('Desactiver ce chatbot ?')">
                        Desactiver le chatbot
                    </button>
                    <?php else: ?>
                    <button type="submit" name="toggle_active" value="1" class="btn btn-primary btn-sm" style="width:100%;">
                        Activer le chatbot
                    </button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('primary_color').addEventListener('input', function() {
    document.getElementById('primary_color_text').value = this.value;
});
</script>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
