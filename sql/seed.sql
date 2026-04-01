-- ============================================
-- FRENCHYBOT - Donnees d'exemple (seed)
-- Usage: mysql -u root -p frenchybot < seed.sql
-- A executer APRES install.sql
-- ============================================

SET NAMES utf8mb4;

-- ============================================
-- Admin par defaut (mot de passe: admin123 -> a changer !)
-- ============================================
INSERT INTO admins (username, password_hash, email, is_super_admin) VALUES
('admin', '$2y$10$YourHashHere', 'admin@frenchycompany.fr', 1)
ON DUPLICATE KEY UPDATE username = VALUES(username);

-- ============================================
-- Chatbot ORCA (premier client - migration)
-- Token genere aleatoirement, a remplacer en prod
-- ============================================
INSERT INTO chatbots (name, domain, token, secret_key, welcome_message, primary_color, auto_popup, popup_delay, notification_email, is_active) VALUES
('ORCA - Maisons Orca', 'maisons-orca.fr', 'orca_pub_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6', 'orca_sec_z9y8x7w6v5u4t3s2r1q0p9o8n7m6l5k4', 'Bonjour ! 👋 Je suis l''assistant ORCA.\n\nJe peux vous trouver la maison et le terrain idéal. Qu''est-ce qui vous ferait plaisir ?', '#1a5653', 1, 20, 'contact@maisons-orca.fr', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Recuperer l'ID du chatbot ORCA
SET @orca_id = (SELECT id FROM chatbots WHERE token = 'orca_pub_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6' LIMIT 1);

-- ============================================
-- Intentions par defaut pour ORCA
-- ============================================
INSERT INTO chatbot_intentions (chatbot_id, intention_key, keywords, response_text, action, priority, is_active) VALUES

-- Intentions generales
(@orca_id, 'prix', 'prix,tarif,combien,coute,cher,budget,euros',
'💰 Nos maisons sont personnalisables et le prix dépend de vos choix (surface, finitions...). Pour une estimation précise, pouvez-vous me dire :\n\n1️⃣ Quelle surface souhaitez-vous ?\n2️⃣ Dans quel département ?\n3️⃣ Avez-vous déjà un terrain ?', 'collect_info', 10, 1),

(@orca_id, 'terrain', 'terrain,parcelle,trouver,chercher,terrain a vendre',
'🌿 Je peux vous aider à trouver un terrain ! Pour vous proposer les meilleures offres, j''ai besoin de quelques infos. Commençons par votre département.', 'collect_info', 10, 1),

(@orca_id, 'modele', 'modele,maison,coquelicot,tulipe,hibiscus,catalogue',
'🏠 Excellente idée ! Nous avons plusieurs modèles qui pourraient vous correspondre. Pour vous orienter vers les meilleures options, quel est votre budget approximatif ?', 'collect_info', 10, 1),

(@orca_id, 'devis', 'devis,estimation,prix personnalise,combien pour moi',
'📋 Je vais vous préparer un devis personnalisé ! Cela prend seulement 2 minutes. Quel est votre département de construction ?', 'start_qualification', 15, 1),

(@orca_id, 'rdv', 'rendez-vous,rdv,rencontrer,conseiller,visite,agence',
'📅 Je vais vous mettre en relation avec un conseiller. Pour qu''il puisse préparer notre échange, pouvez-vous me donner votre département et un numéro de téléphone ?', 'create_lead_priority', 15, 1),

(@orca_id, 'contact', 'telephone,contact,email,joindre,appeler',
'📞 Vous pouvez nous contacter au 03 44 00 00 00 (lun-ven 9h-18h). Ou laissez-moi vos coordonnées, un conseiller vous rappellera sous 24h !', 'create_lead', 10, 1),

(@orca_id, 'salutation', 'bonjour,bonsoir,hey,salut,coucou,hello',
'Bonjour ! 👋 Je suis l''assistant virtuel ORCA. Je peux vous aider à :\n\n• 📋 Obtenir un devis personnalisé\n• 🏠 Découvrir nos modèles\n• 🌿 Trouver un terrain\n• 📅 Prendre rendez-vous\n\nQue souhaitez-vous faire ?', NULL, 5, 1),

(@orca_id, 'au_revoir', 'au revoir,bye,ciao,a plus,bonne journee',
'Au revoir ! 👋 N''hésitez pas à revenir si vous avez d''autres questions. Bonne journée !', 'close', 5, 1),

(@orca_id, 'remerciement', 'merci,merci beaucoup,top,super,genial,parfait',
'Je vous en prie ! 😊 C''est un plaisir de vous aider. Y a-t-il autre chose que je puisse faire pour vous ?', NULL, 5, 1),

(@orca_id, 'negation', 'non,non merci,pas interesse,pas pour linstant',
'Pas de problème ! Je reste disponible si vous changez d''avis ou si vous avez d''autres questions.', NULL, 5, 1),

(@orca_id, 'aide', 'aide,help,comment ca marche,que fais tu,tu fais quoi',
'🤖 Je suis là pour vous aider avec votre projet de construction ! Je peux :\n\n✅ Vous donner des estimations de prix\n✅ Vous présenter nos modèles de maisons\n✅ Vous aider à trouver un terrain\n✅ Mettre en relation avec un conseiller\n✅ Répondre à vos questions\n\nPar quoi commençons-nous ?', NULL, 8, 1),

-- Intentions avancees
(@orca_id, 'reprendre', 'reprendre,continuer,plus tard,revenu,retour,je reviens',
'Je reprends où nous en étions ! Pouvez-vous me rappeler où on s''était arrêté ? (département, surface, budget...)', 'show_steps', 10, 1),

(@orca_id, 'comparer', 'comparer,difference,versus,meilleur,choisir entre,lequel',
'Je peux vous aider à comparer nos modèles ! Quelle surface envisagez-vous ? Cela me permettra de vous proposer les meilleures options.', 'collect_info', 10, 1),

(@orca_id, 'negocier', 'negocier,rabais,remise,promo,reduction,moins cher,soldes',
'Nos prix sont compétitifs et transparents. Chaque projet étant unique, je vais vous mettre en relation avec un conseiller qui pourra étudier votre situation.', 'create_lead_priority', 10, 1),

(@orca_id, 'plan', 'plan,croquis,dessin,technique,facade,etage,rdc',
'📐 Vous souhaitez voir les plans détaillés ? Je peux vous envoyer nos catalogues complets par email. Quelle est votre adresse ?', 'send_catalog', 10, 1),

(@orca_id, 'constructeur_concurrent', 'maisons pierre,maisons france confort,tradi,autre constructeur,concurrent',
'🏆 ORCA se différencie par :\n\n✅ Maisons 100% personnalisables\n✅ Accompagnement de A à Z\n✅ Transparence des prix\n✅ Garanties décennales\n✅ 30 ans d''expérience\n\nSouhaitez-vous découvrir nos réalisations ?', 'show_realisations', 10, 1),

(@orca_id, 'credit_refuse', 'credit refuse,banque refuse,pret refuse,financement impossible',
'💪 Ne vous inquiétez pas ! Nous avons des partenaires financiers qui peuvent vous aider, même dans des situations complexes. Un conseiller peut étudier votre dossier gratuitement.', 'create_lead_priority', 15, 1),

(@orca_id, 'urgent', 'urgent,rapidement,vite,des que possible,au plus vite,presser',
'⚡ J''ai compris que c''est urgent ! Je vais traiter votre demande en priorité. Un conseiller vous contactera aujourd''hui. Votre numéro de téléphone ?', 'create_lead_priority', 15, 1),

(@orca_id, 'surface', 'surface,m2,metre carre,grande,maison taille',
'Pour vous orienter vers les bons modèles, quelle surface habitable envisagez-vous ? (70m², 100m², 120m²...)', 'collect_info', 10, 1),

(@orca_id, 'delai', 'delai,temps,quand,commencer,construction dure,ca prend combien de temps',
'⏱️ Le délai moyen est de 6 à 8 mois après obtention du permis. Mais cela dépend de la complexité du projet. Quand souhaitez-vous démarrer ?', 'collect_info', 10, 1)

ON DUPLICATE KEY UPDATE
    keywords = VALUES(keywords),
    response_text = VALUES(response_text),
    priority = VALUES(priority);

-- ============================================
-- Webhook exemple pour ORCA
-- ============================================
INSERT INTO chatbot_webhooks (chatbot_id, name, webhook_url, webhook_type, event_type, is_active, headers) VALUES
(@orca_id, 'n8n Lead Processing', 'https://n8n.maisons-orca.fr/webhook/chatbot-lead', 'n8n', 'lead_created', 0, '{"Content-Type": "application/json"}')
ON DUPLICATE KEY UPDATE webhook_url = VALUES(webhook_url);

-- ============================================
-- VERIFICATION
-- ============================================
SELECT 'Seed termine !' AS message;
SELECT CONCAT('Chatbot ORCA cree avec ID: ', @orca_id) AS info;
SELECT CONCAT('Intentions inserees: ', COUNT(*)) AS stats FROM chatbot_intentions WHERE chatbot_id = @orca_id;
