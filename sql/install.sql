-- ============================================
-- FRENCHYBOT - Installation Complète Multi-Tenant
-- mysql -u root -p frenchybot < sql/install.sql
-- ============================================

SET NAMES utf8mb4;

-- ============================================
-- TABLE: Chatbots (table maîtresse multi-tenant)
-- ============================================
CREATE TABLE IF NOT EXISTS chatbots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    domain VARCHAR(255) DEFAULT NULL,
    token VARCHAR(64) NOT NULL,
    secret_key VARCHAR(64) NOT NULL,
    -- Config apparence
    welcome_message TEXT DEFAULT NULL,
    primary_color VARCHAR(7) DEFAULT '#1a5653',
    auto_popup TINYINT(1) DEFAULT 1,
    popup_delay INT DEFAULT 20,
    logo_url VARCHAR(255) DEFAULT NULL,
    -- Config IA
    ai_provider ENUM('none','openai','anthropic') DEFAULT 'none',
    ai_api_key VARCHAR(255) DEFAULT NULL,
    ai_model VARCHAR(50) DEFAULT 'gpt-4',
    -- Config webhook
    webhook_enabled TINYINT(1) DEFAULT 0,
    webhook_url VARCHAR(500) DEFAULT NULL,
    email_notifications TINYINT(1) DEFAULT 1,
    notification_email VARCHAR(255) DEFAULT NULL,
    -- Config BDD externe (produits generiques)
    ext_db_enabled TINYINT(1) DEFAULT 0,
    ext_db_host VARCHAR(255) DEFAULT 'localhost',
    ext_db_name VARCHAR(100) DEFAULT NULL,
    ext_db_user VARCHAR(100) DEFAULT NULL,
    ext_db_pass VARCHAR(255) DEFAULT NULL,
    ext_db_products JSON DEFAULT NULL COMMENT 'Config des tables produits: [{type, table, col_name, col_price, col_description, col_location, col_surface, col_image, col_active, col_category, search_fields}]',
    -- Statut
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_token (token),
    INDEX idx_active (is_active),
    INDEX idx_domain (domain)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: Conversations
-- ============================================
CREATE TABLE IF NOT EXISTS chatbot_conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chatbot_id INT NOT NULL,
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

    INDEX idx_chatbot (chatbot_id),
    INDEX idx_session_active (session_id, is_active),
    INDEX idx_started (started_at),
    INDEX idx_lead (lead_id),
    INDEX idx_ab_test (ab_test_id, ab_variant),
    INDEX idx_is_active (is_active),
    INDEX idx_page_source (page_source),
    FOREIGN KEY (chatbot_id) REFERENCES chatbots(id) ON DELETE CASCADE
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
    chatbot_id INT NOT NULL,
    intention_key VARCHAR(100) NOT NULL,
    keywords TEXT NOT NULL,
    response_text TEXT NOT NULL,
    action VARCHAR(50) DEFAULT NULL,
    priority INT DEFAULT 10,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_chatbot (chatbot_id),
    INDEX idx_active (is_active),
    INDEX idx_priority (priority),
    INDEX idx_chatbot_key (chatbot_id, intention_key),
    FOREIGN KEY (chatbot_id) REFERENCES chatbots(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: A/B Tests
-- ============================================
CREATE TABLE IF NOT EXISTS chatbot_ab_tests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chatbot_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    test_type VARCHAR(50) NOT NULL,
    variant_a_value TEXT NOT NULL,
    variant_b_value TEXT NOT NULL,
    status ENUM('active', 'completed', 'paused') DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    ended_at DATETIME DEFAULT NULL,

    INDEX idx_chatbot (chatbot_id),
    INDEX idx_status (status),
    INDEX idx_type (test_type),
    FOREIGN KEY (chatbot_id) REFERENCES chatbots(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: Leads
-- ============================================
CREATE TABLE IF NOT EXISTS leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chatbot_id INT DEFAULT NULL,
    nom VARCHAR(100) DEFAULT NULL,
    prenom VARCHAR(100) DEFAULT NULL,
    email VARCHAR(255) DEFAULT NULL,
    telephone VARCHAR(20) DEFAULT NULL,
    departement VARCHAR(10) DEFAULT NULL,
    surface_souhaitee VARCHAR(50) DEFAULT NULL,
    budget_estime VARCHAR(50) DEFAULT NULL,
    terrain_prevu TINYINT(1) DEFAULT 0,
    type_demande VARCHAR(50) DEFAULT 'devis',
    source VARCHAR(50) DEFAULT 'chatbot',
    page_source VARCHAR(255) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    status ENUM('new','contacted','qualified','converted','lost') DEFAULT 'new',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_chatbot (chatbot_id),
    INDEX idx_status (status),
    INDEX idx_created (created_at),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: Relances Automatiques
-- ============================================
CREATE TABLE IF NOT EXISTS chatbot_followups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chatbot_id INT DEFAULT NULL,
    conversation_id INT NOT NULL,
    lead_data JSON DEFAULT NULL,
    followup_date DATETIME NOT NULL,
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    status ENUM('pending', 'sent', 'cancelled', 'converted') DEFAULT 'pending',
    email_subject VARCHAR(255) DEFAULT NULL,
    email_content TEXT DEFAULT NULL,
    sent_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_chatbot (chatbot_id),
    INDEX idx_status_date (status, followup_date),
    INDEX idx_conversation (conversation_id),
    FOREIGN KEY (conversation_id) REFERENCES chatbot_conversations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: Analytics temps réel
-- ============================================
CREATE TABLE IF NOT EXISTS chatbot_analytics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chatbot_id INT NOT NULL,
    date DATE NOT NULL,
    hour INT NOT NULL,
    conversations INT DEFAULT 0,
    messages INT DEFAULT 0,
    leads_generated INT DEFAULT 0,
    avg_score DECIMAL(5,2) DEFAULT 0,
    avg_duration INT DEFAULT 0,

    UNIQUE KEY unique_chatbot_date_hour (chatbot_id, date, hour),
    INDEX idx_chatbot (chatbot_id),
    INDEX idx_date (date),
    FOREIGN KEY (chatbot_id) REFERENCES chatbots(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: Patterns utilisateur
-- ============================================
CREATE TABLE IF NOT EXISTS chatbot_user_patterns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chatbot_id INT DEFAULT NULL,
    pattern_type VARCHAR(50) NOT NULL,
    pattern_value VARCHAR(255) NOT NULL,
    frequency INT DEFAULT 1,
    last_used DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY unique_pattern (chatbot_id, pattern_type, pattern_value),
    INDEX idx_type_freq (pattern_type, frequency)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: Utilisateurs admin
-- ============================================
CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','manager','client') DEFAULT 'manager',
    chatbot_id INT DEFAULT NULL COMMENT 'NULL pour admin (voit tout), set pour client (voit uniquement son chatbot)',
    is_active TINYINT(1) DEFAULT 1,
    last_login DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uk_username (username),
    UNIQUE KEY uk_email (email),
    INDEX idx_chatbot (chatbot_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- VERIFICATION
-- ============================================
SELECT 'FrenchyBot - Installation terminee avec succes !' AS message;
SELECT CONCAT('Tables creees: ',
    (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN
        ('chatbots','chatbot_conversations','chatbot_messages','chatbot_intentions','chatbot_ab_tests',
         'leads','chatbot_followups','chatbot_analytics','chatbot_user_patterns','admin_users'))
) AS stats;
