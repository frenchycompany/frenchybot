<?php
/**
 * Fonctions du Chatbot ORCA - Version intelligente
 * Scénarios complets pré-établis + recherche BDD
 */

// ======================================================
// CONVERSATION
// ======================================================

function chatbotGetOrCreateConversation() {
    global $pdo;
    $sid = session_id();
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    $page = $_SERVER['HTTP_REFERER'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM chatbot_conversations
                          WHERE session_id = ? AND is_active = 1
                          AND last_activity > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
                          ORDER BY id DESC LIMIT 1");
    $stmt->execute([$sid]);
    $conv = $stmt->fetch();

    if ($conv) return array_merge($conv, ['is_new' => false]);

    $pdo->prepare("INSERT INTO chatbot_conversations
                  (session_id, ip_address, page_source, current_step, data_collected, started_at, last_activity)
                  VALUES (?, ?, ?, 1, '{}', NOW(), NOW())")->execute([$sid, $ip, $page]);

    return ['id' => $pdo->lastInsertId(), 'current_step' => 1, 'data_collected' => '{}', 'is_new' => true];
}

function chatbotGetConversation($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM chatbot_conversations WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function chatbotUpdateStep($cid, $step) {
    global $pdo;
    $pdo->prepare("UPDATE chatbot_conversations SET current_step = ?, last_activity = NOW() WHERE id = ?")->execute([$step, $cid]);
}

function chatbotUpdateData($cid, $field, $val) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT data_collected FROM chatbot_conversations WHERE id = ?");
    $stmt->execute([$cid]);
    $data = json_decode($stmt->fetchColumn() ?: '{}', true) ?: [];
    $data[$field] = $val;
    $score = min(count($data) * 12, 100);
    $pdo->prepare("UPDATE chatbot_conversations SET data_collected = ?, completion_score = ?, last_activity = NOW() WHERE id = ?")
        ->execute([json_encode($data, JSON_UNESCAPED_UNICODE), $score, $cid]);
}

function chatbotSaveMessage($cid, $type, $msg, $extra = null) {
    global $pdo;
    $buttons = $extra ? json_encode($extra, JSON_UNESCAPED_UNICODE) : null;
    $pdo->prepare("INSERT INTO chatbot_messages (conversation_id, type, message, buttons, created_at) VALUES (?, ?, ?, ?, NOW())")
        ->execute([$cid, $type, $msg, $buttons]);
    return $pdo->lastInsertId();
}

/**
 * Marquer le dernier message utilisateur comme reconnu
 */
function chatbotMarkRecognized($cid, $intentionKey) {
    global $pdo;
    $pdo->prepare("UPDATE chatbot_messages SET intention_detected = ?
        WHERE conversation_id = ? AND type = 'user' ORDER BY id DESC LIMIT 1")
        ->execute([$intentionKey, $cid]);
}

function chatbotGetHistory($cid) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT type, message, buttons FROM chatbot_messages WHERE conversation_id = ? ORDER BY id ASC");
    $stmt->execute([$cid]);
    return $stmt->fetchAll();
}

function chatbotCountUserMessages($cid) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM chatbot_messages WHERE conversation_id = ? AND type = 'user'");
    $stmt->execute([$cid]);
    return (int) $stmt->fetchColumn();
}

// ======================================================
// SCÉNARIOS COMPLETS PRÉ-ÉTABLIS
// Chaque parcours guide l'utilisateur de A à Z
// et termine TOUJOURS par le formulaire de coordonnées
// ======================================================

function chatbotGetScenario() {
    return [
        // ===================== ACCUEIL =====================
        1 => [
            'type' => 'chips',
            'message' => "Bonjour ! 👋 Je suis l'assistant ORCA.\n\nJe peux vous trouver la maison et le terrain idéal. Qu'est-ce qui vous ferait plaisir ?",
            'options' => [
                ['label' => '🏠 Une maison', 'value' => 'go_maison', 'next' => 10],
                ['label' => '🌿 Un terrain', 'value' => 'go_terrain', 'next' => 20],
                ['label' => '💰 Les prix', 'value' => 'go_prix', 'next' => 30],
                ['label' => '❓ Une question', 'value' => 'go_question', 'next' => 40]
            ]
        ],

        // ===================== PARCOURS MAISON =====================
        10 => [
            'type' => 'chips',
            'message' => "Bonne idée ! 😊 On va trouver le modèle parfait.\n\nVous préférez quel style ?",
            'field' => 'type_maison',
            'options' => [
                ['label' => 'Plain-pied', 'value' => 'plain-pied', 'next' => 11],
                ['label' => 'Avec étage', 'value' => '1-etage', 'next' => 11],
                ['label' => 'Pas sûr', 'value' => 'tous', 'next' => 11]
            ]
        ],
        11 => [
            'type' => 'chips',
            'message' => "Noté ! Et côté chambres, il vous en faut combien ?",
            'field' => 'nb_chambres',
            'options' => [
                ['label' => '2', 'value' => '2', 'next' => 12],
                ['label' => '3', 'value' => '3', 'next' => 12],
                ['label' => '4+', 'value' => '4', 'next' => 12]
            ]
        ],
        12 => [
            'type' => 'chips',
            'message' => "Parfait ! Dernière question : votre budget maison (hors terrain) ?",
            'field' => 'budget',
            'options' => [
                ['label' => '< 155k€', 'value' => '155000', 'next' => 'results_maison'],
                ['label' => '155 - 185k€', 'value' => '185000', 'next' => 'results_maison'],
                ['label' => '185 - 220k€', 'value' => '220000', 'next' => 'results_maison'],
                ['label' => '> 220k€', 'value' => '250000', 'next' => 'results_maison']
            ]
        ],

        // ===================== PARCOURS TERRAIN =====================
        20 => [
            'type' => 'chips',
            'message' => "Je peux vous aider à trouver un terrain ! 🌿\n\nDans quel coin cherchez-vous ?",
            'field' => 'departement',
            'options' => [
                ['label' => 'Oise (60)', 'value' => '60', 'next' => 21],
                ['label' => 'Seine-et-Marne (77)', 'value' => '77', 'next' => 21],
                ['label' => 'Val-d\'Oise (95)', 'value' => '95', 'next' => 21],
                ['label' => 'Aisne (02)', 'value' => '02', 'next' => 21],
                ['label' => 'Somme (80)', 'value' => '80', 'next' => 21]
            ]
        ],
        21 => [
            'type' => 'chips',
            'message' => "Top ! Et côté budget terrain, vous êtes sur quelle fourchette ?",
            'field' => 'budget_terrain',
            'options' => [
                ['label' => '< 50k€', 'value' => '50000', 'next' => 'results_terrain'],
                ['label' => '50 - 80k€', 'value' => '80000', 'next' => 'results_terrain'],
                ['label' => '80 - 120k€', 'value' => '120000', 'next' => 'results_terrain'],
                ['label' => 'Pas de limite', 'value' => '999999', 'next' => 'results_terrain']
            ]
        ],

        // ===================== PARCOURS PRIX =====================
        30 => [
            'type' => 'chips',
            'message' => "💰 **Voici nos prix de départ :**\n\n🏠 **Plain-pied :**\n• Coquelicot 88m² → **145 000 €**\n• Tulipe 95m² → **152 000 €**\n• Hibiscus 102m² → **168 000 €**\n\n🏡 **Avec étage :**\n• Orchidée 110m² → **178 000 €**\n• Lila 120m² → **185 000 €**\n• Magnolia 130m² → **215 000 €**\n\n*Hors terrain. Souvent moins cher qu'un loyer !*",
            'options' => [
                ['label' => '🏠 Choisir un modèle', 'value' => 'go_maison', 'next' => 10],
                ['label' => '🌿 Trouver un terrain', 'value' => 'go_terrain', 'next' => 20],
                ['label' => '📋 Être rappelé', 'value' => 'coord', 'next' => 50],
                ['label' => '❓ Une question', 'value' => 'go_question', 'next' => 40]
            ]
        ],

        // ===================== QUESTIONS LIBRES =====================
        40 => [
            'type' => 'text',
            'message' => "Allez-y, posez-moi votre question ! 😊\n\nJe connais nos maisons, les terrains dispo, les prix, les délais, les aides au financement...",
        ],

        // ===================== COLLECTE COORDONNÉES (conversationnel) =====================
        50 => [
            'type' => 'text',
            'field' => 'prenom',
            'message' => "Super ! Pour vous envoyer tout ça, j'ai juste besoin de votre prénom ? 😊",
            'validation' => 'name',
            'error' => "Hmm, je n'ai pas bien compris votre prénom. Pouvez-vous le retaper ?",
            'next' => 51
        ],
        51 => [
            'type' => 'text',
            'field' => 'nom',
            'message' => "Enchanté {{prenom}} ! Et votre nom de famille ?",
            'validation' => 'name',
            'error' => "Je n'ai pas bien lu, votre nom ?",
            'next' => 52
        ],
        52 => [
            'type' => 'text',
            'field' => 'email',
            'message' => "Votre email ? (pour recevoir les documents)",
            'validation' => 'email',
            'error' => "Cet email ne semble pas valide. Réessayez ? (ex: nom@email.fr)",
            'next' => 53
        ],
        53 => [
            'type' => 'text',
            'field' => 'telephone',
            'message' => "Et votre téléphone ? (pour que le conseiller vous rappelle)",
            'validation' => 'phone',
            'error' => "Le numéro ne semble pas valide. Format : 06 12 34 56 78",
            'next' => 55
        ],

        // ===================== CONFIRMATION =====================
        55 => [
            'type' => 'final',
            'message' => "🎉 **C'est noté {{prenom}} !**\n\n📞 Un conseiller ORCA vous rappelle sous 24h au **{{telephone}}**.\n\nMerci pour votre confiance !",
            'options' => [
                ['label' => '🏠 Voir nos modèles', 'value' => 'voir_modeles', 'action' => 'link', 'url' => '/modeles.php'],
                ['label' => 'Fermer', 'value' => 'fermer', 'action' => 'close']
            ]
        ]
    ];
}

// ======================================================
// RECHERCHE MODÈLES EN BDD
// ======================================================

function chatbotSearchModeles($data) {
    global $pdo;

    try {
        $where = ['is_active = 1'];
        $params = [];

        $type = $data['type_maison'] ?? '';
        if ($type && $type !== 'tous') {
            $where[] = 'nb_etages = ?';
            $params[] = $type;
        }

        $chambres = intval($data['nb_chambres'] ?? 0);
        if ($chambres > 0) {
            $where[] = 'nb_chambres >= ?';
            $params[] = $chambres;
        }

        $budget = intval($data['budget'] ?? 0);
        if ($budget > 0) {
            // Chercher les modèles dans le budget (avec marge de 10%)
            $where[] = '(prix_base IS NOT NULL AND prix_base <= ?)';
            $params[] = intval($budget * 1.1);
        }

        $sql = "SELECT nom, slug, surface_habitable, nb_chambres, nb_etages, style, prix_base, prix_afficher, slogan
                FROM modeles WHERE " . implode(' AND ', $where) . " ORDER BY prix_base ASC LIMIT 6";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll();

        // Si aucun résultat avec le budget, chercher TOUS les modèles correspondant au type/chambres
        if (empty($results) && $budget > 0) {
            $where2 = ['is_active = 1'];
            $params2 = [];
            if ($type && $type !== 'tous') { $where2[] = 'nb_etages = ?'; $params2[] = $type; }
            if ($chambres > 0) { $where2[] = 'nb_chambres >= ?'; $params2[] = $chambres; }

            $sql2 = "SELECT nom, slug, surface_habitable, nb_chambres, nb_etages, style, prix_base, prix_afficher, slogan
                     FROM modeles WHERE " . implode(' AND ', $where2) . " ORDER BY prix_base ASC LIMIT 6";
            $stmt2 = $pdo->prepare($sql2);
            $stmt2->execute($params2);
            $results = $stmt2->fetchAll();
        }

        return $results;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Formater les résultats modèles
 */
function chatbotFormatModeles($modeles, $budget = 0) {
    if (empty($modeles)) {
        return "Nous n'avons pas encore de modèle en base, mais nos conseillers ont plein de solutions pour vous !";
    }

    $inBudget = [];
    $aboveBudget = [];

    foreach ($modeles as $m) {
        $prix = $m['prix_base'] ? intval($m['prix_base']) : 0;
        if ($budget > 0 && $prix > $budget) {
            $aboveBudget[] = $m;
        } else {
            $inBudget[] = $m;
        }
    }

    $text = '';

    if (!empty($inBudget)) {
        $text .= "🏠 **" . count($inBudget) . " modèle(s) dans votre budget :**\n\n";
        foreach ($inBudget as $m) {
            $prix = $m['prix_afficher'] ?: number_format($m['prix_base'], 0, ',', ' ') . ' €';
            $etage = $m['nb_etages'] === 'plain-pied' ? 'Plain-pied' : 'Avec étage';
            $text .= "**{$m['nom']}** — {$m['surface_habitable']}m², {$m['nb_chambres']} ch., {$etage}\n";
            $text .= "→ {$prix}\n\n";
        }
    }

    if (!empty($aboveBudget) && empty($inBudget)) {
        $text .= "Aucun modèle exactement dans ce budget, mais voici les plus proches :\n\n";
        foreach ($aboveBudget as $m) {
            $prix = $m['prix_afficher'] ?: number_format($m['prix_base'], 0, ',', ' ') . ' €';
            $etage = $m['nb_etages'] === 'plain-pied' ? 'Plain-pied' : 'Avec étage';
            $text .= "**{$m['nom']}** — {$m['surface_habitable']}m², {$m['nb_chambres']} ch., {$etage}\n";
            $text .= "→ {$prix}\n\n";
        }
        $text .= "💡 *Nos conseillers peuvent adapter les modèles à votre budget !*\n";
    } elseif (!empty($aboveBudget)) {
        $text .= "Et avec un peu plus de budget :\n\n";
        foreach (array_slice($aboveBudget, 0, 2) as $m) {
            $prix = $m['prix_afficher'] ?: number_format($m['prix_base'], 0, ',', ' ') . ' €';
            $text .= "**{$m['nom']}** — {$m['surface_habitable']}m², {$m['nb_chambres']} ch. → {$prix}\n";
        }
        $text .= "\n";
    }

    return $text;
}

// ======================================================
// RECHERCHE TERRAINS EN BDD
// ======================================================

function chatbotSearchTerrains($data) {
    global $pdo;

    try {
        $where = ['is_available = 1'];
        $params = [];

        $dept = $data['departement'] ?? '';
        if ($dept) { $where[] = 'departement = ?'; $params[] = $dept; }

        $budget = intval($data['budget_terrain'] ?? 0);
        if ($budget > 0 && $budget < 999999) { $where[] = 'prix <= ?'; $params[] = $budget; }

        $sql = "SELECT DISTINCT reference, ville, code_postal, departement, surface, prix, est_viabilise, proximite
                FROM terrains WHERE " . implode(' AND ', $where) . " ORDER BY prix ASC LIMIT 5";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function chatbotFormatTerrains($terrains) {
    if (empty($terrains)) {
        return "Aucun terrain disponible pour ces critères actuellement.\n\n💡 **Nos conseillers recherchent en permanence de nouveaux terrains.** Laissez vos coordonnées et on vous préviendra dès qu'on a quelque chose !";
    }
    $text = "🌿 **" . count($terrains) . " terrain(s) disponible(s) :**\n\n";
    foreach ($terrains as $t) {
        $prix = number_format($t['prix'], 0, ',', ' ') . ' €';
        $viab = $t['est_viabilise'] ? '✅ Viabilisé' : '⚠️ À viabiliser';
        $text .= "**{$t['ville']}** ({$t['departement']}) — {$t['surface']}m² — {$prix}\n";
        $text .= "{$viab}";
        if ($t['proximite']) $text .= " | 📍 {$t['proximite']}";
        $text .= "\n\n";
    }
    return $text;
}

// ======================================================
// DÉTECTION D'INTENTION (BDD puis fallback)
// ======================================================

function chatbotDetectIntention($message) {
    global $pdo;
    $msg = mb_strtolower(trim($message));

    // 1. Chercher dans chatbot_intentions (BDD)
    try {
        $stmt = $pdo->query("SELECT * FROM chatbot_intentions WHERE is_active = 1 ORDER BY priority DESC");
        foreach ($stmt->fetchAll() as $intent) {
            $keywords = array_map('trim', explode(',', mb_strtolower($intent['keywords'])));
            foreach ($keywords as $kw) {
                if ($kw !== '' && mb_strpos($msg, $kw) !== false) {
                    return [
                        'key' => $intent['intention_key'],
                        'response' => $intent['response_text'],
                        'action' => $intent['action'] ?? null
                    ];
                }
            }
        }
    } catch (Exception $e) {}

    // 2. Fallback hardcodé
    $fallback = [
        'prix' => [
            'kw' => ['prix', 'coût', 'cout', 'combien', 'tarif', 'cher', '€', 'euro'],
            'text' => "💰 **Nos maisons démarrent à 145 000 € (88m², plain-pied).**\n\nGamme complète de 145 000 € à 215 000 € selon la surface et le nombre de chambres.\n\n*Prix hors terrain, hors options. Consultez la grille complète !*"
        ],
        'delai' => [
            'kw' => ['délai', 'delai', 'durée', 'duree', 'temps', 'quand', 'livraison', 'mois'],
            'text' => "⏱️ **Délai moyen : 8 à 12 mois** (permis + construction).\n\n• Étude et permis : 2-3 mois\n• Gros oeuvre : 4-5 mois\n• Second oeuvre + finitions : 2-3 mois\n\n**Nos délais sont contractuels et garantis.**"
        ],
        'financement' => [
            'kw' => ['financement', 'prêt', 'pret', 'ptz', 'crédit', 'credit', 'banque', 'aide', 'mensualité'],
            'text' => "💡 **Aides disponibles :**\n\n• **PTZ** : Prêt à Taux Zéro (sous conditions)\n• **Prêt Action Logement** : jusqu'à 40 000 €\n• **TVA réduite** dans certaines zones\n\nNotre partenaire bancaire vous accompagne gratuitement !"
        ],
        'rdv' => [
            'kw' => ['rendez-vous', 'rdv', 'rencontrer', 'agence', 'visite', 'appeler'],
            'text' => "📅 **Prenons rendez-vous !**\n\n• À l'agence de Longueil-Annel (60)\n• Chez vous (déplacement gratuit)\n• En visioconférence\n\nOuvert du lundi au vendredi, 9h-18h."
        ],
        'garantie' => [
            'kw' => ['garantie', 'qualité', 'norme', 'assurance', 'décennale', 're2020'],
            'text' => "✅ **Garanties ORCA :**\n\n• Garantie décennale (10 ans)\n• Garantie biennale (2 ans)\n• Assurance dommages-ouvrage\n• Norme RE2020\n• Constructeur depuis 1993"
        ],
    ];

    foreach ($fallback as $key => $data) {
        foreach ($data['kw'] as $kw) {
            if (mb_strpos($msg, $kw) !== false) {
                return ['key' => $key, 'response' => $data['text'], 'action' => null];
            }
        }
    }

    return null;
}

// ======================================================
// EXTRACTION INTELLIGENTE DE CRITÈRES DEPUIS UN MESSAGE
// "je veux un terrain de 500m² dans l'oise" → departement=60, surface=500
// ======================================================

function chatbotExtractCriteria($message) {
    $msg = mb_strtolower(trim($message));
    $criteria = [];

    // --- Département (nom ou numéro) ---
    $deptNames = [
        'oise' => '60', 'aisne' => '02', 'somme' => '80',
        'seine-et-marne' => '77', 'seine et marne' => '77',
        'val-d\'oise' => '95', 'val d\'oise' => '95', 'valdoise' => '95',
        'essonne' => '91', 'yvelines' => '78',
        'hauts-de-seine' => '92', 'hauts de seine' => '92',
        'seine-saint-denis' => '93', 'seine saint denis' => '93',
        'val-de-marne' => '94', 'val de marne' => '94',
        'picardie' => '60', 'compiègne' => '60', 'compiegne' => '60',
        'senlis' => '60', 'beauvais' => '60', 'creil' => '60', 'noyon' => '60',
        'meaux' => '77', 'coulommiers' => '77',
        'cergy' => '95', 'pontoise' => '95',
        'laon' => '02', 'soissons' => '02', 'saint-quentin' => '02',
        'amiens' => '80', 'abbeville' => '80',
    ];
    foreach ($deptNames as $name => $code) {
        if (mb_strpos($msg, $name) !== false) {
            $criteria['departement'] = $code;
            break;
        }
    }
    // Numéro de département brut
    if (!isset($criteria['departement']) && preg_match('/\b(02|60|77|78|80|91|92|93|94|95)\b/', $msg, $m)) {
        $criteria['departement'] = $m[1];
    }

    // --- Surface (m²) ---
    if (preg_match('/(\d+)\s*(?:m²|m2|mètres?\s*carrés?|metres?\s*carres?)/i', $msg, $m)) {
        $criteria['surface'] = intval($m[1]);
    }

    // --- Budget / prix ---
    // "200000€", "200 000€", "200k€", "200 000 euros", "budget 200000"
    if (preg_match('/(\d[\d\s.,]*)\s*(?:k€|k\s*euros?|k€)/i', $msg, $m)) {
        $criteria['budget'] = intval(preg_replace('/[\s.,]/', '', $m[1])) * 1000;
    } elseif (preg_match('/(\d[\d\s.,]*)\s*(?:€|euros?)/i', $msg, $m)) {
        $criteria['budget'] = intval(preg_replace('/[\s.,]/', '', $m[1]));
    } elseif (preg_match('/budget\s*(?:de\s*)?(\d[\d\s.,]*)/i', $msg, $m)) {
        $num = intval(preg_replace('/[\s.,]/', '', $m[1]));
        if ($num < 1000) $num *= 1000;
        $criteria['budget'] = $num;
    }

    // --- Nombre de chambres ---
    if (preg_match('/(\d+)\s*(?:chambres?|ch\b|pièces?\s*principales?)/i', $msg, $m)) {
        $criteria['nb_chambres'] = intval($m[1]);
    }

    // --- Type de maison ---
    if (preg_match('/plain[\s-]?pied/i', $msg)) {
        $criteria['type_maison'] = 'plain-pied';
    } elseif (preg_match('/(?:avec\s+)?étage|etage|r\+1/i', $msg)) {
        $criteria['type_maison'] = '1-etage';
    }

    // --- Viabilisé ---
    if (preg_match('/viabilis[ée]/i', $msg)) {
        $criteria['viabilise'] = true;
    }

    // --- Sujet principal (terrain ou maison ?) ---
    if (preg_match('/terrain|parcelle|foncier|constructible/i', $msg)) {
        $criteria['_subject'] = 'terrain';
    } elseif (preg_match('/maison|modèle|modele|construire|villa|pavillon/i', $msg)) {
        $criteria['_subject'] = 'maison';
    }

    return $criteria;
}

/**
 * Recherche terrains avec critères enrichis (surface, viabilisé...)
 */
function chatbotSearchTerrainsAdvanced($criteria) {
    global $pdo;

    try {
        $where = ['is_available = 1'];
        $params = [];

        if (!empty($criteria['departement'])) {
            $where[] = 'departement = ?';
            $params[] = $criteria['departement'];
        }
        if (!empty($criteria['budget'])) {
            $where[] = 'prix <= ?';
            $params[] = intval($criteria['budget']);
        }
        if (!empty($criteria['surface'])) {
            // Chercher des terrains >= surface demandée (avec marge -20%)
            $where[] = 'surface >= ?';
            $params[] = intval($criteria['surface'] * 0.8);
        }
        if (!empty($criteria['viabilise'])) {
            $where[] = 'est_viabilise = 1';
        }

        $sql = "SELECT DISTINCT reference, ville, code_postal, departement, surface, prix, est_viabilise, proximite
                FROM terrains WHERE " . implode(' AND ', $where) . " ORDER BY prix ASC LIMIT 5";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Recherche modèles avec critères enrichis
 */
function chatbotSearchModelesAdvanced($criteria) {
    global $pdo;

    try {
        $where = ['is_active = 1'];
        $params = [];

        if (!empty($criteria['type_maison']) && $criteria['type_maison'] !== 'tous') {
            $where[] = 'nb_etages = ?';
            $params[] = $criteria['type_maison'];
        }
        if (!empty($criteria['nb_chambres'])) {
            $where[] = 'nb_chambres >= ?';
            $params[] = intval($criteria['nb_chambres']);
        }
        if (!empty($criteria['budget'])) {
            $where[] = '(prix_base IS NOT NULL AND prix_base <= ?)';
            $params[] = intval($criteria['budget'] * 1.1);
        }
        if (!empty($criteria['surface'])) {
            $where[] = 'surface_habitable >= ?';
            $params[] = intval($criteria['surface'] * 0.85);
        }

        $sql = "SELECT nom, slug, surface_habitable, nb_chambres, nb_etages, style, prix_base, prix_afficher, slogan
                FROM modeles WHERE " . implode(' AND ', $where) . " ORDER BY prix_base ASC LIMIT 6";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll();

        // Fallback si rien trouvé : enlever le filtre budget
        if (empty($results) && !empty($criteria['budget'])) {
            $where2 = ['is_active = 1'];
            $params2 = [];
            if (!empty($criteria['type_maison']) && $criteria['type_maison'] !== 'tous') { $where2[] = 'nb_etages = ?'; $params2[] = $criteria['type_maison']; }
            if (!empty($criteria['nb_chambres'])) { $where2[] = 'nb_chambres >= ?'; $params2[] = intval($criteria['nb_chambres']); }
            if (!empty($criteria['surface'])) { $where2[] = 'surface_habitable >= ?'; $params2[] = intval($criteria['surface'] * 0.85); }
            $sql2 = "SELECT nom, slug, surface_habitable, nb_chambres, nb_etages, style, prix_base, prix_afficher, slogan
                     FROM modeles WHERE " . implode(' AND ', $where2) . " ORDER BY prix_base ASC LIMIT 6";
            $stmt2 = $pdo->prepare($sql2);
            $stmt2->execute($params2);
            $results = $stmt2->fetchAll();
        }

        return $results;
    } catch (Exception $e) {
        return [];
    }
}

// ======================================================
// VALIDATION & NORMALISATION
// ======================================================

function chatbotValidateInput($value, $type) {
    $value = trim($value);
    switch ($type) {
        case 'name':  return mb_strlen($value) >= 2 && preg_match('/^[\p{L}\s\-\']+$/u', $value);
        case 'email': return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
        case 'phone': return preg_match('/^(0[1-9])[\s.-]?(\d{2}[\s.-]?){4}$/', $value);
        default: return true;
    }
}

function chatbotNormalizePhone($phone) {
    $digits = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($digits) === 10) {
        return implode(' ', str_split($digits, 2));
    }
    return $phone;
}

// ======================================================
// CRÉATION DE LEAD
// ======================================================

function chatbotCreateLead($conversation_id, $data) {
    global $pdo;

    try {
        $terrainPrevu = in_array(($data['terrain'] ?? ''), ['oui', '1', 'true'], true) ? 1 : 0;
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        $pageSource = $_SERVER['HTTP_REFERER'] ?? 'chatbot';

        $stmt = $pdo->prepare("INSERT INTO leads
            (nom, prenom, email, telephone, departement, surface_souhaitee, budget_estime,
             terrain_prevu, type_demande, source, page_source, ip_address, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'devis', 'chatbot', ?, ?, NOW())");

        $stmt->execute([
            $data['nom'] ?? '', $data['prenom'] ?? '', $data['email'] ?? '',
            $data['telephone'] ?? '', $data['departement'] ?? '',
            $data['surface'] ?? '', $data['budget'] ?? '',
            $terrainPrevu, $pageSource, $ip
        ]);

        $lead_id = $pdo->lastInsertId();
        $pdo->prepare("UPDATE chatbot_conversations SET lead_id = ?, is_active = 0, ended_at = NOW() WHERE id = ?")
            ->execute([$lead_id, $conversation_id]);

        // Notification email admin
        chatbotNotifyAdmin($lead_id, $data);

        return ['lead_id' => $lead_id, 'success' => true];
    } catch (Exception $e) {
        error_log('Chatbot lead error: ' . $e->getMessage());
        return ['lead_id' => null, 'success' => false, 'error' => $e->getMessage()];
    }
}

// ======================================================
// NOTIFICATION EMAIL ADMIN (point 2)
// ======================================================

function chatbotNotifyAdmin($lead_id, $data) {
    global $pdo, $site_config;

    try {
        // Vérifier que les notifs sont activées
        $stmt = $pdo->prepare("SELECT valeur FROM config WHERE cle = 'chatbot_email_notifications'");
        $stmt->execute();
        $enabled = $stmt->fetchColumn();
        if ($enabled !== '1') return;

        $adminEmail = $site_config['site_email'] ?? '';
        if (empty($adminEmail)) return;

        $prenom = htmlspecialchars($data['prenom'] ?? '');
        $nom = htmlspecialchars($data['nom'] ?? '');
        $email = htmlspecialchars($data['email'] ?? '');
        $tel = htmlspecialchars($data['telephone'] ?? '');
        $dept = htmlspecialchars($data['departement'] ?? '-');
        $budget = htmlspecialchars($data['budget'] ?? '-');
        $surface = htmlspecialchars($data['surface'] ?? '-');

        $subject = "🏠 Nouveau lead chatbot : {$prenom} {$nom}";

        $body = "
        <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
            <div style='background:#1a5653;color:white;padding:20px;border-radius:8px 8px 0 0;'>
                <h2 style='margin:0;'>🏠 Nouveau lead via le chatbot</h2>
            </div>
            <div style='background:white;padding:25px;border:1px solid #eee;border-radius:0 0 8px 8px;'>
                <table style='width:100%;border-collapse:collapse;'>
                    <tr><td style='padding:8px 0;color:#888;width:120px;'>Prénom :</td><td style='padding:8px 0;font-weight:bold;'>{$prenom}</td></tr>
                    <tr><td style='padding:8px 0;color:#888;'>Nom :</td><td style='padding:8px 0;font-weight:bold;'>{$nom}</td></tr>
                    <tr><td style='padding:8px 0;color:#888;'>Email :</td><td style='padding:8px 0;'><a href='mailto:{$email}'>{$email}</a></td></tr>
                    <tr><td style='padding:8px 0;color:#888;'>Téléphone :</td><td style='padding:8px 0;font-weight:bold;font-size:16px;'><a href='tel:{$tel}'>{$tel}</a></td></tr>
                    <tr><td colspan='2' style='border-top:1px solid #eee;padding-top:12px;'></td></tr>
                    <tr><td style='padding:8px 0;color:#888;'>Département :</td><td style='padding:8px 0;'>{$dept}</td></tr>
                    <tr><td style='padding:8px 0;color:#888;'>Surface :</td><td style='padding:8px 0;'>{$surface} m²</td></tr>
                    <tr><td style='padding:8px 0;color:#888;'>Budget :</td><td style='padding:8px 0;'>{$budget} €</td></tr>
                </table>
                <div style='margin-top:20px;text-align:center;'>
                    <a href='" . SITE_URL . "admin/lead-view.php?id={$lead_id}' style='display:inline-block;padding:12px 30px;background:#1a5653;color:white;text-decoration:none;border-radius:6px;font-weight:bold;'>Voir le lead dans l'admin</a>
                </div>
            </div>
        </div>";

        sendEmail($adminEmail, $subject, $body);
    } catch (Exception $e) {
        error_log('Chatbot email notification error: ' . $e->getMessage());
    }
}

// ======================================================
// A/B TESTING (point 6) - récupérer la variante pour l'accueil
// ======================================================

function chatbotGetABVariant($conversation_id) {
    global $pdo;

    try {
        // Chercher un test actif sur le message de bienvenue
        $stmt = $pdo->query("SELECT * FROM chatbot_ab_tests WHERE status = 'active' AND test_type = 'welcome_message' LIMIT 1");
        $test = $stmt->fetch();
        if (!$test) return null;

        // Assigner aléatoirement A ou B
        $variant = (mt_rand(0, 1) === 0) ? 'A' : 'B';

        // Sauvegarder dans la conversation
        $pdo->prepare("UPDATE chatbot_conversations SET ab_test_id = ?, ab_variant = ? WHERE id = ?")
            ->execute([$test['id'], $variant, $conversation_id]);

        return [
            'test_id' => $test['id'],
            'variant' => $variant,
            'message' => ($variant === 'A') ? $test['variant_a_value'] : $test['variant_b_value']
        ];
    } catch (Exception $e) {
        return null;
    }
}

// ======================================================
// FOLLOWUP / RELANCE (point 8)
// ======================================================

/**
 * Planifier un followup pour une conversation abandonnée
 * Appelé quand un visiteur a commencé mais n'a pas laissé ses coordonnées
 */
function chatbotScheduleFollowup($conversation_id) {
    global $pdo;

    try {
        $conv = chatbotGetConversation($conversation_id);
        if (!$conv || $conv['lead_id']) return; // Déjà converti

        $data = json_decode($conv['data_collected'] ?? '{}', true) ?: [];
        if (empty($data)) return; // Rien collecté

        // Vérifier qu'il n'y a pas déjà un followup
        $stmt = $pdo->prepare("SELECT id FROM chatbot_followups WHERE conversation_id = ? AND status = 'pending'");
        $stmt->execute([$conversation_id]);
        if ($stmt->fetch()) return;

        // Déterminer la priorité
        $score = intval($conv['completion_score'] ?? 0);
        $priority = 'low';
        if ($score >= 50) $priority = 'high';
        elseif ($score >= 25) $priority = 'medium';

        // Planifier dans 24h
        $followupDate = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $prenom = $data['prenom'] ?? 'visiteur';
        $subject = "Votre projet de maison ORCA - On reprend où on en était ?";
        $content = chatbotBuildFollowupEmail($data);

        $pdo->prepare("INSERT INTO chatbot_followups
            (conversation_id, lead_data, followup_date, priority, status, email_subject, email_content, created_at)
            VALUES (?, ?, ?, ?, 'pending', ?, ?, NOW())")
            ->execute([
                $conversation_id,
                json_encode($data, JSON_UNESCAPED_UNICODE),
                $followupDate,
                $priority,
                $subject,
                $content
            ]);
    } catch (Exception $e) {
        error_log('Chatbot followup error: ' . $e->getMessage());
    }
}

/**
 * Construire l'email de relance
 */
function chatbotBuildFollowupEmail($data) {
    $prenom = htmlspecialchars($data['prenom'] ?? '');
    $greeting = $prenom ? "Bonjour {$prenom}," : "Bonjour,";

    $details = '';
    if (!empty($data['type_maison'])) $details .= "<li>Type : " . htmlspecialchars($data['type_maison']) . "</li>";
    if (!empty($data['nb_chambres'])) $details .= "<li>Chambres : " . htmlspecialchars($data['nb_chambres']) . "</li>";
    if (!empty($data['budget'])) $details .= "<li>Budget : " . number_format(intval($data['budget']), 0, ',', ' ') . " €</li>";
    if (!empty($data['departement'])) $details .= "<li>Département : " . htmlspecialchars($data['departement']) . "</li>";

    return "
    <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
        <div style='background:#1a5653;color:white;padding:20px;border-radius:8px 8px 0 0;text-align:center;'>
            <h2 style='margin:0;'>Maisons ORCA</h2>
        </div>
        <div style='background:white;padding:30px;border:1px solid #eee;'>
            <p>{$greeting}</p>
            <p>Vous avez commencé à explorer nos maisons sur notre site. Nous avons noté vos critères :</p>
            " . ($details ? "<ul style='line-height:1.8;'>{$details}</ul>" : "") . "
            <p><strong>Un conseiller ORCA peut vous rappeler gratuitement</strong> pour répondre à toutes vos questions et vous accompagner dans votre projet.</p>
            <div style='text-align:center;margin:25px 0;'>
                <a href='" . SITE_URL . "contact.php' style='display:inline-block;padding:14px 35px;background:#1a5653;color:white;text-decoration:none;border-radius:6px;font-weight:bold;font-size:16px;'>Être rappelé gratuitement</a>
            </div>
            <p style='color:#888;font-size:13px;'>Cet email a été envoyé suite à votre visite sur maisons-orca.fr. Si vous ne souhaitez plus recevoir de messages, ignorez simplement cet email.</p>
        </div>
    </div>";
}

/**
 * Traiter les followups en attente (à appeler via CRON)
 * Usage: php -r "require '/var/www/orca/includes/config.php'; chatbotProcessFollowups();"
 */
function chatbotProcessFollowups() {
    global $pdo, $site_config;

    try {
        $stmt = $pdo->query("SELECT f.*, c.data_collected
            FROM chatbot_followups f
            JOIN chatbot_conversations c ON f.conversation_id = c.id
            WHERE f.status = 'pending' AND f.followup_date <= NOW()
            LIMIT 10");
        $followups = $stmt->fetchAll();

        foreach ($followups as $f) {
            $data = json_decode($f['lead_data'] ?? $f['data_collected'] ?? '{}', true) ?: [];
            $email = $data['email'] ?? '';

            if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $sent = sendEmail($email, $f['email_subject'], $f['email_content']);
                $status = $sent ? 'sent' : 'pending';
            } else {
                $status = 'cancelled'; // Pas d'email → on annule
            }

            $pdo->prepare("UPDATE chatbot_followups SET status = ?, sent_at = NOW() WHERE id = ?")
                ->execute([$status, $f['id']]);
        }

        return count($followups);
    } catch (Exception $e) {
        error_log('Chatbot followup process error: ' . $e->getMessage());
        return 0;
    }
}
