/**
 * Chatbot ORCA - Version conversationnelle
 * Chips légers + ton humain + collecte step-by-step
 */
(function() {
    'use strict';

    var chatId = null, currentStep = 1;
    var apiUrl = (window.chatbotBaseUrl || '') + 'chatbot/api.php';

    // ==========================================
    // Widget HTML
    // ==========================================
    function createWidget() {
        var el = document.createElement('div');
        el.id = 'orca-cb';
        el.innerHTML =
            '<div id="cb-win" style="display:none;position:fixed;bottom:90px;right:20px;width:400px;background:#fff;border-radius:16px;box-shadow:0 10px 40px rgba(0,0,0,0.25);z-index:10000;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;overflow:hidden;flex-direction:column;">' +
                '<div style="background:linear-gradient(135deg,#1a5653,#0f3d3a);color:#fff;padding:14px 20px;display:flex;align-items:center;gap:12px;">' +
                    '<div style="width:36px;height:36px;background:rgba(255,255,255,.15);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:17px;">🏠</div>' +
                    '<div style="flex:1;"><div style="font-weight:700;font-size:15px;">Assistant ORCA</div><div style="font-size:11px;opacity:.7;">En ligne</div></div>' +
                    '<span id="cb-close" style="cursor:pointer;font-size:20px;opacity:.7;padding:4px 8px;">✕</span>' +
                '</div>' +
                '<div id="cb-msgs" style="overflow-y:auto;padding:16px;background:#f5f7f9;min-height:220px;max-height:400px;"></div>' +
                '<div id="cb-inp" style="padding:10px 14px;background:#fff;border-top:1px solid #eee;display:flex;gap:8px;">' +
                    '<input id="cb-txt" type="text" placeholder="Tapez votre message..." style="flex:1;padding:10px 14px;border:1px solid #ddd;border-radius:24px;font-size:13px;outline:none;" autocomplete="off">' +
                    '<button id="cb-go" style="padding:10px 16px;background:#1a5653;color:#fff;border:none;border-radius:24px;cursor:pointer;font-size:13px;font-weight:700;">↑</button>' +
                '</div>' +
            '</div>' +
            '<div id="cb-fab" style="position:fixed;bottom:20px;right:20px;width:62px;height:62px;background:linear-gradient(135deg,#1a5653,#0f3d3a);border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:0 4px 15px rgba(26,86,83,0.4);z-index:10000;transition:transform .2s;" onmouseover="this.style.transform=\'scale(1.1)\'" onmouseout="this.style.transform=\'scale(1)\'">' +
                '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>' +
            '</div>' +
            '<div id="cb-bubble" style="display:none;position:fixed;bottom:90px;right:20px;background:#fff;padding:12px 16px;border-radius:12px;box-shadow:0 4px 15px rgba(0,0,0,0.15);z-index:10000;max-width:260px;font-size:13px;cursor:pointer;animation:cbfade .4s ease;" onclick="document.getElementById(\'cb-bubble\').style.display=\'none\';toggleChat();">' +
                '<div style="font-weight:600;color:#1a5653;">🏠 Besoin d\'aide ?</div>' +
                '<div style="color:#555;margin-top:4px;">Je peux vous trouver la maison et le terrain idéal !</div>' +
                '<span onclick="event.stopPropagation();document.getElementById(\'cb-bubble\').style.display=\'none\';" style="position:absolute;top:6px;right:10px;cursor:pointer;color:#999;font-size:16px;">×</span>' +
            '</div>';
        document.body.appendChild(el);

        q('cb-fab').onclick = toggleChat;
        q('cb-close').onclick = toggleChat;
        q('cb-go').onclick = sendText;
        q('cb-txt').onkeypress = function(e) { if (e.key === 'Enter') sendText(); };

        // Bulle contextuelle
        setTimeout(function() {
            if (q('cb-win').style.display !== 'flex') {
                var path = window.location.pathname.toLowerCase();
                var t = q('cb-bubble').querySelector('div:first-child');
                var d = q('cb-bubble').querySelector('div:nth-child(2)');
                if (path.indexOf('modele') !== -1) { t.textContent = '🏠 Ce modèle vous plaît ?'; d.textContent = 'Je peux vous donner un prix personnalisé !'; }
                else if (path.indexOf('terrain') !== -1) { t.textContent = '🌿 Vous cherchez un terrain ?'; d.textContent = 'J\'ai des parcelles disponibles !'; }
                else if (path.indexOf('contact') !== -1) { t.textContent = '📞 Une question rapide ?'; d.textContent = 'Je réponds en 2 secondes !'; }
                q('cb-bubble').style.display = 'block';
            }
        }, 6000);
    }

    function q(id) { return document.getElementById(id); }

    // ==========================================
    // Toggle
    // ==========================================
    function toggleChat() {
        var win = q('cb-win');
        var open = win.style.display === 'flex';
        win.style.display = open ? 'none' : 'flex';
        q('cb-bubble').style.display = 'none';
        if (!open && !chatId) initChat();
        if (!open) window.dispatchEvent(new Event('chatbot-opened'));
    }
    window.cbToggleChat = toggleChat;

    // ==========================================
    // Init
    // ==========================================
    function initChat() {
        showTyping();
        post('action=init', function(d) {
            hideTyping();
            if (d.disabled) return;
            if (d.error) { addMsg(d.error, 'bot'); return; }
            chatId = d.conversation_id;
            currentStep = d.step || 1;

            if (d.config && d.config.auto_popup) {
                var delay = (d.config.popup_delay || 20) * 1000;
                setTimeout(function() { if (q('cb-win').style.display !== 'flex') toggleChat(); }, delay);
            }

            if (d.is_new) {
                addMsg(d.message, 'bot');
                if (d.options) showChips(d.options);
            } else if (d.history && d.history.length) {
                d.history.forEach(function(m) { addMsg(m.message, m.type); });
            }
        });
    }

    // ==========================================
    // Envoyer message
    // ==========================================
    function sendText() {
        var inp = q('cb-txt');
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

        post('action=message&conversation_id=' + chatId + '&message=' + encodeURIComponent(val), function(d) {
            hideTyping();
            if (d.error) { addMsg(d.error, 'bot'); return; }
            currentStep = d.step || currentStep;

            if (d.message) addMsg(d.message, 'bot');

            if (d.type === 'final') {
                hideInput();
                if (d.options) showChips(d.options);
            } else if (d.type === 'results_then_form' || d.type === 'form') {
                // Après résultats, montrer des réactions puis le formulaire sera step-by-step
                if (d.options) showChips(d.options);
                else if (d.type === 'results_then_form') {
                    showChips([
                        {label: '👍 Ça m\'intéresse', value: 'coord', next: 50},
                        {label: '🔄 Autres critères', value: 'autre', next: 40},
                        {label: '❓ J\'ai une question', value: 'go_question', next: 40}
                    ]);
                }
            } else if (d.options) {
                showChips(d.options);
            }

            // Focus sur l'input si c'est un champ texte attendu
            if (d.field) {
                var inp = q('cb-txt');
                var placeholders = {
                    'prenom': 'Votre prénom...',
                    'nom': 'Votre nom...',
                    'email': 'Votre email...',
                    'telephone': 'Votre téléphone (06 12 34 56 78)...'
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
        var c = q('cb-msgs');
        var d = document.createElement('div');
        var bot = (type === 'bot');

        d.style.cssText = 'margin:6px 0;padding:10px 14px;border-radius:16px;max-width:88%;font-size:13px;line-height:1.55;word-wrap:break-word;animation:cbfade .3s ease;' +
            (bot ? 'background:#fff;color:#333;margin-right:auto;border:1px solid #e8e8e8;border-bottom-left-radius:4px;box-shadow:0 1px 2px rgba(0,0,0,0.04);'
                 : 'background:#1a5653;color:#fff;margin-left:auto;border-bottom-right-radius:4px;max-width:75%;');

        var safe = text.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        d.innerHTML = safe.replace(/\*\*(.+?)\*\*/g,'<strong>$1</strong>').replace(/\n/g,'<br>').replace(/→/g,'<span style="color:#1a5653;">→</span>');

        c.appendChild(d);
        c.scrollTop = c.scrollHeight;
    }

    // ==========================================
    // Chips (suggestions légères inline)
    // ==========================================
    function showChips(opts) {
        if (!opts || !opts.length) return;
        var c = q('cb-msgs');
        var w = document.createElement('div');
        w.className = 'cb-chips';
        w.style.cssText = 'margin:8px 0;display:flex;flex-wrap:wrap;gap:6px;animation:cbfade .3s ease;';

        opts.forEach(function(o) {
            var b = document.createElement('button');
            b.textContent = o.label;
            b.style.cssText = 'padding:8px 14px;background:#fff;border:1.5px solid #1a5653;border-radius:20px;cursor:pointer;font-size:12px;color:#1a5653;font-weight:500;transition:all .15s;white-space:nowrap;';
            b.onmouseenter = function() { this.style.background='#1a5653'; this.style.color='#fff'; };
            b.onmouseleave = function() { this.style.background='#fff'; this.style.color='#1a5653'; };
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
        var all = document.querySelectorAll('.cb-chips');
        for (var i = 0; i < all.length; i++) all[i].style.display = 'none';
    }

    // ==========================================
    // Input visibility
    // ==========================================
    function showInput() {
        q('cb-inp').style.display = 'flex';
        q('cb-txt').placeholder = 'Tapez votre message...';
    }
    function hideInput() {
        q('cb-inp').style.display = 'none';
    }

    // ==========================================
    // Typing
    // ==========================================
    function showTyping() {
        if (q('cb-typ')) return;
        var c = q('cb-msgs'), d = document.createElement('div');
        d.id = 'cb-typ';
        d.style.cssText = 'margin:6px 0;padding:10px 14px;background:#fff;border-radius:16px;border-bottom-left-radius:4px;display:inline-flex;gap:5px;border:1px solid #e8e8e8;';
        d.innerHTML = '<span class="cb-dot"></span><span class="cb-dot"></span><span class="cb-dot"></span>';
        c.appendChild(d); c.scrollTop = c.scrollHeight;
    }
    function hideTyping() { var e = q('cb-typ'); if (e) e.remove(); }

    // ==========================================
    // AJAX
    // ==========================================
    function post(body, cb) {
        var x = new XMLHttpRequest();
        x.open('POST', apiUrl, true);
        x.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        x.timeout = 15000;
        x.onreadystatechange = function() {
            if (x.readyState === 4) {
                if (x.status === 200) { try { cb(JSON.parse(x.responseText)); } catch(e) { cb({error:'Erreur serveur'}); } }
                else { cb({error:'Connexion impossible'}); }
            }
        };
        x.ontimeout = function() { cb({error:'Délai dépassé'}); };
        x.send(body);
    }

    // ==========================================
    // CSS
    // ==========================================
    function injectCSS() {
        var s = document.createElement('style');
        s.textContent =
            '@keyframes cbfade{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}' +
            '@keyframes cbdot{0%,60%,100%{transform:translateY(0)}30%{transform:translateY(-6px)}}' +
            '.cb-dot{width:6px;height:6px;background:#bbb;border-radius:50%;display:inline-block;animation:cbdot 1.2s infinite}' +
            '.cb-dot:nth-child(2){animation-delay:.2s}.cb-dot:nth-child(3){animation-delay:.4s}' +
            '#cb-txt:focus{border-color:#1a5653!important}' +
            '@media(max-width:480px){#cb-win{left:8px!important;right:8px!important;bottom:80px!important;width:auto!important;}#cb-bubble{display:none!important;}}';
        document.head.appendChild(s);
    }

    // ==========================================
    // Init
    // ==========================================
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() { injectCSS(); createWidget(); });
    } else { injectCSS(); createWidget(); }
})();
