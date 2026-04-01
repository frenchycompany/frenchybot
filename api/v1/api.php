<?php
/**
 * API Chatbot ORCA - Scénarios complets + recherche BDD
 */
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

// Charger les réglages chatbot
$chatbot_config = [];
try {
    $stmt = $pdo->query("SELECT cle, valeur FROM config WHERE cle LIKE 'chatbot_%'");
    while ($row = $stmt->fetch()) $chatbot_config[$row['cle']] = $row['valeur'];
} catch (Exception $e) {}

// Chatbot désactivé ?
if (($chatbot_config['chatbot_enabled'] ?? '1') === '0') {
    respond(['disabled' => true]);
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
switch ($action) {
    case 'init':    handleInit(); break;
    case 'message': handleMessage(); break;
    case 'form':    handleForm(); break;
    default:        respond(['error' => 'Action inconnue']);
}

// ======================================================
// INIT
// ======================================================
function handleInit() {
    $conv = chatbotGetOrCreateConversation();
    $scenario = chatbotGetScenario();

    if ($conv['is_new']) {
        $step = $scenario[1];
        $welcomeMsg = $step['message'];

        // A/B test sur le message de bienvenue
        $abTest = chatbotGetABVariant($conv['id']);
        if ($abTest && !empty($abTest['message'])) {
            $welcomeMsg = $abTest['message'];
        }

        chatbotSaveMessage($conv['id'], 'bot', $welcomeMsg, $step['options']);
        global $chatbot_config;
        respond([
            'conversation_id' => $conv['id'], 'step' => 1, 'type' => 'buttons',
            'message' => $welcomeMsg, 'options' => $step['options'], 'is_new' => true,
            'config' => [
                'auto_popup' => ($chatbot_config['chatbot_auto_popup'] ?? '1') === '1',
                'popup_delay' => intval($chatbot_config['chatbot_popup_delay'] ?? 20),
                'color' => $chatbot_config['chatbot_primary_color'] ?? '#1a5653'
            ]
        ]);
    }

    $history = chatbotGetHistory($conv['id']);
    $stepId = (int) $conv['current_step'];
    $stepDef = $scenario[$stepId] ?? null;
    respond([
        'conversation_id' => $conv['id'], 'step' => $stepId,
        'type' => $stepDef['type'] ?? 'text', 'history' => $history, 'is_new' => false
    ]);
}

// ======================================================
// MESSAGE
// ======================================================
function handleMessage() {
    $cid = intval($_POST['conversation_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');
    if (!$cid || $message === '') respond(['error' => 'Données manquantes']);

    chatbotSaveMessage($cid, 'user', $message);

    $conv = chatbotGetConversation($cid);
    if (!$conv) respond(['error' => 'Conversation introuvable']);

    $scenario = chatbotGetScenario();
    $stepId = (int) $conv['current_step'];
    $step = $scenario[$stepId] ?? null;
    $data = json_decode($conv['data_collected'] ?? '{}', true) ?: [];

    // --- 0. Collecte conversationnelle des coordonnées (étapes 50-53) ---
    if ($stepId >= 50 && $stepId <= 53 && $step && isset($step['field'])) {
        return handleCoordStep($cid, $message, $step, $stepId, $scenario);
    }

    // --- 1. Navigation directe (valeurs de boutons) ---
    $nav = ['go_maison'=>10, 'go_terrain'=>20, 'go_prix'=>30, 'go_question'=>40, 'go_form'=>50,
            'autre'=>40, 'coord'=>50, 'fermer'=>55];
    $val = mb_strtolower(trim($message));
    if (isset($nav[$val])) {
        chatbotMarkRecognized($cid, 'navigation');
        return goToStep($cid, $nav[$val], $scenario);
    }

    // --- 2. Si étape à boutons → matcher le clic ---
    if ($step && isset($step['options'])) {
        $matched = matchOption($message, $step['options']);
        if ($matched) {
            chatbotMarkRecognized($cid, 'scenario_step_' . $stepId);
            if (isset($step['field'])) {
                chatbotUpdateData($cid, $step['field'], $matched['value']);
            }
            $next = $matched['next'];

            if ($next === 'results_maison') return handleResultsMaison($cid, $scenario);
            if ($next === 'results_terrain') return handleResultsTerrain($cid, $scenario);

            return goToStep($cid, $next, $scenario);
        }
    }

    // --- 3. Extraction intelligente de critères multiples ---
    $criteria = chatbotExtractCriteria($message);
    $hasCriteria = !empty($criteria) && !empty(array_diff_key($criteria, ['_subject' => 1]));

    // Si on a un sujet + des critères concrets → recherche directe
    if ($hasCriteria) {
        $subject = $criteria['_subject'] ?? null;

        // Sauvegarder les critères extraits
        foreach ($criteria as $k => $v) {
            if ($k[0] !== '_') chatbotUpdateData($cid, $k, $v);
        }

        // Recherche terrain
        if ($subject === 'terrain' || (!$subject && isset($criteria['departement']) && !isset($criteria['nb_chambres']))) {
            chatbotMarkRecognized($cid, 'smart_search_terrain');
            if (isset($criteria['budget'])) chatbotUpdateData($cid, 'budget_terrain', $criteria['budget']);
            return handleSmartSearchTerrain($cid, $criteria, $scenario);
        }

        // Recherche maison
        if ($subject === 'maison' || isset($criteria['nb_chambres']) || isset($criteria['type_maison'])) {
            chatbotMarkRecognized($cid, 'smart_search_maison');
            return handleSmartSearchMaison($cid, $criteria, $scenario);
        }
    }

    // --- 4. Détection d'intention classique ---
    $intention = chatbotDetectIntention($message);
    $msgCount = chatbotCountUserMessages($cid);

    if ($intention) {
        chatbotMarkRecognized($cid, $intention['key']);
        $resp = $intention['response'] ?? '';
        $act = $intention['action'] ?? '';

        // Si intention terrain/maison + critères extraits → recherche enrichie
        if ($hasCriteria && in_array($act, ['scenario_terrain', 'scenario_devis', 'afficher_modeles'])) {
            foreach ($criteria as $k => $v) { if ($k[0] !== '_') chatbotUpdateData($cid, $k, $v); }
            if ($act === 'scenario_terrain') return handleSmartSearchTerrain($cid, $criteria, $scenario);
            return handleSmartSearchMaison($cid, $criteria, $scenario);
        }

        // Actions de scénario classiques
        if ($act === 'scenario_devis') { if ($resp) chatbotSaveMessage($cid, 'bot', $resp); return goToStep($cid, 30, $scenario); }
        if ($act === 'scenario_terrain') { if ($resp) chatbotSaveMessage($cid, 'bot', $resp); return goToStep($cid, 20, $scenario); }
        if ($act === 'afficher_modeles') { if ($resp) chatbotSaveMessage($cid, 'bot', $resp); return goToStep($cid, 10, $scenario); }
        if ($act === 'transfert_humain') {
            chatbotSaveMessage($cid, 'bot', $resp ?: 'Un conseiller va vous aider !');
            chatbotUpdateStep($cid, 50);
            respond(['step'=>50, 'type'=>'form', 'message'=> ($resp ?: '') . "\n\n👇 **Laissez vos coordonnées :**"]);
        }

        // Action lien vers une page du site : link:/engagements.php, link:/modeles.php, etc.
        if (strpos($act, 'link:') === 0) {
            $linkUrl = substr($act, 5); // enlever "link:"
            $linkLabel = getLinkLabel($linkUrl);
            if ($resp) chatbotSaveMessage($cid, 'bot', $resp);
            respond([
                'step' => $stepId,
                'message' => $resp ?: '',
                'options' => [
                    ['label' => "📄 $linkLabel", 'value' => 'voir_page', 'action' => 'link', 'url' => $linkUrl],
                    ['label' => '❓ Autre question', 'value' => 'autre', 'next' => 40],
                    ['label' => '📋 Être rappelé', 'value' => 'coord', 'next' => 50]
                ]
            ]);
        }

        // Sinon, répondre avec le texte + proposer la suite
        if ($resp) {
            chatbotSaveMessage($cid, 'bot', $resp);

            // Après 4+ échanges → formulaire
            if ($msgCount >= 4) {
                chatbotUpdateStep($cid, 50);
                respond(['step'=>50, 'type'=>'form', 'message'=> $resp . "\n\n👇 **Pour aller plus loin, laissez vos coordonnées :**"]);
            }

            // Options contextuelles selon le sujet
            respond([
                'step' => $stepId,
                'message' => $resp,
                'options' => getFollowUpOptions($intention['key'])
            ]);
        }
    }

    // --- 4. Après 5+ messages non compris → formulaire ---
    if ($msgCount >= 5) {
        chatbotUpdateStep($cid, 50);
        $msg = "Un conseiller ORCA pourra mieux vous répondre ! 😊\n\nLaissez vos coordonnées, il vous rappelle sous 24h :";
        chatbotSaveMessage($cid, 'bot', $msg);
        respond(['step'=>50, 'type'=>'form', 'message'=>$msg]);
    }

    // --- 5. Réponse par défaut ---
    $defaultMsg = "Je n'ai pas la réponse exacte, mais je peux vous aider ! 😊";
    chatbotSaveMessage($cid, 'bot', $defaultMsg);
    respond([
        'step' => $stepId,
        'message' => $defaultMsg,
        'options' => [
            ['label' => '💰 Connaître les prix', 'value' => 'go_prix', 'next' => 30],
            ['label' => '🏠 Chercher une maison', 'value' => 'go_maison', 'next' => 10],
            ['label' => '🌿 Chercher un terrain', 'value' => 'go_terrain', 'next' => 20],
            ['label' => '📋 Être rappelé', 'value' => 'coord', 'next' => 50]
        ]
    ]);
}

// ======================================================
// COLLECTE CONVERSATIONNELLE DES COORDONNÉES
// ======================================================

function handleCoordStep($cid, $message, $step, $stepId, $scenario) {
    $field = $step['field'];
    $value = trim($message);

    // Valider
    $validationType = $step['validation'] ?? null;
    if ($validationType && !chatbotValidateInput($value, $validationType)) {
        chatbotSaveMessage($cid, 'bot', $step['error'] ?? 'Hmm, je n\'ai pas compris. Réessayez ?');
        respond([
            'step' => $stepId,
            'message' => $step['error'] ?? 'Hmm, je n\'ai pas compris. Réessayez ?',
            'field' => $field,
            'retry' => true
        ]);
    }

    // Normaliser téléphone
    if ($field === 'telephone') {
        $value = chatbotNormalizePhone($value);
    }

    chatbotMarkRecognized($cid, 'coord_' . $field);
    chatbotUpdateData($cid, $field, $value);

    $nextStepId = $step['next'] ?? 55;

    // Si étape suivante = 55 (finale), créer le lead
    if ($nextStepId == 55) {
        $conv = chatbotGetConversation($cid);
        $allData = json_decode($conv['data_collected'] ?? '{}', true) ?: [];
        $result = chatbotCreateLead($cid, $allData);

        $finalStep = $scenario[55];
        $msg = $finalStep['message'];
        $msg = str_replace('{{prenom}}', htmlspecialchars($allData['prenom'] ?? ''), $msg);
        $msg = str_replace('{{telephone}}', htmlspecialchars($allData['telephone'] ?? ''), $msg);

        chatbotSaveMessage($cid, 'bot', $msg);
        chatbotUpdateStep($cid, 55);

        respond([
            'step' => 55, 'type' => 'final', 'message' => $msg,
            'lead_id' => $result['lead_id'] ?? null,
            'options' => $finalStep['options'] ?? null
        ]);
    }

    // Sinon, aller à l'étape suivante
    $nextStep = $scenario[$nextStepId] ?? null;
    if (!$nextStep) respond(['error' => 'Étape non trouvée']);

    // Remplacer les variables dans le message
    $conv = chatbotGetConversation($cid);
    $allData = json_decode($conv['data_collected'] ?? '{}', true) ?: [];
    $msg = $nextStep['message'];
    foreach ($allData as $k => $v) {
        $msg = str_replace('{{' . $k . '}}', htmlspecialchars($v), $msg);
    }

    chatbotUpdateStep($cid, $nextStepId);
    chatbotSaveMessage($cid, 'bot', $msg);

    respond([
        'step' => $nextStepId,
        'type' => $nextStep['type'] ?? 'text',
        'message' => $msg,
        'field' => $nextStep['field'] ?? null,
        'options' => $nextStep['options'] ?? null
    ]);
}

// ======================================================
// RECHERCHE INTELLIGENTE (depuis texte libre)
// ======================================================

function handleSmartSearchTerrain($cid, $criteria, $scenario) {
    $results = chatbotSearchTerrainsAdvanced($criteria);
    $text = '';

    // Résumer ce qu'on a compris
    $understood = [];
    if (!empty($criteria['departement'])) $understood[] = 'département ' . $criteria['departement'];
    if (!empty($criteria['surface'])) $understood[] = $criteria['surface'] . 'm²';
    if (!empty($criteria['budget'])) $understood[] = number_format($criteria['budget'], 0, ',', ' ') . ' €';
    if (!empty($criteria['viabilise'])) $understood[] = 'viabilisé';

    if (!empty($understood)) {
        $text .= "🔍 J'ai compris : **" . implode(', ', $understood) . "**\n\n";
    }

    $text .= chatbotFormatTerrains($results);

    if (!empty($results)) {
        $text .= "\n**Intéressé ? Laissez vos coordonnées pour les fiches détaillées !**";
    }

    chatbotSaveMessage($cid, 'bot', $text);
    chatbotUpdateStep($cid, 50);
    respond([
        'step' => 50,
        'type' => 'results_then_form',
        'message' => $text,
        'results_count' => count($results)
    ]);
}

function handleSmartSearchMaison($cid, $criteria, $scenario) {
    $results = chatbotSearchModelesAdvanced($criteria);
    $budget = intval($criteria['budget'] ?? 0);
    $text = '';

    // Résumer ce qu'on a compris
    $understood = [];
    if (!empty($criteria['type_maison'])) $understood[] = $criteria['type_maison'] === 'plain-pied' ? 'plain-pied' : 'avec étage';
    if (!empty($criteria['nb_chambres'])) $understood[] = $criteria['nb_chambres'] . ' chambres';
    if (!empty($criteria['surface'])) $understood[] = $criteria['surface'] . 'm²';
    if ($budget > 0) $understood[] = number_format($budget, 0, ',', ' ') . ' €';

    if (!empty($understood)) {
        $text .= "🔍 J'ai compris : **" . implode(', ', $understood) . "**\n\n";
    }

    $text .= chatbotFormatModeles($results, $budget);
    $text .= "\n**Laissez vos coordonnées pour une estimation détaillée !**";

    chatbotSaveMessage($cid, 'bot', $text);
    chatbotUpdateStep($cid, 50);
    respond([
        'step' => 50,
        'type' => 'results_then_form',
        'message' => $text,
        'results_count' => count($results)
    ]);
}

// ======================================================
// RÉSULTATS MAISON (après le questionnaire guidé)
// ======================================================
function handleResultsMaison($cid, $scenario) {
    $conv = chatbotGetConversation($cid);
    $data = json_decode($conv['data_collected'] ?? '{}', true) ?: [];

    $results = chatbotSearchModeles($data);
    $budget = intval($data['budget'] ?? 0);
    $text = chatbotFormatModeles($results, $budget);
    $text .= "\n**Intéressé ? Laissez vos coordonnées pour recevoir les fiches détaillées et une estimation !**";

    chatbotSaveMessage($cid, 'bot', $text);
    chatbotUpdateStep($cid, 50);

    respond([
        'step' => 50,
        'type' => 'results_then_form',
        'message' => $text,
        'results_count' => count($results)
    ]);
}

// ======================================================
// RÉSULTATS TERRAIN (après le questionnaire)
// ======================================================
function handleResultsTerrain($cid, $scenario) {
    $conv = chatbotGetConversation($cid);
    $data = json_decode($conv['data_collected'] ?? '{}', true) ?: [];

    $results = chatbotSearchTerrains($data);
    $text = chatbotFormatTerrains($results);
    $text .= "\n**Laissez vos coordonnées pour recevoir les fiches complètes !**";

    chatbotSaveMessage($cid, 'bot', $text);
    chatbotUpdateStep($cid, 50);

    respond([
        'step' => 50,
        'type' => 'results_then_form',
        'message' => $text,
        'results_count' => count($results)
    ]);
}

// ======================================================
// FORMULAIRE
// ======================================================
function handleForm() {
    $cid = intval($_POST['conversation_id'] ?? 0);
    $formData = json_decode($_POST['data'] ?? '{}', true);
    if (!$cid || empty($formData)) respond(['error' => 'Données manquantes']);

    $errors = [];
    if (empty($formData['prenom']) || !chatbotValidateInput($formData['prenom'], 'name')) $errors[] = 'Prénom';
    if (empty($formData['nom']) || !chatbotValidateInput($formData['nom'], 'name')) $errors[] = 'Nom';
    if (empty($formData['email']) || !chatbotValidateInput($formData['email'], 'email')) $errors[] = 'Email';
    if (empty($formData['telephone']) || !chatbotValidateInput($formData['telephone'], 'phone')) $errors[] = 'Téléphone';
    if (!empty($errors)) respond(['error' => 'Champ(s) invalide(s) : ' . implode(', ', $errors)]);

    $formData['telephone'] = chatbotNormalizePhone($formData['telephone']);

    $conv = chatbotGetConversation($cid);
    $existing = json_decode($conv['data_collected'] ?? '{}', true) ?: [];
    $allData = array_merge($existing, $formData);

    foreach ($formData as $k => $v) chatbotUpdateData($cid, $k, $v);

    $result = chatbotCreateLead($cid, $allData);
    if (!$result['success']) respond(['error' => 'Erreur serveur, réessayez.']);

    $prenom = htmlspecialchars($allData['prenom'] ?? '');
    $scenario = chatbotGetScenario();
    $msg = str_replace('{{prenom}}', $prenom, $scenario[55]['message']);

    chatbotSaveMessage($cid, 'bot', $msg);
    chatbotUpdateStep($cid, 55);

    respond([
        'step' => 55, 'type' => 'final', 'message' => $msg,
        'lead_id' => $result['lead_id'], 'options' => $scenario[55]['options']
    ]);
}

// ======================================================
// UTILITAIRES
// ======================================================

function goToStep($cid, $stepId, $scenario) {
    $step = $scenario[$stepId] ?? $scenario[50];
    if (!isset($scenario[$stepId])) $stepId = 50;

    chatbotUpdateStep($cid, $stepId);
    chatbotSaveMessage($cid, 'bot', $step['message'], $step['options'] ?? null);

    respond([
        'step' => $stepId, 'type' => $step['type'] ?? 'step',
        'message' => $step['message'], 'options' => $step['options'] ?? null
    ]);
}

function matchOption($message, $options) {
    $msg = mb_strtolower(trim($message));
    foreach ($options as $opt) {
        if (mb_strtolower($opt['value']) === $msg) return $opt;
    }
    foreach ($options as $opt) {
        $label = preg_replace('/[\x{1F000}-\x{1FFFF}]|[\x{2600}-\x{27BF}]/u', '', $opt['label']);
        $label = mb_strtolower(trim($label));
        if ($label === $msg || mb_strpos($label, $msg) !== false || mb_strpos($msg, $label) !== false) return $opt;
        similar_text($msg, $label, $pct);
        if ($pct > 70) return $opt;
    }
    return null;
}

function getFollowUpOptions($intentionKey) {
    $map = [
        'prix' => [
            ['label' => '🏠 Voir les modèles', 'value' => 'go_maison', 'next' => 10],
            ['label' => '💰 Grille des prix complète', 'value' => 'go_prix', 'next' => 30],
            ['label' => '❓ Autre question', 'value' => 'autre', 'next' => 40],
            ['label' => '📋 Être rappelé', 'value' => 'coord', 'next' => 50]
        ],
        'delai' => [
            ['label' => '💰 Connaître les prix', 'value' => 'go_prix', 'next' => 30],
            ['label' => '❓ Autre question', 'value' => 'autre', 'next' => 40],
            ['label' => '📋 Être rappelé', 'value' => 'coord', 'next' => 50]
        ],
        'financement' => [
            ['label' => '💰 Simuler mon budget', 'value' => 'go_prix', 'next' => 30],
            ['label' => '❓ Autre question', 'value' => 'autre', 'next' => 40],
            ['label' => '📞 Parler à un conseiller', 'value' => 'coord', 'next' => 50]
        ],
        'rdv' => [
            ['label' => '📋 Laisser mes coordonnées', 'value' => 'coord', 'next' => 50],
            ['label' => '❓ Autre question', 'value' => 'autre', 'next' => 40]
        ],
        'garantie' => [
            ['label' => '🏠 Voir nos modèles', 'value' => 'go_maison', 'next' => 10],
            ['label' => '❓ Autre question', 'value' => 'autre', 'next' => 40],
            ['label' => '📋 Être rappelé', 'value' => 'coord', 'next' => 50]
        ],
    ];
    return $map[$intentionKey] ?? [
        ['label' => '🏠 Nos maisons', 'value' => 'go_maison', 'next' => 10],
        ['label' => '🌿 Nos terrains', 'value' => 'go_terrain', 'next' => 20],
        ['label' => '❓ Autre question', 'value' => 'autre', 'next' => 40],
        ['label' => '📋 Être rappelé', 'value' => 'coord', 'next' => 50]
    ];
}

function getLinkLabel($url) {
    $labels = [
        '/modeles.php' => 'Voir nos modèles',
        '/engagements.php' => 'Voir nos engagements',
        '/constructeur.php' => 'Découvrir ORCA',
        '/contact.php' => 'Nous contacter',
        '/faq.php' => 'Consulter la FAQ',
        '/blog.php' => 'Lire nos actualités',
        '/estimation.php' => 'Estimer mon projet',
    ];
    foreach ($labels as $path => $label) {
        if (strpos($url, $path) !== false) return $label;
    }
    return 'En savoir plus';
}

function respond($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
