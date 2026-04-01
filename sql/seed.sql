-- ============================================
-- FRENCHYBOT - Données d'exemple
-- À exécuter APRÈS install.sql
-- ============================================

SET NAMES utf8mb4;

-- ============================================
-- Admin par défaut (mot de passe: admin123 — À CHANGER)
-- ============================================
INSERT INTO admin_users (username, email, password_hash, role) VALUES
('admin', 'admin@frenchycompany.fr', '$2y$10$YourHashHere', 'admin')
ON DUPLICATE KEY UPDATE username = VALUES(username);

-- Le hash sera généré au premier login ou via le script de setup

-- ============================================
-- Chatbot exemple : ORCA
-- ============================================
INSERT INTO chatbots (name, domain, token, secret_key, welcome_message, primary_color, auto_popup, popup_delay, notification_email) VALUES
('ORCA - Maisons', 'maisons-orca.fr', LOWER(HEX(RANDOM_BYTES(16))), LOWER(HEX(RANDOM_BYTES(32))),
 'Bonjour ! 👋 Je suis l''assistant ORCA. Que souhaitez-vous faire ?',
 '#1a5653', 1, 20, 'contact@maisons-orca.fr')
ON DUPLICATE KEY UPDATE name = VALUES(name);

SET @orca_id = LAST_INSERT_ID();

-- ============================================
-- Intentions par défaut pour le chatbot ORCA
-- ============================================
INSERT INTO chatbot_intentions (chatbot_id, intention_key, keywords, response_text, action, priority, is_active) VALUES
(@orca_id, 'prix', 'prix,tarif,combien,coute,cher,budget,euros', '💰 Nos maisons sont personnalisables et le prix dépend de vos choix (surface, finitions...). Pour une estimation précise, pouvez-vous me dire :\n\n1️⃣ Quelle surface souhaitez-vous ?\n2️⃣ Dans quel département ?\n3️⃣ Avez-vous déjà un terrain ?', 'collect_info', 10, 1),
(@orca_id, 'terrain', 'terrain,parcelle,trouver,chercher terrain,terrain a vendre', '🌿 Je peux vous aider à trouver un terrain ! Pour vous proposer les meilleures offres, j''ai besoin de quelques infos. Commençons par votre département.', 'collect_info', 10, 1),
(@orca_id, 'modele', 'modele,maison,coquelicot,tulipe,hibiscus,catalogue', '🏠 Excellente idée ! Nous avons plusieurs modèles qui pourraient vous correspondre. Pour vous orienter vers les meilleures options, quel est votre budget approximatif ?', 'collect_info', 10, 1),
(@orca_id, 'devis', 'devis,estimation,prix personnalise,combien pour moi', '📋 Je vais vous préparer un devis personnalisé ! Cela prend seulement 2 minutes. Quel est votre département de construction ?', 'start_qualification', 15, 1),
(@orca_id, 'rdv', 'rendez-vous,rdv,rencontrer,conseiller,visite,agence', '📅 Je vais vous mettre en relation avec un conseiller. Pour qu''il puisse préparer notre échange, pouvez-vous me donner votre département et un numéro de téléphone ?', 'create_lead_priority', 15, 1),
(@orca_id, 'contact', 'telephone,contact,email,joindre,appeler', '📞 Vous pouvez nous contacter au 03 44 00 00 00 (lun-ven 9h-18h). Ou laissez-moi vos coordonnées, un conseiller vous rappellera sous 24h !', 'create_lead', 10, 1),
(@orca_id, 'salutation', 'bonjour,bonsoir,hey,salut,coucou,hello', 'Bonjour ! 👋 Je suis l''assistant virtuel ORCA. Je peux vous aider à :\n\n• 📋 Obtenir un devis personnalisé\n• 🏠 Découvrir nos modèles\n• 🌿 Trouver un terrain\n• 📅 Prendre rendez-vous\n\nQue souhaitez-vous faire ?', NULL, 5, 1),
(@orca_id, 'au_revoir', 'au revoir,bye,ciao,a plus,bonne journee', 'Au revoir ! 👋 N''hésitez pas à revenir si vous avez d''autres questions. Bonne journée !', 'close', 5, 1),
(@orca_id, 'remerciement', 'merci,merci beaucoup,top,super,genial,parfait', 'Je vous en prie ! 😊 C''est un plaisir de vous aider. Y a-t-il autre chose que je puisse faire pour vous ?', NULL, 5, 1),
(@orca_id, 'negation', 'non,non merci,pas interesse,pas pour linstant', 'Pas de problème ! Je reste disponible si vous changez d''avis ou si vous avez d''autres questions.', NULL, 5, 1),
(@orca_id, 'aide', 'aide,help,comment ca marche,que fais tu,tu fais quoi', '🤖 Je suis là pour vous aider avec votre projet de construction ! Je peux :\n\n✅ Vous donner des estimations de prix\n✅ Vous présenter nos modèles de maisons\n✅ Vous aider à trouver un terrain\n✅ Mettre en relation avec un conseiller\n✅ Répondre à vos questions\n\nPar quoi commençons-nous ?', NULL, 8, 1),
(@orca_id, 'reprendre', 'reprendre,continuer,plus tard,revenu,retour,je reviens', 'Je reprends où nous en étions ! Pouvez-vous me rappeler où on s''était arrêté ? (département, surface, budget...)', 'show_steps', 10, 1),
(@orca_id, 'comparer', 'comparer,difference,versus,meilleur,choisir entre,lequel', 'Je peux vous aider à comparer nos modèles ! Quelle surface envisagez-vous ? Cela me permettra de vous proposer les meilleures options.', 'collect_info', 10, 1),
(@orca_id, 'negocier', 'negocier,rabais,remise,promo,reduction,moins cher,soldes', 'Nos prix sont compétitifs et transparents. Chaque projet étant unique, je vais vous mettre en relation avec un conseiller qui pourra étudier votre situation.', 'create_lead_priority', 10, 1),
(@orca_id, 'plan', 'plan,croquis,dessin,technique,facade,etage,rdc', '📐 Vous souhaitez voir les plans détaillés ? Je peux vous envoyer nos catalogues complets par email. Quelle est votre adresse ?', 'send_catalog', 10, 1),
(@orca_id, 'credit_refuse', 'credit refuse,banque refuse,pret refuse,financement impossible', '💪 Ne vous inquiétez pas ! Nous avons des partenaires financiers qui peuvent vous aider, même dans des situations complexes. Un conseiller peut étudier votre dossier gratuitement.', 'create_lead_priority', 15, 1),
(@orca_id, 'urgent', 'urgent,rapidement,vite,des que possible,au plus vite,presser', '⚡ J''ai compris que c''est urgent ! Je vais traiter votre demande en priorité. Un conseiller vous contactera aujourd''hui. Votre numéro de téléphone ?', 'create_lead_priority', 15, 1),
(@orca_id, 'surface', 'surface,m2,metre carre,grande,maison taille', 'Pour vous orienter vers les bons modèles, quelle surface habitable envisagez-vous ? (70m², 100m², 120m²...)', 'collect_info', 10, 1),
(@orca_id, 'delai', 'delai,temps,quand,commencer,construction dure,ca prend combien de temps', '⏱️ Le délai moyen est de 6 à 8 mois après obtention du permis. Mais cela dépend de la complexité du projet. Quand souhaitez-vous démarrer ?', 'collect_info', 10, 1);

-- ============================================
-- A/B Test exemple
-- ============================================
INSERT INTO chatbot_ab_tests (chatbot_id, name, test_type, variant_a_value, variant_b_value, status) VALUES
(@orca_id, 'Message de bienvenue', 'welcome_message',
 'Bonjour ! 👋 Je suis l''assistant ORCA. Que souhaitez-vous faire ?',
 'Bonjour ! 🏠 Vous cherchez votre future maison ? Je peux vous aider en 2 minutes !',
 'active');

SELECT 'Seed termine avec succes !' AS message;
SELECT CONCAT('Token ORCA: ', token) AS info FROM chatbots WHERE id = @orca_id;
