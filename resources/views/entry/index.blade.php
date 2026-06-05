<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nema Technologies - ERP de gestion d entreprise</title>
    <style>
        :root {
            --ink: #10201f;
            --muted: #667370;
            --line: #dce6e2;
            --soft: #f3f8f6;
            --brand: #0f766e;
            --brand-dark: #0b4f4a;
            --gold: #f2b84b;
            --blue: #2f80ed;
            --white: #ffffff;
        }

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            color: var(--ink);
            background: var(--white);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        a {
            color: inherit;
        }

        .nav {
            width: min(1240px, calc(100% - 40px));
            margin: 0 auto;
            min-height: 82px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            font-size: 20px;
            font-weight: 900;
            letter-spacing: 0;
        }

        .mark {
            width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            border-radius: 10px;
            background: var(--brand);
            color: var(--white);
            font-weight: 900;
        }

        .nav-links {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: clamp(18px, 3vw, 38px);
            color: #374151;
            font-weight: 760;
        }

        .nav a {
            text-decoration: none;
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 820;
        }

        .login {
            color: var(--brand-dark);
        }

        .trial {
            color: var(--white);
            background: var(--brand);
            border-radius: 8px;
            padding: 13px 16px;
        }

        .hero {
            min-height: calc(100vh - 82px);
            display: grid;
            place-items: center;
            position: relative;
            overflow: hidden;
            border-top: 1px solid var(--line);
            background:
                linear-gradient(135deg, rgba(15, 118, 110, .12), rgba(47, 128, 237, .06) 52%, rgba(242, 184, 75, .12)),
                #fbfdfc;
        }

        .hero::after {
            content: "";
            position: absolute;
            left: 50%;
            bottom: -170px;
            width: min(1400px, 120vw);
            height: 260px;
            transform: translateX(-50%);
            border-radius: 50% 50% 0 0;
            background: #eef3f1;
        }

        .hero-inner {
            width: min(1120px, calc(100% - 40px));
            margin: 0 auto;
            padding: 70px 0 130px;
            position: relative;
            z-index: 1;
            text-align: center;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid #b8d9d4;
            border-radius: 999px;
            background: rgba(255, 255, 255, .76);
            color: var(--brand-dark);
            padding: 9px 14px;
            font-size: 14px;
            font-weight: 900;
        }

        h1 {
            margin: 22px auto 0;
            max-width: 1050px;
            color: var(--ink);
            font-size: clamp(42px, 7vw, 94px);
            line-height: .98;
            letter-spacing: 0;
        }

        .accent {
            position: relative;
            display: inline-block;
            padding: 0 .08em .06em;
        }

        .accent::after {
            content: "";
            position: absolute;
            left: 0;
            right: 0;
            bottom: .06em;
            height: .22em;
            border-radius: 999px;
            background: rgba(242, 184, 75, .75);
            z-index: -1;
        }

        .lead {
            max-width: 760px;
            margin: 26px auto 0;
            color: var(--muted);
            font-size: 21px;
            line-height: 1.62;
        }

        .actions {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 14px;
            flex-wrap: wrap;
            margin-top: 34px;
        }

        .button {
            min-height: 54px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            padding: 15px 22px;
            text-decoration: none;
            font-weight: 900;
            border: 1px solid transparent;
        }

        .button-primary {
            background: var(--brand);
            color: var(--white);
        }

        .button-secondary {
            background: var(--white);
            border-color: var(--line);
            color: var(--brand-dark);
        }

        .proof {
            margin: 34px auto 0;
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            max-width: 920px;
            text-align: left;
        }

        .proof-item {
            border: 1px solid rgba(220, 230, 226, .95);
            border-radius: 8px;
            background: rgba(255, 255, 255, .82);
            padding: 14px;
            font-weight: 850;
            color: var(--brand-dark);
        }

        .proof-item span {
            display: block;
            color: var(--muted);
            font-size: 13px;
            font-weight: 680;
            margin-top: 5px;
            line-height: 1.42;
        }

        .foundations {
            background: var(--soft);
            border-top: 1px solid var(--line);
            padding: 72px 20px 86px;
        }

        .section-inner {
            width: min(1160px, 100%);
            margin: 0 auto;
        }

        .section-head {
            display: flex;
            align-items: end;
            justify-content: space-between;
            gap: 22px;
            flex-wrap: wrap;
            margin-bottom: 24px;
        }

        h2 {
            margin: 0 0 8px;
            font-size: clamp(30px, 4vw, 48px);
            line-height: 1.08;
            letter-spacing: 0;
        }

        .muted {
            color: var(--muted);
            font-size: 18px;
            line-height: 1.6;
        }

        .pill {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            background: #e4f4f1;
            border: 1px solid #b7dcd6;
            color: var(--brand-dark);
            padding: 10px 14px;
            font-weight: 900;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
        }

        .card {
            min-height: 268px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--white);
            padding: 22px;
            display: flex;
            flex-direction: column;
            gap: 13px;
            box-shadow: 0 18px 36px rgba(16, 32, 31, .05);
        }

        .number {
            width: fit-content;
            border-radius: 999px;
            background: #fff2d3;
            color: #93610f;
            padding: 7px 11px;
            font-size: 13px;
            font-weight: 900;
        }

        .card h3 {
            margin: 0;
            font-size: 22px;
            letter-spacing: 0;
        }

        .result {
            margin-top: auto;
            border-top: 1px solid var(--line);
            padding-top: 13px;
            color: var(--brand-dark);
            font-weight: 900;
            line-height: 1.48;
        }

        .notice {
            margin-top: 28px;
            border: 1px solid #ead39b;
            border-radius: 8px;
            background: #fff9eb;
            padding: 18px 20px;
            color: #6b4a12;
            font-size: 17px;
            line-height: 1.6;
        }

        @media (max-width: 960px) {
            .nav {
                flex-wrap: wrap;
            }

            .nav-links {
                order: 3;
                width: 100%;
                justify-content: flex-start;
                overflow-x: auto;
                padding-bottom: 4px;
            }

            .proof,
            .grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 620px) {
            .nav {
                width: min(100% - 28px, 1240px);
                align-items: flex-start;
            }

            .nav-actions {
                width: 100%;
                justify-content: space-between;
            }

            .nav-links {
                gap: 18px;
                font-size: 15px;
            }

            .hero-inner {
                width: min(100% - 28px, 1120px);
                padding-top: 46px;
                padding-bottom: 112px;
            }

            h1 {
                font-size: clamp(38px, 12vw, 58px);
            }

            .lead {
                font-size: 17px;
            }

            .button {
                width: 100%;
            }

            .proof,
            .grid {
                grid-template-columns: 1fr;
            }

            .card {
                min-height: auto;
            }
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
            <a href="#fondations">Applications</a>
            <a href="#fondations">Secteurs</a>
            <a href="#fondations">Methode</a>
            <a href="#fondations">Tarifs</a>
            <a href="#fondations">Aide</a>
        </nav>

        <div class="nav-actions">
            <a class="login" href="{{ route('login') }}">Se connecter</a>
            <a class="trial" href="#fondations">Demarrer</a>
        </div>
    </header>

    <main>
        <section class="hero" aria-label="Presentation Nema ERP">
            <div class="hero-inner">
                <div class="eyebrow">ERP, CRM, caisse et stock pour PME ambitieuses</div>

                <h1>
                    Pilotez toute votre entreprise
                    <span class="accent">depuis Nema.</span>
                </h1>

                <p class="lead">
                    Nema ne commence pas par une vente. La plateforme prepare d abord le compte,
                    l entreprise, la comptabilite et les donnees de base pour que chaque operation soit fiable.
                </p>

                <div class="actions">
                    <a class="button button-primary" href="#fondations">Voir les 4 fondations</a>
                    <a class="button button-secondary" href="{{ route('login') }}">J ai deja un espace</a>
                </div>

                <div class="proof" aria-label="Modules Nema">
                    <div class="proof-item">ERP complet<span>Ventes, achats, stock, caisse et rapports.</span></div>
                    <div class="proof-item">CRM terrain<span>Clients, fournisseurs, relances et opportunites.</span></div>
                    <div class="proof-item">Compta propre<span>Taxes, caisse, banque et mobile money.</span></div>
                    <div class="proof-item">Base maitrisee<span>Donnees fiables avant la premiere vente.</span></div>
                </div>
            </div>
        </section>

        <section id="fondations" class="foundations">
            <div class="section-inner">
                <div class="section-head">
                    <div>
                        <h2>Les 4 fondations avant d entrer dans Nema</h2>
                        <div class="muted">Une entreprise solide ne vend pas avant d avoir pose sa base.</div>
                    </div>
                    <div class="pill">Avant connexion</div>
                </div>

                <div class="grid">
                    @foreach ($foundations as $foundation)
                        <article class="card">
                            <div class="number">{{ $foundation['number'] }}</div>
                            <h3>{{ $foundation['title'] }}</h3>
                            <div class="muted">{{ $foundation['text'] }}</div>
                            <div class="result">{{ $foundation['result'] }}</div>
                        </article>
                    @endforeach
                </div>

                <div class="notice">
                    Pour Nema Technologies, l espace existe deja. Cette page pose maintenant la bonne entree :
                    on comprend les bases avant d utiliser l ERP au quotidien.
                </div>
            </div>
        </section>
    </main>
</body>
</html>
