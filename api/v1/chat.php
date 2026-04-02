<?php
/**
 * FrenchyBot API - Point d'entree conversation multi-tenant
 */
define('FRENCHYBOT', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/chatbot-functions.php';

header('Content-Type: application/json; charset=utf-8');

// Authentifier via token
$chatbot = authenticateRequest();
$chatbot_id = $chatbot['id'];

// Chatbot désactivé ?
if (!$chatbot['is_active']) {
    respond(['disabled' => true]);
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
switch ($action) {
    case 'init':    handleInit($chatbot, $chatbot_id); break;
    case 'message': handleMessage($chatbot_id); break;
    case 'form':    handleForm($chatbot_id); break;
    default:        respond(['error' => 'Action inconnue']);
}

// ======================================================
// INIT
// ======================================================
function handleInit(array $chatbot, int $chatbot_id) {
    $conv = chatbotGetOrCreateConversation($chatbot_id);
    $scenario = chatbotGetScenario();

    if ($conv['is_new']) {
        $step = $scenario[1];
        $welcomeMsg = !empty($chatbot['welcome_message']) ? $chatbot['welcome_message'] : $step['message'];

        // A/B test sur le message de bienvenue
        $abTest = chatbotGetABVariant($conv['id'], $chatbot_id);
        if ($abTest && !empty($abTest['message'])) {
            $welcomeMsg = $abTest['message'];
        }

        chatbotSaveMessage($conv['id'], 'bot', $welcomeMsg, $step['options']);
        respond([
            'conversation_id' => $conv['id'], 'step' => 1, 'type' => 'buttons',
            'message' => $welcomeMsg, 'options' => $step['options'], 'is_new' => true,
            'config' => [
                'auto_popup' => (bool)$chatbot['auto_popup'],
                'popup_delay' => intval($chatbot['popup_delay']),
                'color' => $chatbot['primary_color'] ?? '#1a5653'
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
function handleMessage(int $chatbot_id) {
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

    // --- 0. Navigation directe (valeurs de boutons) — AVANT la collecte coord ---
    $nav = ['go_maison'=>10, 'go_terrain'=>20, 'go_prix'=>30, 'go_question'=>40, 'go_form'=>50,
            'autre'=>40, 'coord'=>50, 'fermer'=>55];
    $val = mb_strtolower(trim($message));
    if (isset($nav[$val])) {
        chatbotMarkRecognized($cid, 'navigation');
        return goToStep($cid, $nav[$val], $scenario);
    }

    // --- 1. Si etape coord mais le message ressemble a une recherche → relancer une recherche ---
    if ($stepId >= 50 && $stepId <= 53 && $step && isset($step['field'])) {
        $quickCriteria = chatbotExtractCriteria($message);
        $hasRealCriteria = !empty($quickCriteria) && !empty(array_diff_key($quickCriteria, ['_subject' => 1]));
        if ($hasRealCriteria) {
            // C'est une recherche, pas un prenom/nom/email
            $subject = $quickCriteria['_subject'] ?? null;
            foreach ($quickCriteria as $k => $v) {
                if ($k[0] !== '_') chatbotUpdateData($cid, $k, $v);
            }
            if ($subject === 'terrain' || !empty($quickCriteria['ville']) || (!$subject && isset($quickCriteria['departement']) && !isset($quickCriteria['nb_chambres']))) {
                chatbotMarkRecognized($cid, 'smart_search_terrain');
                return handleSmartSearchTerrain($cid, $quickCriteria, $scenario);
            }
            if ($subject === 'maison' || isset($quickCriteria['nb_chambres']) || isset($quickCriteria['type_maison'])) {
                chatbotMarkRecognized($cid, 'smart_search_maison');
                return handleSmartSearchMaison($cid, $quickCriteria, $scenario);
            }
        }
        // Sinon, collecte normale des coordonnees
        return handleCoordStep($cid, $message, $step, $stepId, $scenario, $chatbot_id);
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
    $intention = chatbotDetectIntention($message, $chatbot_id);
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
        $msg = "Un conseiller pourra mieux vous répondre ! 😊\n\nLaissez vos coordonnées, il vous rappelle sous 24h :";
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

function handleCoordStep($cid, $message, $step, $stepId, $scenario, $chatbot_id) {
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
        $result = chatbotCreateLead($cid, $allData, $chatbot_id);

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
    global $chatbot_id;

    // Essayer BDD externe d'abord
    $extPdo = getExternalPdo($chatbot_id);
    if ($extPdo) {
        $productConfig = null;
        foreach (getProductConfigs($chatbot_id) as $c) {
            if (in_array($c['type'], ['terrain', 'terrains', 'land'])) { $productConfig = $c; break; }
        }
        if ($productConfig) {
            return handleGenericProductSearch($cid, $criteria, $productConfig);
        }
    }

    $results = chatbotSearchTerrainsAdvanced($criteria, $chatbot_id);
    $text = '';

    $understood = [];
    if (!empty($criteria['ville'])) $understood[] = $criteria['ville'];
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
    global $chatbot_id;

    // Essayer BDD externe d'abord
    $extPdo = getExternalPdo($chatbot_id);
    if ($extPdo) {
        $productConfig = null;
        foreach (getProductConfigs($chatbot_id) as $c) {
            if (in_array($c['type'], ['maison', 'modele', 'modeles', 'product'])) { $productConfig = $c; break; }
        }
        if ($productConfig) {
            return handleGenericProductSearch($cid, $criteria, $productConfig);
        }
    }

    $results = chatbotSearchModelesAdvanced($criteria, $chatbot_id);
    $budget = intval($criteria['budget'] ?? 0);
    $text = '';

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
// RECHERCHE PRODUIT GENERIQUE (BDD externe)
// ======================================================
function handleGenericProductSearch($cid, $criteria, $productConfig) {
    global $chatbot_id;

    $search = chatbotSearchProducts($chatbot_id, $productConfig['type'], $criteria);
    $results = $search['results'];
    $totalCount = $search['total'];
    $budget = intval($criteria['budget'] ?? 0);
    $text = '';

    // Resumer ce qu'on a compris
    $understood = [];
    if (!empty($criteria['ville'])) $understood[] = $criteria['ville'];
    if (!empty($criteria['departement'])) $understood[] = 'departement ' . $criteria['departement'];
    if (!empty($criteria['surface'])) $understood[] = $criteria['surface'] . 'm²';
    if ($budget > 0) $understood[] = number_format($budget, 0, ',', ' ') . ' €';
    if (!empty($criteria['nb_chambres'])) $understood[] = $criteria['nb_chambres'] . ' chambres';
    if (!empty($criteria['type_maison'])) $understood[] = $criteria['type_maison'];

    if (!empty($understood)) {
        $text .= "🔍 J'ai compris : **" . implode(', ', $understood) . "**\n\n";
    }

    $text .= chatbotFormatProducts($results, $productConfig, $budget, $totalCount);
    $text .= "\n**Laissez vos coordonnees et un conseiller vous enverra la liste complete !**";

    chatbotSaveMessage($cid, 'bot', $text);
    chatbotUpdateStep($cid, 50);
    respond([
        'step' => 50,
        'type' => 'results_then_form',
        'message' => $text,
        'results_count' => $totalCount
    ]);
}

// ======================================================
// RÉSULTATS MAISON (après le questionnaire guidé)
// ======================================================
function handleResultsMaison($cid, $scenario) {
    global $chatbot_id;
    $conv = chatbotGetConversation($cid);
    $data = json_decode($conv['data_collected'] ?? '{}', true) ?: [];

    // Essayer BDD externe
    $extPdo = getExternalPdo($chatbot_id);
    if ($extPdo) {
        foreach (getProductConfigs($chatbot_id) as $c) {
            if (in_array($c['type'], ['maison', 'modele', 'modeles', 'product'])) {
                $criteria = $data;
                if (!empty($data['budget'])) $criteria['budget'] = intval($data['budget']);
                if (!empty($data['type_maison'])) $criteria['category'] = $data['type_maison'];
                return handleGenericProductSearch($cid, $criteria, $c);
            }
        }
    }

    $results = chatbotSearchModeles($data, $chatbot_id);
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
    global $chatbot_id;
    $conv = chatbotGetConversation($cid);
    $data = json_decode($conv['data_collected'] ?? '{}', true) ?: [];

    // Essayer BDD externe
    $extPdo = getExternalPdo($chatbot_id);
    if ($extPdo) {
        foreach (getProductConfigs($chatbot_id) as $c) {
            if (in_array($c['type'], ['terrain', 'terrains', 'land'])) {
                $criteria = $data;
                if (!empty($data['budget_terrain'])) $criteria['budget'] = intval($data['budget_terrain']);
                return handleGenericProductSearch($cid, $criteria, $c);
            }
        }
    }

    $results = chatbotSearchTerrains($data, $chatbot_id);
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
function handleForm(int $chatbot_id) {
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

    $result = chatbotCreateLead($cid, $allData, $chatbot_id);
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
