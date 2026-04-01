<?php
/**
 * Admin - Centre d'apprentissage du Chatbot
 * CRUD intentions + test live + messages non reconnus + stats
 */
require_once '../includes/config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit;
}

$page_title = 'Chatbot - Centre d\'apprentissage';
$message = '';
$error = '';

// --- Actions ---

// Ajouter/Modifier une intention
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_intention'])) {
    $id = intval($_POST['id'] ?? 0);
    $intention_key = strtolower(trim($_POST['intention_key'] ?? ''));
    $keywords = trim($_POST['keywords'] ?? '');
    $response_text = trim($_POST['response_text'] ?? '');
    $action = trim($_POST['action'] ?? '');
    $priority = intval($_POST['priority'] ?? 5);
    $category = trim($_POST['category'] ?? 'general');

    if (empty($intention_key) || empty($keywords) || empty($response_text)) {
        $error = 'Intention, mots-clés et réponse sont obligatoires.';
    } else {
        // Vérifier conflits de mots-clés
        $newKeywords = array_map('trim', explode(',', strtolower($keywords)));
        $conflicts = [];
        $stmt = $pdo->prepare("SELECT id, intention_key, keywords FROM chatbot_intentions WHERE id != ?");
        $stmt->execute([$id]);
        foreach ($stmt->fetchAll() as $existing) {
            $existingKw = array_map('trim', explode(',', strtolower($existing['keywords'])));
            $overlap = array_intersect($newKeywords, $existingKw);
            if (!empty($overlap)) {
                $conflicts[] = "\"" . implode(', ', $overlap) . "\" déjà dans <strong>" . htmlspecialchars($existing['intention_key']) . "</strong>";
            }
        }

        if (!empty($conflicts) && !isset($_POST['force_save'])) {
            $error = '⚠️ Conflits de mots-clés détectés : ' . implode(' | ', $conflicts) . '<br><small>Cochez "Forcer" pour sauvegarder quand même.</small>';
        } else {
            try {
                if ($id) {
                    $pdo->prepare("UPDATE chatbot_intentions SET intention_key=?, keywords=?, response_text=?, action=?, priority=? WHERE id=?")
                        ->execute([$intention_key, $keywords, $response_text, $action, $priority, $id]);
                    $message = 'Intention modifiée ✅';
                } else {
                    $pdo->prepare("INSERT INTO chatbot_intentions (intention_key, keywords, response_text, action, priority) VALUES (?, ?, ?, ?, ?)")
                        ->execute([$intention_key, $keywords, $response_text, $action, $priority]);
                    $message = 'Nouvelle intention ajoutée ! Le chatbot a appris 🎉';
                }
            } catch (PDOException $e) {
                $error = 'Erreur : ' . $e->getMessage();
            }
        }
    }
}

// Test live (AJAX)
if (isset($_POST['test_message'])) {
    header('Content-Type: application/json');
    $testMsg = trim($_POST['test_message']);
    $result = chatbotDetectIntention($testMsg);
    echo json_encode([
        'found' => $result !== null,
        'key' => $result['key'] ?? null,
        'response' => $result['response'] ?? null,
        'action' => $result['action'] ?? null
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Supprimer
if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM chatbot_intentions WHERE id = ?")->execute([intval($_GET['delete'])]);
    header('Location: chatbot-intentions.php?deleted=1');
    exit;
}

// Toggle actif/inactif
if (isset($_GET['toggle'])) {
    $pdo->prepare("UPDATE chatbot_intentions SET is_active = NOT is_active WHERE id = ?")->execute([intval($_GET['toggle'])]);
    header('Location: chatbot-intentions.php');
    exit;
}

// --- Données ---

// Toutes les intentions
$intentions = $pdo->query("SELECT * FROM chatbot_intentions ORDER BY priority DESC, intention_key ASC")->fetchAll();

// Intention en cours d'édition
$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM chatbot_intentions WHERE id = ?");
    $stmt->execute([intval($_GET['edit'])]);
    $edit = $stmt->fetch();
}

// Messages non reconnus (top 20 les plus fréquents)
// Exclut : valeurs de boutons, navigation, réponses trop courtes
$unrecognized = [];
try {
    $unrecognized = $pdo->query("SELECT message, COUNT(*) as count
        FROM chatbot_messages
        WHERE type = 'user'
        AND (intention_detected IS NULL OR intention_detected = '')
        AND LENGTH(TRIM(message)) > 3
        AND LOWER(TRIM(message)) NOT IN ('go_maison','go_terrain','go_prix','go_question','go_form','go_form','autre','coord','fermer','voir_modeles','oui','non')
        AND LOWER(TRIM(message)) NOT REGEXP '^[0-9]+$'
        AND LOWER(TRIM(message)) NOT IN ('plain-pied','1-etage','tous','2','3','4','plat','en_pente','boise','constructible','oui','non','recherche',
            'devis','modeles','rdv','maison','terrain','question','60','77','95','02','80',
            '155000','185000','220000','250000','50000','80000','120000','150000','999999')
        GROUP BY message
        ORDER BY count DESC
        LIMIT 20")->fetchAll();
} catch (Exception $e) {}

// Stats par intention (nombre de déclenchements)
$intentionStats = [];
try {
    $intentionStats = $pdo->query("SELECT intention_detected as ikey, COUNT(*) as triggers,
        SUM(CASE WHEN c.lead_id IS NOT NULL THEN 1 ELSE 0 END) as leads
        FROM chatbot_messages m
        LEFT JOIN chatbot_conversations c ON m.conversation_id = c.id
        WHERE m.intention_detected IS NOT NULL AND m.intention_detected != ''
        GROUP BY m.intention_detected
        ORDER BY triggers DESC")->fetchAll();
    $intentionStats = array_column($intentionStats, null, 'ikey');
} catch (Exception $e) {}

// Compteurs
$totalIntentions = count($intentions);
$activeIntentions = count(array_filter($intentions, fn($i) => $i['is_active']));
$totalKeywords = 0;
foreach ($intentions as $i) {
    $totalKeywords += count(array_map('trim', explode(',', $i['keywords'])));
}

include 'includes/admin-header.php';
?>

<div class="admin-content">
    <h1 class="admin-title">🎓 Centre d'apprentissage</h1>

    <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?><div class="alert alert-success">Intention supprimée.</div><?php endif; ?>

    <!-- Stats rapides -->
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:15px;margin-bottom:25px;">
        <div class="admin-section" style="padding:15px;text-align:center;">
            <div style="font-size:28px;font-weight:700;color:var(--color-primary);"><?php echo $totalIntentions; ?></div>
            <div style="font-size:12px;color:#888;">Intentions</div>
        </div>
        <div class="admin-section" style="padding:15px;text-align:center;">
            <div style="font-size:28px;font-weight:700;color:#27ae60;"><?php echo $activeIntentions; ?></div>
            <div style="font-size:12px;color:#888;">Actives</div>
        </div>
        <div class="admin-section" style="padding:15px;text-align:center;">
            <div style="font-size:28px;font-weight:700;color:#3498db;"><?php echo $totalKeywords; ?></div>
            <div style="font-size:12px;color:#888;">Mots-clés</div>
        </div>
        <div class="admin-section" style="padding:15px;text-align:center;">
            <div style="font-size:28px;font-weight:700;color:#e74c3c;"><?php echo count($unrecognized); ?></div>
            <div style="font-size:12px;color:#888;">Non reconnus</div>
        </div>
    </div>

    <!-- Zone de test live -->
    <div class="admin-section" style="margin-bottom:25px;background:linear-gradient(135deg,#1a5653,#0f3d3a);color:#fff;border-radius:12px;">
        <h2 style="font-size:18px;margin-bottom:15px;color:#fff;">🧪 Tester le chatbot</h2>
        <p style="font-size:13px;opacity:.8;margin-bottom:15px;">Tapez un message comme le ferait un visiteur. Vous verrez instantanément comment le chatbot répondrait.</p>
        <div style="display:flex;gap:10px;">
            <input type="text" id="test-input" placeholder="Ex: combien coûte une maison 3 chambres ?" style="flex:1;padding:12px 16px;border:none;border-radius:8px;font-size:14px;outline:none;" onkeypress="if(event.key==='Enter')testChatbot()">
            <button onclick="testChatbot()" style="padding:12px 24px;background:#fff;color:#1a5653;border:none;border-radius:8px;cursor:pointer;font-weight:700;font-size:14px;">Tester</button>
        </div>
        <div id="test-result" style="display:none;margin-top:15px;padding:15px;background:rgba(255,255,255,0.1);border-radius:8px;">
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 2fr;gap:25px;">
        <!-- Colonne gauche : Formulaire + Messages non reconnus -->
        <div>
            <!-- Formulaire ajout/modif -->
            <div class="admin-section" style="margin-bottom:25px;">
                <h2 style="font-size:16px;margin-bottom:15px;">
                    <?php echo $edit ? '✏️ Modifier l\'intention' : '➕ Ajouter une connaissance'; ?>
                </h2>

                <form method="POST">
                    <input type="hidden" name="save_intention" value="1">
                    <?php if ($edit): ?>
                    <input type="hidden" name="id" value="<?php echo $edit['id']; ?>">
                    <?php endif; ?>

                    <div style="margin-bottom:12px;">
                        <label style="display:block;margin-bottom:4px;font-weight:500;font-size:13px;">Nom de l'intention</label>
                        <input type="text" name="intention_key" class="form-control" required
                            value="<?php echo htmlspecialchars($edit['intention_key'] ?? ''); ?>"
                            placeholder="ex: prix_terrain, objection_delai..."
                            style="width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:6px;font-size:13px;">
                    </div>

                    <div style="margin-bottom:12px;">
                        <label style="display:block;margin-bottom:4px;font-weight:500;font-size:13px;">Mots-clés <small style="color:#999;">(séparés par des virgules)</small></label>
                        <textarea name="keywords" class="form-control" rows="3" required
                            style="width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:6px;font-size:13px;"
                            placeholder="prix terrain, cout terrain, combien terrain..."><?php echo htmlspecialchars($edit['keywords'] ?? ''); ?></textarea>
                    </div>

                    <div style="margin-bottom:12px;">
                        <label style="display:block;margin-bottom:4px;font-weight:500;font-size:13px;">Réponse du chatbot</label>
                        <textarea name="response_text" id="response-text" class="form-control" rows="5" required
                            style="width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:6px;font-size:13px;font-family:inherit;"
                            oninput="previewResponse()"
                            placeholder="Utilisez **gras** et des retours à la ligne..."><?php echo htmlspecialchars($edit['response_text'] ?? ''); ?></textarea>
                        <div id="response-preview" style="margin-top:8px;padding:10px;background:#f5f7f9;border-radius:6px;font-size:13px;line-height:1.5;display:none;"></div>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px;">
                        <div>
                            <label style="display:block;margin-bottom:4px;font-weight:500;font-size:13px;">Action</label>
                            <select name="action" id="action-select" class="form-control" onchange="toggleCustomUrl()" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;font-size:13px;">
                                <optgroup label="Répondre">
                                    <option value="">💬 Répondre seulement</option>
                                </optgroup>
                                <optgroup label="Lancer un parcours">
                                    <option value="scenario_devis" <?php echo ($edit['action'] ?? '') === 'scenario_devis' ? 'selected' : ''; ?>>📊 Lancer le devis</option>
                                    <option value="scenario_terrain" <?php echo ($edit['action'] ?? '') === 'scenario_terrain' ? 'selected' : ''; ?>>🌿 Chercher un terrain</option>
                                    <option value="afficher_modeles" <?php echo ($edit['action'] ?? '') === 'afficher_modeles' ? 'selected' : ''; ?>>🏠 Voir les modèles</option>
                                    <option value="transfert_humain" <?php echo ($edit['action'] ?? '') === 'transfert_humain' ? 'selected' : ''; ?>>📋 Formulaire de contact</option>
                                </optgroup>
                                <optgroup label="Lien vers une page du site">
                                    <option value="link:/modeles.php" <?php echo ($edit['action'] ?? '') === 'link:/modeles.php' ? 'selected' : ''; ?>>🏠 Page Modèles</option>
                                    <option value="link:/engagements.php" <?php echo ($edit['action'] ?? '') === 'link:/engagements.php' ? 'selected' : ''; ?>>✅ Page Engagements / Garanties</option>
                                    <option value="link:/constructeur.php" <?php echo ($edit['action'] ?? '') === 'link:/constructeur.php' ? 'selected' : ''; ?>>🏗️ Page Constructeur</option>
                                    <option value="link:/contact.php" <?php echo ($edit['action'] ?? '') === 'link:/contact.php' ? 'selected' : ''; ?>>📞 Page Contact</option>
                                    <option value="link:/faq.php" <?php echo ($edit['action'] ?? '') === 'link:/faq.php' ? 'selected' : ''; ?>>❓ Page FAQ</option>
                                    <option value="link:/blog.php" <?php echo ($edit['action'] ?? '') === 'link:/blog.php' ? 'selected' : ''; ?>>📰 Page Actualités</option>
                                    <option value="link:/estimation.php" <?php echo ($edit['action'] ?? '') === 'link:/estimation.php' ? 'selected' : ''; ?>>💰 Page Estimation</option>
                                    <option value="custom_link" <?php echo (strpos($edit['action'] ?? '', 'link:') === 0 && !in_array($edit['action'] ?? '', ['link:/modeles.php','link:/engagements.php','link:/constructeur.php','link:/contact.php','link:/faq.php','link:/blog.php','link:/estimation.php'])) ? 'selected' : ''; ?>>🔗 URL personnalisée...</option>
                                </optgroup>
                                <optgroup label="Autre">
                                    <option value="close" <?php echo ($edit['action'] ?? '') === 'close' ? 'selected' : ''; ?>>❌ Fermer le chat</option>
                                </optgroup>
                            </select>
                            <div id="custom-url-field" style="display:none;margin-top:6px;">
                                <input type="text" name="custom_url" id="custom-url-input" class="form-control"
                                    placeholder="/ma-page.php ou https://..."
                                    value="<?php echo htmlspecialchars(str_replace('link:', '', $edit['action'] ?? '')); ?>"
                                    style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;font-size:13px;">
                                <small style="color:#888;">URL relative (/page.php) ou absolue (https://...)</small>
                            </div>
                        </div>
                        <div>
                            <label style="display:block;margin-bottom:4px;font-weight:500;font-size:13px;">Priorité</label>
                            <input type="number" name="priority" class="form-control" min="1" max="10"
                                value="<?php echo $edit['priority'] ?? 5; ?>"
                                style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;font-size:13px;">
                        </div>
                    </div>

                    <?php if ($error && strpos($error, 'Conflits') !== false): ?>
                    <label style="display:flex;align-items:center;gap:8px;margin-bottom:12px;font-size:13px;color:#e74c3c;">
                        <input type="checkbox" name="force_save" value="1"> Forcer la sauvegarde malgré les conflits
                    </label>
                    <?php endif; ?>

                    <button type="submit" class="btn btn-primary" style="width:100%;">
                        <?php echo $edit ? '💾 Enregistrer' : '➕ Ajouter'; ?>
                    </button>

                    <?php if ($edit): ?>
                    <a href="chatbot-intentions.php" class="btn btn-outline" style="width:100%;margin-top:8px;display:block;text-align:center;">Annuler</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Messages non reconnus -->
            <div class="admin-section">
                <h2 style="font-size:16px;margin-bottom:15px;">🔴 Messages non reconnus</h2>
                <p style="font-size:12px;color:#888;margin-bottom:15px;">Les questions les plus fréquentes auxquelles le chatbot n'a pas su répondre.</p>

                <?php if (empty($unrecognized)): ?>
                <p style="color:#27ae60;text-align:center;padding:20px;">✅ Tout est couvert ! Aucun message non reconnu.</p>
                <?php else: ?>
                <div style="display:flex;flex-direction:column;gap:8px;">
                    <?php foreach ($unrecognized as $u): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 12px;background:#fff5f5;border-radius:6px;border-left:3px solid #e74c3c;">
                        <div style="flex:1;">
                            <div style="font-size:13px;color:#333;">"<?php echo htmlspecialchars(substr($u['message'], 0, 60)); ?>"</div>
                            <div style="font-size:11px;color:#999;"><?php echo $u['count']; ?>× posé</div>
                        </div>
                        <button onclick="prefillFromMessage('<?php echo addslashes(htmlspecialchars($u['message'])); ?>')" class="btn btn-sm btn-primary" style="white-space:nowrap;">Apprendre</button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Colonne droite : Liste des intentions avec stats -->
        <div class="admin-section">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;">
                <h2 style="font-size:16px;">📚 Connaissances actuelles (<?php echo $totalIntentions; ?>)</h2>
                <input type="text" id="search-intentions" placeholder="🔍 Rechercher..." oninput="filterIntentions()"
                    style="padding:6px 12px;border:1px solid #ddd;border-radius:6px;font-size:13px;width:200px;">
            </div>

            <div style="overflow-x:auto;">
                <table class="admin-table" id="intentions-table">
                    <thead>
                        <tr>
                            <th style="width:120px;">Intention</th>
                            <th style="width:150px;">Mots-clés</th>
                            <th>Réponse</th>
                            <th style="width:50px;">Prio</th>
                            <th style="width:80px;">Stats</th>
                            <th style="width:60px;">Statut</th>
                            <th style="width:140px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($intentions as $int):
                            $stats = $intentionStats[$int['intention_key']] ?? null;
                            $triggers = $stats['triggers'] ?? 0;
                            $leads = $stats['leads'] ?? 0;
                            $convRate = $triggers > 0 ? round(($leads / $triggers) * 100) : 0;
                        ?>
                        <tr class="intention-row" data-search="<?php echo strtolower($int['intention_key'] . ' ' . $int['keywords']); ?>">
                            <td>
                                <strong style="font-size:13px;"><?php echo htmlspecialchars($int['intention_key']); ?></strong>
                                <?php if ($int['action']): ?>
                                <div style="font-size:10px;color:#1a5653;">→ <?php echo htmlspecialchars($int['action']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-size:12px;color:#666;max-width:150px;overflow:hidden;text-overflow:ellipsis;">
                                    <?php
                                    $words = array_map('trim', explode(',', $int['keywords']));
                                    $shown = array_slice($words, 0, 4);
                                    echo htmlspecialchars(implode(', ', $shown));
                                    if (count($words) > 4) echo ' <span style="color:#999;">+' . (count($words) - 4) . '</span>';
                                    ?>
                                </div>
                            </td>
                            <td>
                                <div style="font-size:12px;color:#555;max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo htmlspecialchars($int['response_text']); ?>">
                                    <?php echo htmlspecialchars(substr($int['response_text'], 0, 80)); ?>...
                                </div>
                            </td>
                            <td style="text-align:center;">
                                <span style="background:#f0f0f0;padding:2px 8px;border-radius:10px;font-size:12px;font-weight:600;"><?php echo $int['priority']; ?></span>
                            </td>
                            <td style="text-align:center;">
                                <?php if ($triggers > 0): ?>
                                <div style="font-size:12px;font-weight:600;"><?php echo $triggers; ?>×</div>
                                <div style="font-size:10px;color:<?php echo $convRate > 20 ? '#27ae60' : '#999'; ?>;"><?php echo $convRate; ?>% conv.</div>
                                <?php else: ?>
                                <span style="font-size:11px;color:#ccc;">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;">
                                <a href="?toggle=<?php echo $int['id']; ?>" title="<?php echo $int['is_active'] ? 'Désactiver' : 'Activer'; ?>">
                                    <?php echo $int['is_active'] ? '<span style="color:#27ae60;font-size:18px;">●</span>' : '<span style="color:#ccc;font-size:18px;">○</span>'; ?>
                                </a>
                            </td>
                            <td>
                                <a href="?edit=<?php echo $int['id']; ?>" class="btn btn-sm btn-primary" style="font-size:11px;">Modifier</a>
                                <a href="?delete=<?php echo $int['id']; ?>" class="btn btn-sm" style="font-size:11px;background:#fee;color:#c00;border:1px solid #fcc;" onclick="return confirm('Supprimer ?')">×</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if (empty($intentions)): ?>
            <p style="color:#999;text-align:center;padding:40px;">Aucune intention. Commencez par ajouter votre première connaissance !</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// --- Test live du chatbot ---
function testChatbot() {
    var input = document.getElementById('test-input');
    var msg = input.value.trim();
    if (!msg) return;

    var result = document.getElementById('test-result');
    result.style.display = 'block';
    result.innerHTML = '<span style="opacity:.6;">Analyse en cours...</span>';

    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'chatbot-intentions.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            try {
                var d = JSON.parse(xhr.responseText);
                if (d.found) {
                    var resp = d.response.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>').replace(/\n/g, '<br>');
                    result.innerHTML = '<div style="margin-bottom:8px;"><span style="background:#27ae60;color:#fff;padding:2px 8px;border-radius:4px;font-size:11px;">✅ Détecté : ' + d.key + '</span>' +
                        (d.action ? ' <span style="background:#3498db;color:#fff;padding:2px 8px;border-radius:4px;font-size:11px;">→ ' + d.action + '</span>' : '') +
                        '</div><div style="font-size:13px;line-height:1.5;">' + resp + '</div>';
                } else {
                    result.innerHTML = '<span style="background:#e74c3c;color:#fff;padding:2px 8px;border-radius:4px;font-size:11px;">❌ Non reconnu</span>' +
                        '<div style="margin-top:8px;font-size:13px;opacity:.8;">Le chatbot ne sait pas répondre à ce message. Ajoutez une intention ci-dessous !</div>';
                }
            } catch(e) {
                result.innerHTML = '<span style="color:#e74c3c;">Erreur de parsing</span>';
            }
        }
    };
    xhr.send('test_message=' + encodeURIComponent(msg));
}

// --- Prévisualisation de la réponse ---
function previewResponse() {
    var text = document.getElementById('response-text').value;
    var preview = document.getElementById('response-preview');
    if (text.length < 5) { preview.style.display = 'none'; return; }
    preview.style.display = 'block';
    var safe = text.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    preview.innerHTML = '<div style="font-size:11px;color:#999;margin-bottom:5px;">Aperçu :</div>' +
        safe.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>').replace(/\n/g, '<br>');
}

// --- Pré-remplir depuis un message non reconnu ---
function prefillFromMessage(msg) {
    // Extraire des mots-clés du message
    var words = msg.toLowerCase().replace(/[^a-zàâäéèêëïîôùûüÿçœæ\s]/g, '').split(/\s+/).filter(function(w) {
        return w.length > 3 && ['dans','avec','pour','chez','nous','vous','votre','cette','est','les','des','une'].indexOf(w) === -1;
    });
    var keywordsField = document.querySelector('textarea[name="keywords"]');
    keywordsField.value = words.join(', ');
    keywordsField.focus();
    window.scrollTo({top: 0, behavior: 'smooth'});
}

// --- Recherche dans les intentions ---
function filterIntentions() {
    var search = document.getElementById('search-intentions').value.toLowerCase();
    var rows = document.querySelectorAll('.intention-row');
    rows.forEach(function(row) {
        row.style.display = row.getAttribute('data-search').indexOf(search) !== -1 ? '' : 'none';
    });
}

// Toggle URL personnalisée
function toggleCustomUrl() {
    var sel = document.getElementById('action-select');
    var field = document.getElementById('custom-url-field');
    field.style.display = sel.value === 'custom_link' ? 'block' : 'none';
}

// Init
if (document.getElementById('response-text').value) previewResponse();
<?php if ($edit && strpos($edit['action'] ?? '', 'link:') === 0): ?>
toggleCustomUrl();
<?php endif; ?>

// Avant soumission : construire l'action finale
document.querySelector('form').addEventListener('submit', function(e) {
    var sel = document.getElementById('action-select');
    if (sel.value === 'custom_link') {
        var url = document.getElementById('custom-url-input').value.trim();
        if (url) {
            // Créer un champ hidden avec la vraie valeur
            var h = document.createElement('input');
            h.type = 'hidden'; h.name = 'action'; h.value = 'link:' + url;
            this.appendChild(h);
            sel.name = ''; // désactiver le select
        }
    }
});
</script>

<?php include 'includes/admin-footer.php'; ?>
