<?php
/**
 * FrenchyBot - Landing page commerciale
 */
$page = $_GET['p'] ?? 'home';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FrenchyBot — Chatbot intelligent pour votre site</title>
    <meta name="description" content="Installez un chatbot conversationnel sur votre site en 5 minutes. Generez des leads, qualifiez vos prospects, connectez votre base de donnees.">

    <!-- Open Graph -->
    <meta property="og:title" content="FrenchyBot — Chatbot intelligent pour votre site">
    <meta property="og:description" content="Generez plus de leads avec un chatbot intelligent connecte a votre base de donnees.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://bot.frenchycompany.fr">

    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; color:#1f2937; line-height:1.6; }
        a { color:#1a5653; text-decoration:none; }

        /* Hero */
        .hero {
            background:linear-gradient(135deg,#1a5653 0%,#0f3d3a 50%,#0a2b29 100%);
            color:#fff;
            padding:80px 24px 100px;
            text-align:center;
            position:relative;
            overflow:hidden;
        }
        .hero::after {
            content:'';
            position:absolute;
            bottom:-2px;
            left:0;
            right:0;
            height:80px;
            background:#fff;
            clip-path:ellipse(60% 100% at 50% 100%);
        }
        .hero h1 { font-size:48px; font-weight:800; margin-bottom:16px; letter-spacing:-1px; }
        .hero h1 span { color:#4ade80; }
        .hero p { font-size:20px; opacity:.85; max-width:600px; margin:0 auto 32px; }
        .hero-cta { display:inline-flex; gap:12px; flex-wrap:wrap; justify-content:center; }
        .btn-hero {
            padding:16px 36px;
            border-radius:12px;
            font-size:17px;
            font-weight:700;
            cursor:pointer;
            border:none;
            transition:all .2s;
            text-decoration:none;
            display:inline-block;
        }
        .btn-primary-hero { background:#fff; color:#1a5653; }
        .btn-primary-hero:hover { transform:translateY(-2px); box-shadow:0 8px 25px rgba(0,0,0,0.2); }
        .btn-outline-hero { background:transparent; color:#fff; border:2px solid rgba(255,255,255,.4); }
        .btn-outline-hero:hover { border-color:#fff; background:rgba(255,255,255,.1); }

        .container { max-width:1100px; margin:0 auto; padding:0 24px; }

        /* Stats bar */
        .stats-bar {
            display:flex;
            justify-content:center;
            gap:60px;
            padding:40px 24px;
            flex-wrap:wrap;
        }
        .stat-item { text-align:center; }
        .stat-item .number { font-size:36px; font-weight:800; color:#1a5653; }
        .stat-item .label { font-size:14px; color:#6b7280; }

        /* Section */
        .section { padding:80px 24px; }
        .section-gray { background:#f9fafb; }
        .section-title { font-size:36px; font-weight:800; text-align:center; margin-bottom:12px; letter-spacing:-0.5px; }
        .section-subtitle { text-align:center; color:#6b7280; font-size:18px; margin-bottom:48px; max-width:600px; margin-left:auto; margin-right:auto; }

        /* Features grid */
        .features-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:32px; }
        .feature-card {
            background:#fff;
            border-radius:16px;
            padding:32px;
            box-shadow:0 1px 3px rgba(0,0,0,0.06);
            border:1px solid #f0f0f0;
            transition:all .2s;
        }
        .feature-card:hover { transform:translateY(-4px); box-shadow:0 12px 30px rgba(0,0,0,0.08); }
        .feature-icon { font-size:32px; margin-bottom:16px; }
        .feature-card h3 { font-size:18px; font-weight:700; margin-bottom:8px; }
        .feature-card p { color:#6b7280; font-size:14px; line-height:1.7; }

        /* How it works */
        .steps { display:grid; grid-template-columns:repeat(3,1fr); gap:40px; counter-reset:step; }
        .step { text-align:center; position:relative; }
        .step::before {
            counter-increment:step;
            content:counter(step);
            display:flex;
            width:48px; height:48px;
            background:linear-gradient(135deg,#1a5653,#0f3d3a);
            color:#fff;
            border-radius:50%;
            align-items:center;
            justify-content:center;
            font-size:20px;
            font-weight:700;
            margin:0 auto 16px;
        }
        .step h3 { font-size:18px; margin-bottom:8px; }
        .step p { color:#6b7280; font-size:14px; }

        /* Pricing */
        .pricing-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:24px; }
        .price-card {
            background:#fff;
            border-radius:16px;
            padding:36px;
            box-shadow:0 1px 3px rgba(0,0,0,0.06);
            border:2px solid #f0f0f0;
            text-align:center;
            transition:all .2s;
        }
        .price-card.popular { border-color:#1a5653; position:relative; }
        .price-card.popular::before {
            content:'Populaire';
            position:absolute;
            top:-12px;
            left:50%;
            transform:translateX(-50%);
            background:#1a5653;
            color:#fff;
            padding:4px 16px;
            border-radius:20px;
            font-size:12px;
            font-weight:600;
        }
        .price-card:hover { transform:translateY(-4px); box-shadow:0 12px 30px rgba(0,0,0,0.08); }
        .price-name { font-size:20px; font-weight:700; margin-bottom:8px; }
        .price-amount { font-size:42px; font-weight:800; color:#1a5653; margin:16px 0 4px; }
        .price-amount span { font-size:16px; font-weight:400; color:#6b7280; }
        .price-desc { color:#6b7280; font-size:14px; margin-bottom:24px; }
        .price-features { list-style:none; text-align:left; margin-bottom:28px; }
        .price-features li { padding:8px 0; font-size:14px; border-bottom:1px solid #f5f5f5; }
        .price-features li::before { content:'✓ '; color:#10b981; font-weight:700; }
        .btn-price {
            display:block;
            padding:14px;
            border-radius:10px;
            font-size:15px;
            font-weight:700;
            text-align:center;
            cursor:pointer;
            border:none;
            transition:all .15s;
            text-decoration:none;
        }
        .btn-price-primary { background:#1a5653; color:#fff; }
        .btn-price-primary:hover { background:#0f3d3a; }
        .btn-price-outline { background:#fff; color:#1a5653; border:2px solid #1a5653; }
        .btn-price-outline:hover { background:#e8f0ef; }

        /* Testimonial */
        .testimonial {
            background:linear-gradient(135deg,#1a5653,#0f3d3a);
            color:#fff;
            border-radius:20px;
            padding:48px;
            text-align:center;
            max-width:800px;
            margin:0 auto;
        }
        .testimonial blockquote { font-size:20px; font-style:italic; opacity:.9; margin-bottom:16px; line-height:1.7; }
        .testimonial cite { font-size:14px; opacity:.7; }

        /* CTA */
        .cta-section {
            background:linear-gradient(135deg,#1a5653,#0f3d3a);
            color:#fff;
            padding:80px 24px;
            text-align:center;
        }
        .cta-section h2 { font-size:36px; font-weight:800; margin-bottom:16px; }
        .cta-section p { font-size:18px; opacity:.8; margin-bottom:32px; max-width:500px; margin-left:auto; margin-right:auto; }

        /* Contact form */
        .contact-form {
            background:#fff;
            border-radius:16px;
            padding:40px;
            max-width:500px;
            margin:0 auto;
            box-shadow:0 20px 40px rgba(0,0,0,0.1);
        }
        .contact-form h3 { font-size:22px; font-weight:700; margin-bottom:20px; text-align:center; color:#1f2937; }
        .form-field { margin-bottom:16px; }
        .form-field label { display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#374151; }
        .form-field input, .form-field select, .form-field textarea {
            width:100%; padding:12px 16px;
            border:1.5px solid #e5e7eb;
            border-radius:10px;
            font-size:15px;
            font-family:inherit;
            outline:none;
            transition:border-color .15s;
        }
        .form-field input:focus, .form-field select:focus, .form-field textarea:focus { border-color:#1a5653; }
        .btn-submit {
            width:100%;
            padding:14px;
            background:#1a5653;
            color:#fff;
            border:none;
            border-radius:10px;
            font-size:16px;
            font-weight:700;
            cursor:pointer;
            transition:background .15s;
        }
        .btn-submit:hover { background:#0f3d3a; }

        /* Footer */
        footer {
            background:#111827;
            color:rgba(255,255,255,.6);
            padding:40px 24px;
            text-align:center;
            font-size:14px;
        }
        footer a { color:rgba(255,255,255,.8); }

        /* Demo floating */
        .demo-badge {
            position:fixed;
            bottom:90px;
            left:20px;
            background:#fff;
            padding:10px 16px;
            border-radius:10px;
            box-shadow:0 4px 15px rgba(0,0,0,0.1);
            font-size:13px;
            z-index:9999;
            display:flex;
            align-items:center;
            gap:8px;
        }
        .demo-badge .dot { width:8px; height:8px; background:#10b981; border-radius:50%; animation:pulse 2s infinite; }
        @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.4} }

        @media (max-width:768px) {
            .hero h1 { font-size:32px; }
            .hero p { font-size:16px; }
            .features-grid, .steps, .pricing-grid { grid-template-columns:1fr; }
            .stats-bar { gap:30px; }
            .stat-item .number { font-size:28px; }
            .demo-badge { display:none; }
        }
    </style>
</head>
<body>

    <!-- Hero -->
    <div class="hero">
        <div class="container">
            <h1>Votre chatbot intelligent<br>en <span>5 minutes</span></h1>
            <p>Generez plus de leads, qualifiez vos prospects et connectez votre base de donnees. Sans coder.</p>
            <div class="hero-cta">
                <a href="#contact" class="btn-hero btn-primary-hero">Demander une demo</a>
                <a href="#demo" class="btn-hero btn-outline-hero">Voir en action</a>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-bar">
        <div class="stat-item">
            <div class="number">+40%</div>
            <div class="label">de leads en plus</div>
        </div>
        <div class="stat-item">
            <div class="number">24/7</div>
            <div class="label">disponible</div>
        </div>
        <div class="stat-item">
            <div class="number">2 min</div>
            <div class="label">pour qualifier un prospect</div>
        </div>
        <div class="stat-item">
            <div class="number">1 ligne</div>
            <div class="label">de code a integrer</div>
        </div>
    </div>

    <!-- Features -->
    <div class="section section-gray">
        <div class="container">
            <h2 class="section-title">Tout ce qu'il faut pour convertir</h2>
            <p class="section-subtitle">Un chatbot qui comprend vos clients, cherche dans votre catalogue et genere des leads qualifies.</p>

            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">🎯</div>
                    <h3>Detection intelligente</h3>
                    <p>Le chatbot comprend les intentions de vos visiteurs grace a un moteur de scoring avance. Il tolere les fautes de frappe et comprend les phrases complexes.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🔍</div>
                    <h3>Recherche dans votre BDD</h3>
                    <p>Connectez votre base de donnees produits. Le chatbot cherche en temps reel et presente les resultats pertinents a vos visiteurs.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📊</div>
                    <h3>Leads qualifies</h3>
                    <p>Collecte conversationnelle des coordonnees. Le visiteur donne son nom, email et telephone naturellement, sans formulaire froid.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🎨</div>
                    <h3>100% personnalisable</h3>
                    <p>Couleurs, messages, scenarios, intentions : tout est configurable depuis l'interface d'administration. Votre marque, votre ton.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🧠</div>
                    <h3>Il apprend</h3>
                    <p>Centre d'apprentissage integre : corrigez les mauvaises detections en un clic, le chatbot s'ameliore en continu.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📱</div>
                    <h3>Multi-canal</h3>
                    <p>Widget flottant, iframe integre, page plein ecran partageable sur Facebook. Popup exit intent et bandeau CTA inclus.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- How it works -->
    <div class="section">
        <div class="container">
            <h2 class="section-title">Comment ca marche</h2>
            <p class="section-subtitle">Operationnel en 3 etapes, sans competences techniques.</p>

            <div class="steps">
                <div class="step">
                    <h3>On configure</h3>
                    <p>On cree votre chatbot, on importe vos produits, on personnalise les messages et les couleurs a votre image.</p>
                </div>
                <div class="step">
                    <h3>Vous collez 1 ligne</h3>
                    <p>Un simple copier-coller d'une ligne de code dans votre site. Compatible WordPress, Wix, Shopify, ou n'importe quel site.</p>
                </div>
                <div class="step">
                    <h3>Les leads arrivent</h3>
                    <p>Le chatbot accueille vos visiteurs, repond a leurs questions, et vous envoie les leads qualifies par email.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Demo -->
    <div class="section section-gray" id="demo">
        <div class="container">
            <h2 class="section-title">Testez en direct</h2>
            <p class="section-subtitle">Ce chatbot est connecte a une vraie base de donnees de terrains. Essayez !</p>
            <div style="max-width:500px;margin:0 auto;border-radius:16px;overflow:hidden;box-shadow:0 10px 40px rgba(0,0,0,0.15);">
                <iframe src="https://bot.frenchycompany.fr/api/v1/iframe.php?token=83059f1ffd4adf64a5ef5e9a803dd1d2" width="100%" height="550" style="border:none;"></iframe>
            </div>
        </div>
    </div>

    <!-- Pricing -->
    <div class="section" id="pricing">
        <div class="container">
            <h2 class="section-title">Tarifs simples et transparents</h2>
            <p class="section-subtitle">Pas de surprise. Pas d'engagement. Annulez quand vous voulez.</p>

            <div class="pricing-grid">
                <div class="price-card">
                    <div class="price-name">Starter</div>
                    <div class="price-amount">49€<span> HT/mois</span></div>
                    <div class="price-desc">Pour les independants et petites entreprises</div>
                    <ul class="price-features">
                        <li>1 chatbot</li>
                        <li>Scenarios conversationnels</li>
                        <li>Collecte de leads illimitee</li>
                        <li>Notifications email</li>
                        <li>Widget + popup + bandeau</li>
                        <li>Tableau de bord</li>
                    </ul>
                    <a href="#contact" class="btn-price btn-price-outline">Commencer</a>
                </div>

                <div class="price-card popular">
                    <div class="price-name">Pro</div>
                    <div class="price-amount">149€<span> HT/mois</span></div>
                    <div class="price-desc">Pour les PME et professionnels</div>
                    <ul class="price-features">
                        <li>1 chatbot avance</li>
                        <li>Connexion base de donnees</li>
                        <li>Recherche produits en direct</li>
                        <li>Import Excel</li>
                        <li>A/B testing</li>
                        <li>Centre d'apprentissage</li>
                        <li>Acces client a l'admin</li>
                    </ul>
                    <a href="#contact" class="btn-price btn-price-primary">Choisir Pro</a>
                </div>

                <div class="price-card">
                    <div class="price-name">Business</div>
                    <div class="price-amount">299€<span> HT/mois</span></div>
                    <div class="price-desc">Pour les entreprises exigeantes</div>
                    <ul class="price-features">
                        <li>Multi-chatbots illimites</li>
                        <li>Tout le plan Pro</li>
                        <li>Intelligence artificielle (GPT/Claude)</li>
                        <li>Webhooks (n8n, Zapier)</li>
                        <li>Relances automatiques</li>
                        <li>Support prioritaire</li>
                        <li>Configuration sur mesure</li>
                    </ul>
                    <a href="#contact" class="btn-price btn-price-outline">Nous contacter</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Testimonial -->
    <div class="section section-gray">
        <div class="container">
            <div class="testimonial">
                <blockquote>"Le chatbot genere en moyenne 3 leads qualifies par jour sur notre site. C'est comme avoir un commercial disponible 24h/24."</blockquote>
                <cite>— Constructeur de maisons individuelles, Oise</cite>
            </div>
        </div>
    </div>

    <!-- CTA / Contact -->
    <div class="cta-section" id="contact">
        <div class="container">
            <h2>Pret a convertir plus de visiteurs ?</h2>
            <p>Demandez une demo gratuite. On configure tout pour vous.</p>

            <div class="contact-form">
                <h3>Demander une demo</h3>
                <form action="https://bot.frenchycompany.fr/api/v1/chat.php" method="post" id="contactForm" onsubmit="return handleSubmit(event)">
                    <div class="form-field">
                        <label>Votre nom</label>
                        <input type="text" name="nom" required placeholder="Jean Dupont">
                    </div>
                    <div class="form-field">
                        <label>Email professionnel</label>
                        <input type="email" name="email" required placeholder="jean@monentreprise.fr">
                    </div>
                    <div class="form-field">
                        <label>Telephone</label>
                        <input type="tel" name="telephone" placeholder="06 12 34 56 78">
                    </div>
                    <div class="form-field">
                        <label>Votre secteur</label>
                        <select name="secteur">
                            <option value="">Choisir...</option>
                            <option>Immobilier / Construction</option>
                            <option>Automobile</option>
                            <option>E-commerce</option>
                            <option>Services / BTP</option>
                            <option>Sante</option>
                            <option>Autre</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label>Votre site web</label>
                        <input type="url" name="site" placeholder="https://monsite.fr">
                    </div>
                    <button type="submit" class="btn-submit">Demander ma demo gratuite</button>
                    <p style="text-align:center;font-size:12px;color:#999;margin-top:12px;">Reponse sous 24h. Aucun engagement.</p>
                </form>
                <div id="formSuccess" style="display:none;text-align:center;padding:40px;">
                    <div style="font-size:48px;margin-bottom:16px;">✅</div>
                    <h3 style="color:#1f2937;">Merci !</h3>
                    <p style="color:#6b7280;">Nous vous recontactons sous 24h pour planifier votre demo.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <p>FrenchyBot — Un produit <a href="https://frenchycompany.fr">FrenchyCompany</a></p>
        <p style="margin-top:8px;">contact@frenchycompany.fr</p>
    </footer>

    <!-- Demo badge -->
    <div class="demo-badge">
        <span class="dot"></span>
        <span>Le chatbot en bas a droite est une demo live</span>
    </div>

    <script>
    function handleSubmit(e) {
        e.preventDefault();
        var form = document.getElementById('contactForm');
        var data = new FormData(form);

        // Envoyer par email (via fetch a un endpoint simple)
        fetch('https://bot.frenchycompany.fr/api/v1/contact.php', {
            method: 'POST',
            body: data
        }).then(function() {
            form.style.display = 'none';
            document.getElementById('formSuccess').style.display = 'block';
        }).catch(function() {
            form.style.display = 'none';
            document.getElementById('formSuccess').style.display = 'block';
        });

        return false;
    }
    </script>

    <!-- FrenchyBot demo -->
    <script src="https://bot.frenchycompany.fr/api/v1/embed.js.php?token=83059f1ffd4adf64a5ef5e9a803dd1d2"></script>

</body>
</html>
