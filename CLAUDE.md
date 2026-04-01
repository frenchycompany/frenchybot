# FrenchyBot — Chatbot SaaS Multi-tenant

## Contexte du projet

FrenchyBot est un chatbot conversationnel intelligent extrait du projet ORCA (maisons-orca.fr) pour être déployé en tant que produit autonome sur plusieurs sites clients.

Le chatbot ORCA est opérationnel et validé. L'objectif est maintenant de le transformer en plateforme multi-tenant : un seul VPS, une seule BDD, plusieurs chatbots configurables indépendamment.

## Repo source

Le code d'origine est dans `frenchycompany/orca` branche `claude/fix-chatbot-issues-bDPTx`.

### Fichiers à reprendre depuis ORCA

| Fichier ORCA | → FrenchyBot | Ce qu'il fait |
|---|---|---|
| `js/chatbot.js` | `embed/frenchybot.js` | Widget flottant (bulle + chat) — à adapter avec token |
| `chatbot/api.php` | `api/v1/chat.php` | API conversation (init/message/form) — à ajouter auth token |
| `includes/chatbot-functions.php` | `includes/chatbot-functions.php` | Moteur : scénarios, recherche BDD, intentions, extraction critères, lead creation |
| `admin/chatbot-intentions.php` | `admin/chatbot-intentions.php` | Centre d'apprentissage (test live, stats, conflits, messages non reconnus) |
| `admin/chatbot.php` | `admin/chatbot-stats.php` | Stats conversations + taux conversion |
| `admin/chatbot-learn.php` | `admin/chatbot-learn.php` | Apprendre depuis une conversation |
| `admin/chatbot-view.php` | `admin/chatbot-view.php` | Voir le transcript d'une conversation |
| `admin/chatbot-settings.php` | `admin/chatbot-settings.php` | Paramètres (couleur, popup, n8n, IA) |
| `admin/chatbot-abtest.php` | `admin/chatbot-abtest.php` | A/B testing messages de bienvenue |
| `admin/leads.php` | `admin/leads.php` | Liste des leads avec filtres |
| `admin/lead-view.php` | `admin/lead-view.php` | Fiche détail lead |
| `chatbot/cron-followups.php` | `cron/followups.php` | Relance automatique conversations abandonnées |
| `estimation.php` | `embed/estimation-example.html` | Exemple de landing page chatbot plein écran |

## Architecture cible

```
frenchybot/
├── admin/                    ← Dashboard multi-chatbots
│   ├── index.php             ← Login
│   ├── dashboard.php         ← Liste des chatbots (en ligne / hors ligne)
│   ├── chatbot-create.php    ← Créer un nouveau chatbot + générer token
│   ├── chatbot-edit.php      ← Configurer un chatbot (couleur, popup, IA, domaine)
│   ├── chatbot-intentions.php ← Centre d'apprentissage (filtré par chatbot)
│   ├── chatbot-stats.php     ← Stats par chatbot
│   ├── chatbot-learn.php     ← Apprendre depuis conversation
│   ├── chatbot-view.php      ← Transcript conversation
│   ├── chatbot-abtest.php    ← A/B testing
│   ├── leads.php             ← Leads (filtrables par chatbot)
│   ├── lead-view.php         ← Fiche lead
│   └── includes/
│       ├── admin-header.php
│       └── admin-footer.php
├── api/
│   └── v1/
│       ├── chat.php          ← Point d'entrée API (token requis)
│       └── embed.js          ← Script d'embed généré dynamiquement
├── includes/
│   ├── config.php            ← Connexion BDD centralisée
│   ├── auth.php              ← Vérification tokens + CORS
│   ├── chatbot-functions.php ← Moteur chatbot (multi-tenant)
│   └── functions.php         ← Utilitaires (email, flash, etc.)
├── cron/
│   └── followups.php         ← Relance conversations abandonnées
├── embed/
│   ├── frenchybot.js         ← Script widget à intégrer sur les sites clients
│   └── exemple.html          ← Page d'exemple d'intégration
└── sql/
    ├── install.sql           ← Schéma complet (tables + index)
    └── seed.sql              ← Données d'exemple + intentions de base
```

## Schéma BDD

### Nouvelle table : `chatbots` (table maîtresse)

```sql
CREATE TABLE chatbots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    domain VARCHAR(255),
    token VARCHAR(64) UNIQUE NOT NULL,
    secret_key VARCHAR(64) NOT NULL,
    -- Config apparence
    welcome_message TEXT,
    primary_color VARCHAR(7) DEFAULT '#1a5653',
    auto_popup BOOLEAN DEFAULT TRUE,
    popup_delay INT DEFAULT 20,
    logo_url VARCHAR(255),
    -- Config IA (optionnel, par chatbot)
    ai_provider ENUM('none','openai','anthropic') DEFAULT 'none',
    ai_api_key VARCHAR(255),
    ai_model VARCHAR(50) DEFAULT 'gpt-4',
    -- Config n8n/webhook (optionnel, par chatbot)
    webhook_enabled BOOLEAN DEFAULT FALSE,
    webhook_url VARCHAR(500),
    email_notifications BOOLEAN DEFAULT TRUE,
    notification_email VARCHAR(255),
    -- Statut
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Tables existantes modifiées (ajout `chatbot_id`)

```sql
-- Chaque table existante reçoit une colonne chatbot_id
ALTER TABLE chatbot_conversations ADD COLUMN chatbot_id INT NOT NULL AFTER id,
    ADD INDEX idx_chatbot (chatbot_id),
    ADD FOREIGN KEY (chatbot_id) REFERENCES chatbots(id);

ALTER TABLE chatbot_intentions ADD COLUMN chatbot_id INT NOT NULL AFTER id,
    ADD INDEX idx_chatbot (chatbot_id),
    ADD FOREIGN KEY (chatbot_id) REFERENCES chatbots(id);

ALTER TABLE leads ADD COLUMN chatbot_id INT AFTER id,
    ADD INDEX idx_chatbot (chatbot_id);

ALTER TABLE chatbot_ab_tests ADD COLUMN chatbot_id INT NOT NULL AFTER id,
    ADD INDEX idx_chatbot (chatbot_id);

ALTER TABLE chatbot_followups ADD COLUMN chatbot_id INT AFTER id;
```

### Tables à créer directement avec chatbot_id

Le fichier `sql/install.sql` doit contenir le schéma complet de toutes les tables AVEC `chatbot_id` dès le départ (pas en ALTER).

## Code d'intégration client

### Méthode 1 : Widget flottant (bulle en bas à droite)
```html
<!-- FrenchyBot -->
<script src="https://bot.frenchycompany.fr/api/v1/embed.js"
        data-token="TOKEN_PUBLIC_DU_CHATBOT"></script>
```

### Méthode 2 : Iframe (chatbot intégré dans une page)
```html
<iframe src="https://bot.frenchycompany.fr/api/v1/chat.php?token=TOKEN&mode=inline"
        width="100%" height="600" frameborder="0"></iframe>
```

### Méthode 3 : Landing page (plein écran)
```html
<iframe src="https://bot.frenchycompany.fr/api/v1/chat.php?token=TOKEN&mode=fullpage"
        width="100%" height="100vh" frameborder="0"
        style="position:fixed;inset:0;border:none;"></iframe>
```

## Flux API

```
Site client                      FrenchyBot VPS
    │                                │
    │  <script embed.js              │
    │   data-token="abc123">         │
    │                                │
    │  GET /api/v1/embed.js          │
    │   ?token=abc123                │
    │ ─────────────────────────────→ │
    │                                │ Vérifie token → chatbot_id=7
    │                                │ Vérifie domain (CORS)
    │  ←───────────────────────────  │
    │  JS widget avec config         │ (couleur, popup, message)
    │                                │
    │  POST /api/v1/chat.php         │
    │   token=abc123                 │
    │   action=init                  │
    │ ─────────────────────────────→ │
    │                                │ Crée conversation pour chatbot 7
    │                                │ Charge intentions du chatbot 7
    │                                │ Applique A/B test du chatbot 7
    │  ←───────────────────────────  │
    │  {message, options}            │
    │                                │
    │  POST /api/v1/chat.php         │
    │   token=abc123                 │
    │   action=message               │
    │   conversation_id=42           │
    │   message="je cherche..."      │
    │ ─────────────────────────────→ │
    │                                │ Détecte intention (chatbot 7)
    │                                │ Recherche BDD (si configuré)
    │  ←───────────────────────────  │
    │  {message, chips}              │
```

## Sécurité

- **Token public** (`token`) : identifie le chatbot, transmis côté client, non secret
- **Secret key** (`secret_key`) : utilisé pour l'API admin, jamais exposé côté client
- **CORS** : `api/v1/chat.php` vérifie que `Origin` correspond au `domain` du chatbot
- **Rate limiting** : par IP + par token (anti-spam)
- **Validation domaine** : le script embed ne fonctionne que sur le domaine enregistré

## Fonctionnalités par chatbot

Chaque chatbot peut avoir indépendamment :

| Feature | Configurable par chatbot |
|---|---|
| Message de bienvenue | ✅ `welcome_message` |
| Couleur du widget | ✅ `primary_color` |
| Popup automatique | ✅ `auto_popup` + `popup_delay` |
| Intentions / Réponses | ✅ table `chatbot_intentions` filtrée |
| Clé OpenAI / Anthropic | ✅ `ai_provider` + `ai_api_key` |
| Webhook n8n/Zapier | ✅ `webhook_url` |
| Email notification | ✅ `notification_email` |
| A/B testing | ✅ table `chatbot_ab_tests` filtrée |
| Leads | ✅ table `leads` filtrée |

## Refactoring nécessaire

### 1. Toutes les fonctions PHP doivent accepter `$chatbot_id`

```php
// Avant (ORCA)
function chatbotDetectIntention($message) {
    $stmt = $pdo->query("SELECT * FROM chatbot_intentions WHERE is_active = 1");

// Après (FrenchyBot)
function chatbotDetectIntention($message, $chatbot_id) {
    $stmt = $pdo->prepare("SELECT * FROM chatbot_intentions WHERE is_active = 1 AND chatbot_id = ?");
    $stmt->execute([$chatbot_id]);
```

Fonctions à refactorer :
- `chatbotGetOrCreateConversation($chatbot_id)`
- `chatbotDetectIntention($message, $chatbot_id)`
- `chatbotGetScenario()` → peut devenir configurable par chatbot (v2)
- `chatbotCreateLead($conversation_id, $data)` → ajouter `chatbot_id` dans INSERT
- `chatbotSearchModeles()` → optionnel (pas tous les clients ont des modèles)
- `chatbotSearchTerrains()` → optionnel

### 2. `api/v1/chat.php` — Auth + multi-tenant

```php
// Début de chaque requête API
$token = $_POST['token'] ?? $_GET['token'] ?? '';
$chatbot = getChatbotByToken($token);
if (!$chatbot || !$chatbot['is_active']) {
    respond(['error' => 'Token invalide']);
}

// Vérifier CORS
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($chatbot['domain'] && strpos($origin, $chatbot['domain']) === false) {
    http_response_code(403);
    exit;
}

$chatbot_id = $chatbot['id'];
// ... toutes les fonctions utilisent $chatbot_id
```

### 3. `api/v1/embed.js` — Script dynamique

```php
<?php
// embed.js est en fait un PHP qui retourne du JS
header('Content-Type: application/javascript');
$token = $_GET['token'] ?? '';
$chatbot = getChatbotByToken($token);
if (!$chatbot) { echo '/* Invalid token */'; exit; }
?>
(function() {
    var config = {
        token: <?= json_encode($token) ?>,
        apiUrl: <?= json_encode('https://bot.frenchycompany.fr/api/v1/chat.php') ?>,
        color: <?= json_encode($chatbot['primary_color']) ?>,
        autoPopup: <?= $chatbot['auto_popup'] ? 'true' : 'false' ?>,
        popupDelay: <?= intval($chatbot['popup_delay']) ?>,
        welcomeMessage: <?= json_encode($chatbot['welcome_message']) ?>
    };
    // ... widget code (repris de chatbot.js avec config dynamique)
})();
```

## Dashboard admin — Pages à créer

### `admin/dashboard.php`
- Liste de tous les chatbots avec : nom, domaine, leads count, statut (actif/inactif)
- Bouton copier le code d'intégration
- Indicateur temps réel : conversations actives

### `admin/chatbot-create.php`
- Formulaire : nom, domaine, couleur, message de bienvenue
- Génère automatiquement le token (random 32 bytes hex)
- Option : cloner les intentions d'un chatbot existant

### `admin/chatbot-edit.php`
- Tous les paramètres du chatbot
- Section IA : provider (none/openai/anthropic) + clé API + modèle
- Section webhook : URL + activer/désactiver
- Section email : adresse de notification
- Régénérer le token
- Code d'intégration à copier

## Ordre d'implémentation recommandé

1. **`sql/install.sql`** — Schéma complet avec `chatbot_id` partout
2. **`includes/config.php` + `auth.php`** — Connexion BDD + vérification tokens
3. **`includes/chatbot-functions.php`** — Refactorer avec `$chatbot_id`
4. **`api/v1/chat.php`** — API avec auth token
5. **`api/v1/embed.js`** — Script widget dynamique
6. **`admin/dashboard.php`** — Liste des chatbots
7. **`admin/chatbot-create.php`** + **`chatbot-edit.php`** — CRUD chatbots
8. **`admin/chatbot-intentions.php`** — Centre d'apprentissage (filtré)
9. **`admin/leads.php`** — Leads filtrés par chatbot
10. **Tester** — Créer chatbot "ORCA", intégrer sur maisons-orca.fr
11. **`embed/exemple.html`** — Documentation d'intégration

## Infos VPS

- Serveur : VPS existant (même que ORCA)
- Chemin : `/var/www/frenchybot/`
- BDD : même MySQL, nouvelle base `frenchybot` (ou schema séparé)
- PHP : 8.x
- Les fichiers ORCA restent en place, le chatbot ORCA sera migré vers FrenchyBot en dernier

## Ce qui est déjà fonctionnel dans ORCA (à reprendre tel quel)

- ✅ Scénarios conversationnels complets (maison/terrain/devis/question)
- ✅ Chips légers + ton humain + collecte step-by-step
- ✅ Extraction intelligente multi-critères ("terrain 500m² dans l'oise")
- ✅ Recherche BDD modèles + terrains
- ✅ 42 intentions avec réponses riches
- ✅ Actions lien vers pages du site (configurable dans admin)
- ✅ Centre d'apprentissage (test live, stats, conflits, messages non reconnus)
- ✅ A/B testing message de bienvenue
- ✅ Email notification admin à chaque lead
- ✅ CRON de relance conversations abandonnées
- ✅ Exit intent popup + bannière CTA sticky
- ✅ Landing page chatbot plein écran
- ✅ Chatbot contextualisé sur les fiches produit
