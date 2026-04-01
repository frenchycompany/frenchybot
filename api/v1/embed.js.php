<?php
/**
 * FrenchyBot - Script d'embed dynamique
 * Usage: <script src="https://bot.frenchycompany.fr/api/v1/embed.js.php?token=TOKEN"></script>
 */
define('FRENCHYBOT', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$token = $_GET['token'] ?? '';
$chatbot = getChatbotByToken($token);
if (!$chatbot) {
    echo '/* FrenchyBot: Invalid token */';
    exit;
}

// CORS check
$origin = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
if (!empty($chatbot['domain']) && !domainMatches($origin, $chatbot['domain'])) {
    echo '/* FrenchyBot: Domain not authorized */';
    exit;
}

$config = [
    'token' => $token,
    'apiUrl' => FB_BASE_URL . '/api/v1/chat.php',
    'color' => $chatbot['primary_color'] ?: '#1a5653',
    'autoPopup' => (bool)$chatbot['auto_popup'],
    'popupDelay' => (int)($chatbot['popup_delay'] ?: 20),
    'welcomeMessage' => $chatbot['welcome_message'] ?: 'Bonjour ! Comment puis-je vous aider ?',
    'name' => $chatbot['name'] ?: 'FrenchyBot',
    'logoUrl' => $chatbot['logo_url'] ?: '',
];
$configJSON = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
(function() {
    'use strict';

    var config = <?= $configJSON ?>;
    var chatId = null, currentStep = 1;

    // ==========================================
    // Darken color utility
    // ==========================================
    function darkenColor(hex, amount) {
        hex = hex.replace('#', '');
        var r = Math.max(0, parseInt(hex.substring(0, 2), 16) - amount);
        var g = Math.max(0, parseInt(hex.substring(2, 4), 16) - amount);
        var b = Math.max(0, parseInt(hex.substring(4, 6), 16) - amount);
        return '#' + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
    }

    var colorDark = darkenColor(config.color, 30);

    // ==========================================
    // Widget HTML
    // ==========================================
    function createWidget() {
        var el = document.createElement('div');
        el.id = 'fb-chatbot';
        el.innerHTML =
            '<div id="fb-win" style="display:none;position:fixed;bottom:90px;right:20px;width:400px;background:#fff;border-radius:16px;box-shadow:0 10px 40px rgba(0,0,0,0.25);z-index:10000;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;overflow:hidden;flex-direction:column;">' +
                '<div style="background:linear-gradient(135deg,' + config.color + ',' + colorDark + ');color:#fff;padding:14px 20px;display:flex;align-items:center;gap:12px;">' +
                    '<div style="width:36px;height:36px;background:rgba(255,255,255,.15);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:17px;">' +
                        (config.logoUrl ? '<img src="' + config.logoUrl + '" style="width:24px;height:24px;border-radius:50%;object-fit:cover;" alt="">' : '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>') +
                    '</div>' +
                    '<div style="flex:1;"><div style="font-weight:700;font-size:15px;">' + escapeHtml(config.name) + '</div><div style="font-size:11px;opacity:.7;">En ligne</div></div>' +
                    '<span id="fb-close" style="cursor:pointer;font-size:20px;opacity:.7;padding:4px 8px;">&#10005;</span>' +
                '</div>' +
                '<div id="fb-msgs" style="overflow-y:auto;padding:16px;background:#f5f7f9;min-height:220px;max-height:400px;"></div>' +
                '<div id="fb-inp" style="padding:10px 14px;background:#fff;border-top:1px solid #eee;display:flex;gap:8px;">' +
                    '<input id="fb-txt" type="text" placeholder="Tapez votre message..." style="flex:1;padding:10px 14px;border:1px solid #ddd;border-radius:24px;font-size:13px;outline:none;" autocomplete="off">' +
                    '<button id="fb-go" style="padding:10px 16px;background:' + config.color + ';color:#fff;border:none;border-radius:24px;cursor:pointer;font-size:13px;font-weight:700;">&uarr;</button>' +
                '</div>' +
            '</div>' +
            '<div id="fb-fab" style="position:fixed;bottom:20px;right:20px;width:62px;height:62px;background:linear-gradient(135deg,' + config.color + ',' + colorDark + ');border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:0 4px 15px rgba(0,0,0,0.3);z-index:10000;transition:transform .2s;">' +
                '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>' +
            '</div>' +
            '<div id="fb-bubble" style="display:none;position:fixed;bottom:90px;right:20px;background:#fff;padding:12px 16px;border-radius:12px;box-shadow:0 4px 15px rgba(0,0,0,0.15);z-index:10000;max-width:260px;font-size:13px;cursor:pointer;animation:fbfade .4s ease;">' +
                '<div style="font-weight:600;color:' + config.color + ';">Besoin d\'aide ?</div>' +
                '<div style="color:#555;margin-top:4px;">' + escapeHtml(config.welcomeMessage).substring(0, 80) + '</div>' +
                '<span id="fb-bubble-close" style="position:absolute;top:6px;right:10px;cursor:pointer;color:#999;font-size:16px;">&times;</span>' +
            '</div>';
        document.body.appendChild(el);

        q('fb-fab').onmouseover = function() { this.style.transform = 'scale(1.1)'; };
        q('fb-fab').onmouseout = function() { this.style.transform = 'scale(1)'; };
        q('fb-fab').onclick = toggleChat;
        q('fb-close').onclick = toggleChat;
        q('fb-go').onclick = sendText;
        q('fb-txt').onkeypress = function(e) { if (e.key === 'Enter') sendText(); };
        q('fb-bubble').onclick = function() { q('fb-bubble').style.display = 'none'; toggleChat(); };
        q('fb-bubble-close').onclick = function(e) { e.stopPropagation(); q('fb-bubble').style.display = 'none'; };

        // Auto popup bubble after delay
        if (config.autoPopup) {
            setTimeout(function() {
                if (q('fb-win').style.display !== 'flex') {
                    q('fb-bubble').style.display = 'block';
                }
            }, config.popupDelay * 1000);
        }
    }

    function q(id) { return document.getElementById(id); }

    function escapeHtml(text) {
        var d = document.createElement('div');
        d.textContent = text;
        return d.innerHTML;
    }

    // ==========================================
    // Toggle
    // ==========================================
    function toggleChat() {
        var win = q('fb-win');
        var open = win.style.display === 'flex';
        win.style.display = open ? 'none' : 'flex';
        q('fb-bubble').style.display = 'none';
        if (!open && !chatId) initChat();
        if (!open) {
            try { window.dispatchEvent(new Event('frenchybot-opened')); } catch(e) {}
        }
    }

    // Public API
    window.FrenchyBot = {
        open: function() { if (q('fb-win').style.display !== 'flex') toggleChat(); },
        close: function() { if (q('fb-win').style.display === 'flex') toggleChat(); },
        isOpen: function() { return q('fb-win').style.display === 'flex'; }
    };

    // ==========================================
    // Init
    // ==========================================
    function initChat() {
        showTyping();
        post('action=init&token=' + encodeURIComponent(config.token), function(d) {
            hideTyping();
            if (d.disabled) return;
            if (d.error) { addMsg(d.error, 'bot'); return; }
            chatId = d.conversation_id;
            currentStep = d.step || 1;

            if (d.is_new) {
                addMsg(d.message, 'bot');
                if (d.options) showChips(d.options);
            } else if (d.history && d.history.length) {
                d.history.forEach(function(m) { addMsg(m.message, m.type); });
            }
        });
    }

    // ==========================================
    // Send message
    // ==========================================
    function sendText() {
        var inp = q('fb-txt');
        var txt = inp.value.trim();
        if (!txt) return;
        inp.value = '';
        send(txt);
    }

    function send(val, label) {
        addMsg(label || val, 'user');
        clearChips();
        showInput();
        showTyping();

        post('action=message&token=' + encodeURIComponent(config.token) + '&conversation_id=' + chatId + '&message=' + encodeURIComponent(val), function(d) {
            hideTyping();
            if (d.error) { addMsg(d.error, 'bot'); return; }
            currentStep = d.step || currentStep;

            if (d.message) addMsg(d.message, 'bot');

            if (d.type === 'final') {
                hideInput();
                if (d.options) showChips(d.options);
            } else if (d.type === 'results_then_form' || d.type === 'form') {
                if (d.options) showChips(d.options);
                else if (d.type === 'results_then_form') {
                    showChips([
                        {label: 'Ca m\'interesse', value: 'coord', next: 50},
                        {label: 'Autres criteres', value: 'autre', next: 40},
                        {label: 'J\'ai une question', value: 'go_question', next: 40}
                    ]);
                }
            } else if (d.options) {
                showChips(d.options);
            }

            if (d.field) {
                var inp = q('fb-txt');
                var placeholders = {
                    'prenom': 'Votre prenom...',
                    'nom': 'Votre nom...',
                    'email': 'Votre email...',
                    'telephone': 'Votre telephone (06 12 34 56 78)...'
                };
                inp.placeholder = placeholders[d.field] || 'Tapez votre message...';
                inp.focus();
            }
        });
    }

    // ==========================================
    // Messages
    // ==========================================
    function addMsg(text, type) {
        if (!text) return;
        var c = q('fb-msgs');
        var d = document.createElement('div');
        var bot = (type === 'bot');

        d.style.cssText = 'margin:6px 0;padding:10px 14px;border-radius:16px;max-width:88%;font-size:13px;line-height:1.55;word-wrap:break-word;animation:fbfade .3s ease;' +
            (bot ? 'background:#fff;color:#333;margin-right:auto;border:1px solid #e8e8e8;border-bottom-left-radius:4px;box-shadow:0 1px 2px rgba(0,0,0,0.04);'
                 : 'background:' + config.color + ';color:#fff;margin-left:auto;border-bottom-right-radius:4px;max-width:75%;');

        var safe = text.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        d.innerHTML = safe.replace(/\*\*(.+?)\*\*/g,'<strong>$1</strong>').replace(/\n/g,'<br>').replace(/\u2192/g,'<span style="color:' + config.color + ';">\u2192</span>');

        c.appendChild(d);
        c.scrollTop = c.scrollHeight;
    }

    // ==========================================
    // Chips
    // ==========================================
    function showChips(opts) {
        if (!opts || !opts.length) return;
        var c = q('fb-msgs');
        var w = document.createElement('div');
        w.className = 'fb-chips';
        w.style.cssText = 'margin:8px 0;display:flex;flex-wrap:wrap;gap:6px;animation:fbfade .3s ease;';

        opts.forEach(function(o) {
            var b = document.createElement('button');
            b.textContent = o.label;
            b.style.cssText = 'padding:8px 14px;background:#fff;border:1.5px solid ' + config.color + ';border-radius:20px;cursor:pointer;font-size:12px;color:' + config.color + ';font-weight:500;transition:all .15s;white-space:nowrap;';
            b.onmouseenter = function() { this.style.background = config.color; this.style.color = '#fff'; };
            b.onmouseleave = function() { this.style.background = '#fff'; this.style.color = config.color; };
            b.onclick = function() {
                if (o.action === 'close') { toggleChat(); return; }
                if (o.action === 'link' && o.url) { window.location.href = o.url; return; }
                send(o.value, o.label);
            };
            w.appendChild(b);
        });
        c.appendChild(w);
        c.scrollTop = c.scrollHeight;
    }

    function clearChips() {
        var all = document.querySelectorAll('.fb-chips');
        for (var i = 0; i < all.length; i++) all[i].style.display = 'none';
    }

    // ==========================================
    // Input visibility
    // ==========================================
    function showInput() {
        q('fb-inp').style.display = 'flex';
        q('fb-txt').placeholder = 'Tapez votre message...';
    }
    function hideInput() {
        q('fb-inp').style.display = 'none';
    }

    // ==========================================
    // Typing indicator
    // ==========================================
    function showTyping() {
        if (q('fb-typ')) return;
        var c = q('fb-msgs'), d = document.createElement('div');
        d.id = 'fb-typ';
        d.style.cssText = 'margin:6px 0;padding:10px 14px;background:#fff;border-radius:16px;border-bottom-left-radius:4px;display:inline-flex;gap:5px;border:1px solid #e8e8e8;';
        d.innerHTML = '<span class="fb-dot"></span><span class="fb-dot"></span><span class="fb-dot"></span>';
        c.appendChild(d); c.scrollTop = c.scrollHeight;
    }
    function hideTyping() { var e = q('fb-typ'); if (e) e.remove(); }

    // ==========================================
    // AJAX
    // ==========================================
    function post(body, cb) {
        var x = new XMLHttpRequest();
        x.open('POST', config.apiUrl, true);
        x.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        x.timeout = 15000;
        x.onreadystatechange = function() {
            if (x.readyState === 4) {
                if (x.status === 200) { try { cb(JSON.parse(x.responseText)); } catch(e) { cb({error:'Erreur serveur'}); } }
                else { cb({error:'Connexion impossible'}); }
            }
        };
        x.ontimeout = function() { cb({error:'Delai depasse'}); };
        x.send(body);
    }

    // ==========================================
    // CSS
    // ==========================================
    function injectCSS() {
        var s = document.createElement('style');
        s.textContent =
            '@keyframes fbfade{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}' +
            '@keyframes fbdot{0%,60%,100%{transform:translateY(0)}30%{transform:translateY(-6px)}}' +
            '.fb-dot{width:6px;height:6px;background:#bbb;border-radius:50%;display:inline-block;animation:fbdot 1.2s infinite}' +
            '.fb-dot:nth-child(2){animation-delay:.2s}.fb-dot:nth-child(3){animation-delay:.4s}' +
            '#fb-txt:focus{border-color:' + config.color + '!important}' +
            '@media(max-width:480px){#fb-win{left:8px!important;right:8px!important;bottom:80px!important;width:auto!important;}#fb-bubble{display:none!important;}}';
        document.head.appendChild(s);
    }

    // ==========================================
    // Init
    // ==========================================
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() { injectCSS(); createWidget(); });
    } else { injectCSS(); createWidget(); }
})();
