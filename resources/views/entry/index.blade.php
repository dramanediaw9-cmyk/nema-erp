<!doctype html>
<html lang="fr">
<head>
    @include('partials.security-csp-meta')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nema Technologies - ERP pour entreprises</title>
    <style>
        :root {
            --ink: #10201f;
            --muted: #63706d;
            --line: #dce6e2;
            --soft: #f4f8f6;
            --panel: #ffffff;
            --brand: #0f766e;
            --brand-dark: #0a4f49;
            --gold: #e9ad35;
            --red: #b42318;
            --white: #fff;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: var(--ink);
            background: var(--white);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        a { color: inherit; }

        .nav {
            width: min(1180px, calc(100% - 32px));
            min-height: 64px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
        }
        .brand { display: inline-flex; align-items: center; gap: 10px; text-decoration: none; font-weight: 900; }
        .mark {
            width: 36px;
            height: 36px;
            display: grid;
            place-items: center;
            color: var(--white);
            background: var(--brand);
            border-radius: 8px;
            font-weight: 950;
        }
        .nav-links { display: flex; gap: 24px; color: #3f4a47; font-size: 14px; font-weight: 800; }
        .nav-links a, .nav-actions a { text-decoration: none; }
        .nav-actions { display: flex; align-items: center; gap: 12px; font-size: 14px; font-weight: 850; }
        .start { padding: 10px 14px; color: var(--white); background: var(--brand); border-radius: 7px; }

        .hero {
            position: relative;
            background-image:
                linear-gradient(90deg, rgba(248, 251, 250, .98) 0%, rgba(248, 251, 250, .93) 38%, rgba(248, 251, 250, .46) 66%, rgba(248, 251, 250, .12) 100%),
                url('{{ asset('marketing/nema-command-center.png') }}');
            background-size: cover;
            background-position: center;
            border-block: 1px solid var(--line);
        }
        .hero-inner {
            width: min(1180px, calc(100% - 32px));
            min-height: calc(100vh - 64px);
            margin: 0 auto;
            padding: clamp(32px, 5vh, 58px) 0 34px;
            display: flex;
            align-items: center;
        }
        .hero-copy { width: min(680px, 100%); }
        .edition-pill {
            width: max-content;
            margin-bottom: 14px;
            padding: 8px 11px;
            color: #173d39;
            background: rgba(255, 255, 255, .88);
            border: 1px solid rgba(15, 118, 110, .18);
            border-radius: 999px;
            box-shadow: 0 16px 34px rgba(16, 32, 31, .08);
            font-size: 12px;
            font-weight: 950;
        }
        .eyebrow {
            color: var(--brand-dark);
            font-size: 13px;
            font-weight: 950;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        h1 {
            max-width: 690px;
            margin: 12px 0 0;
            font-size: clamp(42px, 6vw, 76px);
            line-height: 1.01;
            letter-spacing: 0;
        }
        .accent {
            text-decoration: underline;
            text-decoration-color: var(--gold);
            text-decoration-thickness: .14em;
            text-underline-offset: .09em;
        }
        .lead {
            max-width: 650px;
            margin: 18px 0 0;
            color: var(--muted);
            font-size: 18px;
            line-height: 1.55;
        }
        .actions {
            margin-top: 24px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .button {
            min-height: 46px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 11px 18px;
            border: 1px solid transparent;
            border-radius: 7px;
            font-weight: 900;
            text-decoration: none;
        }
        .button-primary { color: var(--white); background: var(--brand); }
        .button-secondary { color: var(--brand-dark); background: var(--white); border-color: var(--line); }
        .trust-row {
            margin-top: 22px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .trust-pill {
            padding: 8px 10px;
            color: var(--brand-dark);
            background: #e6f3f0;
            border: 1px solid #c3dfda;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 900;
        }
        .hero-proof {
            max-width: 640px;
            margin-top: 24px;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
        }
        .proof-card {
            min-height: 86px;
            padding: 13px 14px;
            border: 1px solid rgba(15, 118, 110, .18);
            border-radius: 9px;
            background: rgba(255, 255, 255, .88);
            box-shadow: 0 18px 38px rgba(16, 32, 31, .08);
        }
        .proof-card strong {
            display: block;
            color: var(--brand-dark);
            font-size: 24px;
            line-height: 1;
        }
        .proof-card span {
            display: block;
            margin-top: 8px;
            color: var(--muted);
            font-size: 12px;
            font-weight: 800;
            line-height: 1.35;
        }

        .erp-preview {
            border: 1px solid #cfded9;
            border-radius: 10px;
            background: var(--panel);
            box-shadow: 0 24px 60px rgba(15, 76, 70, .12);
            overflow: hidden;
        }
        .preview-top {
            min-height: 54px;
            padding: 12px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            background: #173d39;
            color: var(--white);
        }
        .preview-top strong { display: block; font-size: 15px; }
        .preview-top span { color: #bdd8d4; font-size: 12px; }
        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #40d39b;
            box-shadow: 0 0 0 4px rgba(64, 211, 155, .18);
        }
        .preview-body { padding: 14px; display: grid; gap: 12px; }
        .setup-strip {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 8px;
        }
        .setup-step {
            min-height: 76px;
            padding: 10px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fbfdfc;
        }
        .setup-step small { color: var(--brand-dark); font-weight: 950; }
        .setup-step strong { display: block; margin-top: 7px; font-size: 13px; line-height: 1.25; }
        .module-row { display: flex; gap: 7px; overflow: hidden; }
        .module-chip {
            flex: none;
            padding: 8px 10px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            color: #4d5956;
            font-size: 12px;
            font-weight: 900;
        }
        .preview-grid {
            display: grid;
            grid-template-columns: .9fr 1.1fr;
            gap: 12px;
        }
        .mini-panel {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            overflow: hidden;
        }
        .mini-panel-head {
            padding: 10px 12px;
            display: flex;
            justify-content: space-between;
            gap: 10px;
            border-bottom: 1px solid var(--line);
            color: #44504d;
            font-size: 12px;
            font-weight: 950;
            text-transform: uppercase;
        }
        .metric-list { display: grid; }
        .metric {
            padding: 11px 12px;
            display: flex;
            justify-content: space-between;
            gap: 12px;
            border-bottom: 1px solid #edf2f0;
            font-size: 13px;
        }
        .metric:last-child { border-bottom: 0; }
        .metric strong { font-size: 16px; }
        .ok { color: var(--brand); }
        .danger { color: var(--red); }
        .work-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .work-table th, .work-table td { padding: 10px 11px; border-bottom: 1px solid #edf2f0; text-align: left; }
        .work-table th { color: #6a7773; background: #f6faf8; font-size: 11px; text-transform: uppercase; }
        .work-table tr:last-child td { border-bottom: 0; }

        .foundation-section { padding: 40px 18px 54px; background: var(--white); }
        .product-section { padding: 44px 18px; background: #f7fbf9; border-top: 1px solid var(--line); }
        .section-inner { width: min(1120px, 100%); margin: 0 auto; }
        .section-head { margin-bottom: 18px; display: flex; align-items: end; justify-content: space-between; gap: 18px; }
        h2 { margin: 0 0 7px; font-size: clamp(28px, 4vw, 40px); letter-spacing: 0; }
        .section-head p { margin: 0; color: var(--muted); }
        .badge {
            padding: 8px 11px;
            color: var(--brand-dark);
            background: #e4f4f1;
            border: 1px solid #b7dcd6;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 900;
            white-space: nowrap;
        }
        .grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
        .foundation-flow {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            position: relative;
        }
        .card {
            min-height: 192px;
            padding: 16px;
            display: flex;
            flex-direction: column;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
        }
        .number { color: #8a5b08; font-size: 13px; font-weight: 950; }
        .card h3 { margin: 12px 0 8px; font-size: 19px; letter-spacing: 0; }
        .card p { margin: 0; color: var(--muted); font-size: 14px; line-height: 1.5; }
        .result { margin-top: auto; padding-top: 12px; color: var(--brand-dark); border-top: 1px solid var(--line); font-size: 14px; font-weight: 850; line-height: 1.4; }
        .foundation-card {
            min-height: 420px;
            padding: 18px;
            display: flex;
            flex-direction: column;
            border: 1px solid var(--line);
            border-radius: 10px;
            background: #fff;
            box-shadow: 0 18px 46px rgba(16, 32, 31, .06);
        }
        .foundation-card__top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 13px;
        }
        .foundation-number {
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            border-radius: 9px;
            background: #f8efd9;
            color: #83560d;
            font-weight: 950;
        }
        .foundation-state {
            color: var(--brand-dark);
            background: #e8f6f1;
            border: 1px solid #cbe7df;
            border-radius: 999px;
            padding: 6px 9px;
            font-size: 11px;
            font-weight: 950;
        }
        .foundation-card h3 { margin: 0 0 8px; font-size: 20px; letter-spacing: 0; }
        .foundation-card p { margin: 0; color: var(--muted); font-size: 14px; line-height: 1.5; }
        .foundation-block {
            margin-top: 15px;
            padding-top: 13px;
            border-top: 1px solid #edf2f0;
        }
        .foundation-block strong {
            display: block;
            margin-bottom: 8px;
            color: #40504d;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .06em;
        }
        .foundation-list {
            margin: 0;
            padding: 0;
            display: grid;
            gap: 7px;
            list-style: none;
        }
        .foundation-list li {
            position: relative;
            padding-left: 16px;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.35;
        }
        .foundation-list li::before {
            content: '';
            position: absolute;
            left: 0;
            top: .55em;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--brand);
        }
        .foundation-check {
            margin-top: 14px;
            padding: 12px;
            border-radius: 8px;
            background: #f6faf8;
            color: #344541;
            font-size: 13px;
            line-height: 1.45;
        }
        .foundation-result {
            margin-top: auto;
            padding-top: 14px;
            color: var(--brand-dark);
            border-top: 1px solid var(--line);
            font-size: 14px;
            font-weight: 900;
            line-height: 1.4;
        }
        .foundation-timeline {
            margin: 18px 0 22px;
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 8px;
        }
        .timeline-step {
            min-height: 52px;
            padding: 9px 10px;
            border: 1px solid #cbe7df;
            border-radius: 8px;
            background: #f6fbf9;
            color: var(--brand-dark);
            font-size: 12px;
            font-weight: 900;
        }
        .timeline-step span {
            display: block;
            margin-bottom: 3px;
            color: #83560d;
            font-size: 11px;
        }
        .final-action {
            margin-top: 22px;
            padding: 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            background: var(--soft);
            border: 1px solid var(--line);
            border-radius: 8px;
        }
        .final-action strong { display: block; margin-bottom: 4px; }
        .final-action span { color: var(--muted); font-size: 14px; }
        .industry-card {
            min-height: 150px;
            padding: 17px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
        }
        .industry-card strong { display: block; font-size: 18px; }
        .industry-card p { margin: 8px 0 0; color: var(--muted); line-height: 1.5; }
        .module-board {
            border: 1px solid var(--line);
            border-radius: 10px;
            overflow: hidden;
            background: #fff;
        }
        .module-line {
            display: grid;
            grid-template-columns: minmax(160px, .8fr) 110px minmax(0, 1.4fr);
            gap: 12px;
            align-items: center;
            padding: 14px 16px;
            border-bottom: 1px solid #edf2f0;
        }
        .module-line:last-child { border-bottom: 0; }
        .module-line strong { font-size: 15px; }
        .module-line span { color: var(--muted); line-height: 1.45; }
        .status {
            justify-self: start;
            padding: 6px 9px;
            border-radius: 999px;
            background: #e8f6f1;
            color: var(--brand-dark);
            font-size: 12px;
            font-style: normal;
            font-weight: 950;
        }
        .assurance-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-top: 18px;
        }
        .assurance {
            padding: 15px;
            border: 1px solid #cfe1dc;
            border-radius: 8px;
            background: #fafffd;
        }
        .assurance strong { display: block; margin-bottom: 7px; }
        .assurance span { color: var(--muted); font-size: 14px; line-height: 1.45; }
        .site-footer {
            padding: 24px 18px;
            background: #173d39;
            color: #d9ebe8;
        }
        .footer-inner {
            width: min(1120px, 100%);
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            font-size: 14px;
        }
        .footer-inner a { color: #fff; font-weight: 850; text-decoration: none; }

        @media (max-width: 980px) {
            .hero {
                background-image:
                    linear-gradient(180deg, rgba(248, 251, 250, .98) 0%, rgba(248, 251, 250, .95) 55%, rgba(248, 251, 250, .7) 100%),
                    url('{{ asset('marketing/nema-command-center.png') }}');
                background-position: 60% top;
            }
            .hero-inner { min-height: auto; }
            .erp-preview { order: -1; }
            .nav-links { display: none; }
            .grid, .assurance-grid, .foundation-flow, .foundation-timeline { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media (max-width: 640px) {
            .nav { flex-wrap: wrap; padding: 12px 0; }
            .nav-actions { width: 100%; justify-content: space-between; }
            h1 { font-size: clamp(36px, 12vw, 48px); }
            .lead { font-size: 16px; }
            .button { width: 100%; }
            .setup-strip, .preview-grid, .grid, .assurance-grid, .hero-proof, .foundation-flow, .foundation-timeline { grid-template-columns: 1fr; }
            .module-line { grid-template-columns: 1fr; }
            .section-head, .final-action { align-items: stretch; flex-direction: column; }
            .card { min-height: auto; }
        }
    </style>
</head>
<body>
    <header class="nav">
        <a class="brand" href="{{ route('entry.index') }}" aria-label="Nema Technologies">
            <span class="mark">N</span>
            <span>Nema Technologies</span>
        </a>

        <nav class="nav-links" aria-label="Navigation principale">
            <a href="#fondations">Démarrage</a>
            <a href="#secteurs">Métiers</a>
            <a href="#modules">Modules</a>
            <a href="{{ route('login') }}">Connexion</a>
        </nav>

        <div class="nav-actions">
            <a href="{{ route('login') }}">Se connecter</a>
            <a class="start" href="{{ route('saas.register.account') }}">Démarrer</a>
        </div>
    </header>

    <main>
        <section class="hero">
            <div class="hero-inner">
                <div class="hero-copy">
                    <div class="edition-pill">Nema ERP Business Suite</div>
                    <div class="eyebrow">ERP, CRM, caisse et stock pour PME</div>
                    <h1>Votre entreprise prête <span class="accent">avant la première vente.</span></h1>
                    <p class="lead">Nema crée d’abord le compte, l’entreprise, l’agence, la formule et l’espace métier. Ensuite seulement l’équipe entre dans un ERP séparé, prêt pour la caisse, le stock, les achats et les rapports.</p>
                    <div class="actions">
                        <a class="button button-primary" href="{{ route('saas.register.account') }}">Créer mon espace Nema</a>
                        <a class="button button-secondary" href="{{ route('login') }}">J’ai déjà un espace</a>
                    </div>
                    <div class="trust-row" aria-label="Points clés">
                        <span class="trust-pill">Base séparée par entreprise</span>
                        <span class="trust-pill">Caisse + stock dès le départ</span>
                        <span class="trust-pill">Essai 14 jours</span>
                    </div>
                    <div class="hero-proof">
                        <div class="proof-card"><strong>4</strong><span>étapes avant connexion</span></div>
                        <div class="proof-card"><strong>126</strong><span>tables contrôlées en sauvegarde</span></div>
                        <div class="proof-card"><strong>0</strong><span>alerte santé sur les espaces actifs</span></div>
                    </div>
                </div>
            </div>
        </section>

        <section id="secteurs" class="product-section">
            <div class="section-inner">
                <div class="section-head">
                    <div>
                        <h2>Des métiers différents, un même noyau solide</h2>
                        <p>Nema adapte le démarrage aux boutiques, services, chantiers, restaurants, écoles et autres PME.</p>
                    </div>
                    <div class="badge">Exploitation terrain</div>
                </div>

                <div class="grid">
                    @foreach ($industries as $industry)
                        <article class="industry-card">
                            <strong>{{ $industry['name'] }}</strong>
                            <p>{{ $industry['detail'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section id="modules" class="foundation-section">
            <div class="section-inner">
                <div class="section-head">
                    <div>
                        <h2>Modules prêts à exploiter</h2>
                        <p>Les briques importantes sont disponibles dès l’ouverture de l’espace.</p>
                    </div>
                    <div class="badge">ERP opérationnel</div>
                </div>

                <div class="module-board">
                    @foreach ($modules as $module)
                        <div class="module-line">
                            <strong>{{ $module['name'] }}</strong>
                            <em class="status">{{ $module['status'] }}</em>
                            <span>{{ $module['detail'] }}</span>
                        </div>
                    @endforeach
                </div>

                <div class="assurance-grid">
                    @foreach ($assurances as $assurance)
                        <div class="assurance">
                            <strong>{{ $assurance['title'] }}</strong>
                            <span>{{ $assurance['text'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section id="fondations" class="foundation-section">
            <div class="section-inner">
                <div class="section-head">
                    <div>
                        <h2>Les 4 étapes avant d’entrer dans Nema</h2>
                        <p>Une base claire pour chaque entreprise et chaque équipe.</p>
                    </div>
                    <div class="badge">Avant connexion</div>
                </div>

                <div class="foundation-timeline" aria-label="Parcours de création">
                    @foreach ($foundations as $foundation)
                        <div class="timeline-step">
                            <span>{{ $foundation['number'] }}</span>
                            {{ $foundation['title'] }}
                        </div>
                    @endforeach
                </div>

                <div class="foundation-flow">
                    @foreach ($foundations as $foundation)
                        <article class="foundation-card">
                            <div class="foundation-card__top">
                                <div class="foundation-number">{{ $foundation['number'] }}</div>
                                <div class="foundation-state">Préparation</div>
                            </div>
                            <h3>{{ $foundation['title'] }}</h3>
                            <p>{{ $foundation['text'] }}</p>

                            <div class="foundation-block">
                                <strong>Informations demandées</strong>
                                <ul class="foundation-list">
                                    @foreach ($foundation['collects'] as $item)
                                        <li>{{ $item }}</li>
                                    @endforeach
                                </ul>
                            </div>

                            <div class="foundation-block">
                                <strong>Nema prépare</strong>
                                <ul class="foundation-list">
                                    @foreach ($foundation['prepares'] as $item)
                                        <li>{{ $item }}</li>
                                    @endforeach
                                </ul>
                            </div>

                            <div class="foundation-check">{{ $foundation['check'] }}</div>
                            <div class="foundation-result">{{ $foundation['result'] }}</div>
                        </article>
                    @endforeach
                </div>

                <div class="final-action">
                    <div>
                        <strong>Créer, configurer, vendre</strong>
                        <span>Nema prépare la base avant que l’utilisateur arrive dans l’ERP métier.</span>
                    </div>
                    <a class="button button-primary" href="{{ route('saas.register.account') }}">Commencer maintenant</a>
                </div>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="footer-inner">
            <span>Nema Technologies - ERP, caisse et stock pour PME</span>
            <span><a href="{{ route('saas.register.account') }}">Créer un espace</a> · <a href="{{ route('login') }}">Se connecter</a></span>
        </div>
    </footer>
</body>
</html>
