-- ============================================
-- ORCA CHATBOT - Installation Complète
-- Pour exécuter sur le serveur: mysql -u root -p orca < chatbot_complete_install.sql
-- ============================================

-- Assurer l'encodage UTF8MB4 pour les emojis
SET NAMES utf8mb4;

-- ============================================
-- TABLE: Conversations
-- ============================================
CREATE TABLE IF NOT EXISTS chatbot_conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,
    page_source VARCHAR(255) DEFAULT NULL,
    referrer VARCHAR(500) DEFAULT NULL,
    scenario_id VARCHAR(50) DEFAULT 'qualification_complete',
    current_step INT DEFAULT 1,
    data_collected JSON DEFAULT NULL,
    completion_score DECIMAL(5,2) DEFAULT 0.00,
    is_active TINYINT(1) DEFAULT 1,
    lead_id INT DEFAULT NULL,
    ab_test_id INT DEFAULT NULL,
    ab_variant CHAR(1) DEFAULT NULL,
    started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_activity DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    ended_at DATETIME DEFAULT NULL,
    
    INDEX idx_session_active (session_id, is_active),
    INDEX idx_started (started_at),
    INDEX idx_lead (lead_id),
    INDEX idx_ab_test (ab_test_id, ab_variant),
    INDEX idx_is_active (is_active),
    INDEX idx_page_source (page_source)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: Messages
-- ============================================
CREATE TABLE IF NOT EXISTS chatbot_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    type ENUM('user', 'bot', 'system') DEFAULT 'user',
    message TEXT NOT NULL,
    intention_detected VARCHAR(100) DEFAULT NULL,
    buttons JSON DEFAULT NULL,
    data_collected JSON DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_conversation (conversation_id),
    INDEX idx_type (type),
    INDEX idx_intention (intention_detected),
    INDEX idx_created (created_at),
    FOREIGN KEY (conversation_id) REFERENCES chatbot_conversations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: Intentions (base de connaissances)
-- ============================================
CREATE TABLE IF NOT EXISTS chatbot_intentions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    intention_key VARCHAR(100) NOT NULL UNIQUE,
    keywords TEXT NOT NULL,
    response_text TEXT NOT NULL,
    action VARCHAR(50) DEFAULT NULL,
    priority INT DEFAULT 10,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_active (is_active),
    INDEX idx_priority (priority),
    INDEX idx_intention_key (intention_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: A/B Tests
-- ============================================
CREATE TABLE IF NOT EXISTS chatbot_ab_tests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    test_type VARCHAR(50) NOT NULL,
    variant_a_value TEXT NOT NULL,
    variant_b_value TEXT NOT NULL,
    status ENUM('active', 'completed', 'paused') DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    ended_at DATETIME DEFAULT NULL,
    
    INDEX idx_status (status),
    INDEX idx_type (test_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: Relances Automatiques
-- ============================================
CREATE TABLE IF NOT EXISTS chatbot_followups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    lead_data JSON DEFAULT NULL,
    followup_date DATETIME NOT NULL,
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    status ENUM('pending', 'sent', 'cancelled', 'converted') DEFAULT 'pending',
    email_subject VARCHAR(255) DEFAULT NULL,
    email_content TEXT DEFAULT NULL,
    sent_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_status_date (status, followup_date),
    INDEX idx_conversation (conversation_id),
    FOREIGN KEY (conversation_id) REFERENCES chatbot_conversations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: Analytics temps réel
-- ============================================
CREATE TABLE IF NOT EXISTS chatbot_analytics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    hour INT NOT NULL,
    conversations INT DEFAULT 0,
    messages INT DEFAULT 0,
    leads_generated INT DEFAULT 0,
    avg_score DECIMAL(5,2) DEFAULT 0,
    avg_duration INT DEFAULT 0,
    
    UNIQUE KEY unique_date_hour (date, hour),
    INDEX idx_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: Patterns utilisateur (autocomplete)
-- ============================================
CREATE TABLE IF NOT EXISTS chatbot_user_patterns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pattern_type VARCHAR(50) NOT NULL,
    pattern_value VARCHAR(255) NOT NULL,
    frequency INT DEFAULT 1,
    last_used DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_pattern (pattern_type, pattern_value),
    INDEX idx_type_freq (pattern_type, frequency)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: Webhooks/Intégrations (pour n8n, Zapier, etc.)
-- ============================================
CREATE TABLE IF NOT EXISTS chatbot_webhooks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    webhook_url VARCHAR(500) NOT NULL,
    webhook_type ENUM('n8n', 'zapier', 'custom') DEFAULT 'custom',
    event_type ENUM('lead_created', 'conversation_started', 'conversation_ended', 'all') DEFAULT 'lead_created',
    is_active TINYINT(1) DEFAULT 1,
    headers JSON DEFAULT NULL,
    last_triggered DATETIME DEFAULT NULL,
    last_response TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_active (is_active),
    INDEX idx_event (event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- INSERTIONS: Intentions par défaut
-- ============================================
INSERT INTO chatbot_intentions (intention_key, keywords, response_text, action, priority, is_active) VALUES
-- Intentions générales
('prix', 'prix,tarif,combien,coute,cher,budget,euros', '💰 Nos maisons sont personnalisables et le prix dépend de vos choix (surface, finitions...). Pour une estimation précise, pouvez-vous me dire :

1️⃣ Quelle surface souhaitez-vous ?
2️⃣ Dans quel département ?
3️⃣ Avez-vous déjà un terrain ?', 'collect_info', 10, 1),

('terrain', 'terrain,parcelle,trouver,tachercher,terrain a vendre', '🌿 Je peux vous aider à trouver un terrain ! Pour vous proposer les meilleures offres, j\'ai besoin de quelques infos. Commençons par votre département.', 'collect_info', 10, 1),

('modele', 'modele,maison,coquelicot,tulipe,hibiscus,vour,tarif maison,catalogue', '🏠 Excellente idée ! Nous avons plusieurs modèles qui pourraient vous correspondre. Pour vous orienter vers les meilleures options, quel est votre budget approximatif ?', 'collect_info', 10, 1),

('devis', 'devis,estimation,prix personnalise,combien pour moi', '📋 Je vais vous préparer un devis personnalisé ! Cela prend seulement 2 minutes. Quel est votre département de construction ?', 'start_qualification', 15, 1),

('rdv', 'rendez-vous,rdv,rencontrer,conseiller,visite,agence', '📅 Je vais vous mettre en relation avec un conseiller. Pour qu\'il puisse préparer notre échange, pouvez-vous me donner votre département et un numéro de téléphone ?', 'create_lead_priority', 15, 1),

('contact', 'telephone,contact,email,joindre,appeler', '📞 Vous pouvez nous contacter au 03 44 00 00 00 (lun-ven 9h-18h). Ou laissez-moi vos coordonnées, un conseiller vous rappellera sous 24h !', 'create_lead', 10, 1),

('salutation', 'bonjour,bonsoir,hey,salut,coucou,hello,bonjour!', 'Bonjour ! 👋 Je suis l\'assistant virtuel ORCA. Je peux vous aider à :

• 📋 Obtenir un devis personnalisé
• 🏠 Découvrir nos modèles
• 🌿 Trouver un terrain
• 📅 Prendre rendez-vous

Que souhaitez-vous faire ?', NULL, 5, 1),

('au_revoir', 'au revoir,bye,merci,ciao,a plus,bonne journee', 'Au revoir ! 👋 N\'hésitez pas à revenir si vous avez d\'autres questions. Bonne journée !', 'close', 5, 1),

('remerciement', 'merci,merci beaucoup,top,super,genial,parfait', 'Je vous en prie ! 😊 C\'est un plaisir de vous aider. Y a-t-il autre chose que je puisse faire pour vous ?', NULL, 5, 1),

('negation', 'non,non merci,na pas,pas interesse,pas pour linstant', 'Pas de problème ! Je reste disponible si vous changez d\'avis ou si vous avez d\'autres questions.', NULL, 5, 1),

('aide', 'aide,help,comment ca marche,que fais tu,tu fais quoi', '🤖 Je suis là pour vous aider avec votre projet de construction ! Je peux :

✅ Vous donner des estimations de prix
✅ Vous présenter nos modèles de maisons
✅ Vous aider à trouver un terrain
✅ Mettre en relation avec un conseiller
✅ Répondre à vos questions

Par quoi commençons-nous ?', NULL, 8, 1),

-- Intentions avancées
('reprendre', 'reprendre,continuer,plus tard,revenu,retour,je reviens', 'Je reprends où nous en étions ! Pouvez-vous me rappeler où on s\'était arrêté ? (département, surface, budget...)', 'show_steps', 10, 1),

('comparer', 'comparer,difference,versus,meilleur,choisir entre,lequel', 'Je peux vous aider à comparer nos modèles ! Quelle surface envisagez-vous ? Cela me permettra de vous proposer les meilleures options.', 'collect_info', 10, 1),

('negocier', 'negocier,rabais,remise,promo,reduction,moins cher,soldes', 'Nos prix sont compétitifs et transparents. Chaque projet étant unique, je vais vous mettre en relation avec un conseiller qui pourra étudier votre situation.', 'create_lead_priority', 10, 1),

('plan', 'plan,croquis,dessin,technique,facade,etage,rdc', '📐 Vous souhaitez voir les plans détaillés ? Je peux vous envoyer nos catalogues complets par email. Quelle est votre adresse ?', 'send_catalog', 10, 1),

('constructeur_concurrent', 'maisons pierre,maisons france confort,tradi,france confort,autre constructeur,concurrent', '🏆 ORCA se différencie par :

✅ Maisons 100% personnalisables
✅ Accompagnement de A à Z
✅ Transparence des prix
✅ Garanties décennales
✅ 30 ans d\'expérience

Souhaitez-vous découvrir nos réalisations ?', 'show_realisations', 10, 1),

('credit_refuse', 'credit refuse,banque refuse,pret refuse,financement impossible,credit pas accepte', '💪 Ne vous inquiétez pas ! Nous avons des partenaires financiers qui peuvent vous aider, même dans des situations complexes. Un conseiller peut étudier votre dossier gratuitement.', 'create_lead_priority', 15, 1),

('urgent', 'urgent,rapidement,vite,des que possible,au plus vite,presser', '⚡ J\'ai compris que c\'est urgent ! Je vais traiter votre demande en priorité. Un conseiller vous contactera aujourd\'hui. Votre numéro de téléphone ?', 'create_lead_priority', 15, 1),

('surface', 'surface,m2,metre carre,grande,maison taille', 'Pour vous orienter vers les bons modèles, quelle surface habitable envisagez-vous ? (70m², 100m², 120m²...)', 'collect_info', 10, 1),

('delai', 'delai,temps,quand,commencer,construction dure,ca prend combien de temps', '⏱️ Le délai moyen est de 6 à 8 mois après obtention du permis. Mais cela dépend de la complexité du projet. Quand souhaitez-vous démarrer ?', 'collect_info', 10, 1)

ON DUPLICATE KEY UPDATE 
    keywords = VALUES(keywords),
    response_text = VALUES(response_text),
    priority = VALUES(priority);

-- ============================================
-- INSERTIONS: Configuration par défaut
-- ============================================
INSERT INTO config (cle, valeur) VALUES
('chatbot_openai_api_key', ''),
('chatbot_openai_model', 'gpt-4'),
('chatbot_enabled', '1'),
('chatbot_auto_popup', '1'),
('chatbot_popup_delay', '30'),
('chatbot_primary_color', '#1a5653'),
('chatbot_welcome_message', 'Bonjour ! 👋 Je suis l\'assistant ORCA. Que souhaitez-vous faire ?'),
('chatbot_offline_message', 'Un conseiller vous répondra dès que possible.'),
('chatbot_email_notifications', '1'),
('chatbot_lead_threshold', '70'),
('chatbot_api_key', MD5(CONCAT('orca_', UNIX_TIMESTAMP()))),

-- Configuration pour n8n / IA
('chatbot_n8n_enabled', '0'),
('chatbot_n8n_email', 'ia@maisons-orca.fr'),
('chatbot_n8n_webhook_url', ''),
('chatbot_n8n_trigger_on_lead', '1'),
('chatbot_n8n_trigger_on_message', '0')

ON DUPLICATE KEY UPDATE cle = VALUES(cle);

-- ============================================
-- INSERTIONS: Exemple de webhook n8n
-- ============================================
INSERT INTO chatbot_webhooks (name, webhook_url, webhook_type, event_type, is_active, headers) VALUES
('n8n Lead Processing', 'https://n8n.maisons-orca.fr/webhook/chatbot-lead', 'n8n', 'lead_created', 0, '{"Content-Type": "application/json"}')
ON DUPLICATE KEY UPDATE webhook_url = VALUES(webhook_url);

-- ============================================
-- VERIFICATION
-- ============================================
SELECT 'Installation terminée avec succès !' AS message;
SELECT CONCAT('Tables créées: ', 
    (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE 'chatbot_%')
) AS stats;
