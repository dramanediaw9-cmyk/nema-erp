<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nema ERP - ERP et CRM pour entreprises ambitieuses</title>
    <style>
        :root {
            --ink: #111827;
            --muted: #5f6673;
            --soft: #f6f7fb;
            --line: #e5e7eb;
            --brand: #1f8a83;
            --brand-dark: #12625d;
            --accent: #ffb12b;
            --purple: #765070;
            --blue: #20aeea;
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
            width: min(1680px, calc(100% - 44px));
            margin: 0 auto;
            min-height: 88px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 28px;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            font-size: 32px;
            font-weight: 900;
            color: #7b7f86;
            letter-spacing: -1px;
        }

        .brand-dot {
            width: 24px;
            height: 24px;
            border: 6px solid var(--purple);
            border-radius: 50%;
            display: inline-block;
        }

        .nav-links {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: clamp(18px, 3vw, 48px);
            flex: 1;
            font-size: 20px;
            font-weight: 760;
            color: #374151;
        }

        .nav-links a,
        .nav-actions a {
            text-decoration: none;
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 18px;
            font-size: 19px;
            font-weight: 780;
        }

        .trial {
            background: var(--purple);
            color: var(--white);
            padding: 16px 24px;
            border-radius: 6px;
        }

        .hero {
            min-height: calc(100vh - 88px);
            position: relative;
            overflow: hidden;
            display: grid;
            place-items: center;
            padding: 70px 22px 160px;
        }

        .hero::after {
            content: "";
            position: absolute;
            left: 50%;
            bottom: -160px;
            width: min(1500px, 120vw);
            height: 250px;
            transform: translateX(-50%);
            border-radius: 50% 50% 0 0;
            background: #f1f2f5;
            z-index: 0;
        }

        .hero-inner {
            width: min(1100px, 100%);
            position: relative;
            z-index: 1;
            text-align: center;
        }

        h1 {
            margin: 0;
            color: #111827;
            font-family: "Segoe Print", "Bradley Hand ITC", "Comic Sans MS", cursive;
            font-size: clamp(58px, 8vw, 116px);
            line-height: .98;
            font-weight: 900;
            letter-spacing: 0;
        }

        .highlight {
            position: relative;
            display: inline-block;
            padding: 0 .14em .04em;
            z-index: 1;
        }

        .highlight::before {
            content: "";
            position: absolute;
            left: -.02em;
            right: -.02em;
            top: 48%;
            height: .62em;
            background: var(--accent);
            border-radius: 18px;
            transform: rotate(-1.8deg);
            z-index: -1;
        }

        .subline {
            display: inline-block;
            position: relative;
            margin-top: 22px;
            color: #111827;
            font-family: "Segoe Print", "Bradley Hand ITC", "Comic Sans MS", cursive;
            font-size: clamp(34px, 4.2vw, 66px);
            line-height: 1.1;
            font-weight: 900;
        }

        .subline::after {
            content: "";
            position: absolute;
            right: 4%;
            bottom: -10px;
            width: 32%;
            height: 9px;
            background: var(--blue);
            border-radius: 999px;
            transform: rotate(-2deg);
        }

        .hero-copy {
            max-width: 760px;
            margin: 30px auto 0;
            color: var(--muted);
            font-size: 20px;
            line-height: 1.65;
        }

        .actions {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 22px;
            flex-wrap: wrap;
            margin-top: 38px;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 72px;
            padding: 20px 34px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 24px;
            font-weight: 900;
        }

        .button-primary {
            background: var(--purple);
            color: var(--white);
        }

        .button-secondary {
            background: #f5f5f7;
            color: var(--purple);
        }

        .note {
            position: absolute;
            right: 8%;
            bottom: 62px;
            color: var(--purple);
            font-family: "Segoe Print", "Bradley Hand ITC", "Comic Sans MS", cursive;
            font-size: clamp(22px, 2.5vw, 36px);
            font-weight: 900;
            line-height: 1.18;
            transform: rotate(-8deg);
            text-align: left;
        }

        .note::before {
            content: "";
            position: absolute;
            left: -70px;
            top: -58px;
            width: 66px;
            height: 70px;
            border-right: 5px solid var(--purple);
            border-bottom: 5px solid var(--purple);
            border-radius: 0 0 56px 0;
            transform: rotate(-22deg);
            opacity: .95;
        }

        .foundations {
            background: var(--soft);
            border-top: 1px solid var(--line);
            padding: 76px 22px 84px;
        }

        .section-inner {
            width: min(1180px, 100%);
            margin: 0 auto;
        }

        .section-head {
            display: flex;
            align-items: end;
            justify-content: space-between;
            gap: 24px;
            flex-wrap: wrap;
            margin-bottom: 24px;
        }

        h2 {
            margin: 0 0 8px;
            font-size: clamp(32px, 4vw, 52px);
            line-height: 1.05;
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
            background: #e7f5f2;
            color: var(--brand-dark);
            border: 1px solid #bfe0da;
            border-radius: 999px;
            padding: 10px 14px;
            font-weight: 900;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
        }

        .card {
            min-height: 270px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--white);
            padding: 22px;
            display: flex;
            flex-direction: column;
            gap: 14px;
            box-shadow: 0 18px 36px rgba(17, 24, 39, .05);
        }

        .number {
            width: fit-content;
            border-radius: 999px;
            background: #fff3d8;
            color: #9a610d;
            padding: 7px 11px;
            font-size: 13px;
            font-weight: 900;
        }

        .card h3 {
            margin: 0;
            font-size: 23px;
            letter-spacing: 0;
        }

        .result {
            margin-top: auto;
            border-top: 1px solid var(--line);
            padding-top: 14px;
            color: var(--brand-dark);
            font-weight: 900;
            line-height: 1.5;
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

        @media (max-width: 1120px) {
            .nav {
                flex-wrap: wrap;
                justify-content: center;
            }

            .brand {
                margin-right: auto;
            }

            .nav-links {
                order: 3;
                width: 100%;
                overflow-x: auto;
                justify-content: flex-start;
                padding-bottom: 4px;
            }

            .note {
                position: relative;
                right: auto;
                bottom: auto;
                width: fit-content;
                margin: 34px auto 0;
            }
        }

        @media (max-width: 900px) {
            .grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .button {
                min-height: 58px;
                font-size: 19px;
                padding: 16px 22px;
            }
        }

        @media (max-width: 640px) {
            .nav {
                width: min(100% - 28px, 1680px);
                align-items: flex-start;
            }

            .nav-actions {
                width: 100%;
                justify-content: space-between;
                font-size: 16px;
            }

            .trial {
                padding: 12px 14px;
            }

            .hero {
                padding-top: 44px;
                padding-bottom: 118px;
            }

            h1 {
                font-size: clamp(46px, 14vw, 72px);
            }

            .subline {
                font-size: clamp(30px, 9vw, 48px);
            }

            .hero-copy {
                font-size: 17px;
            }

            .actions {
                align-items: stretch;
            }

            .button {
                width: 100%;
            }

            .note {
                font-size: 24px;
            }

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
        <a class="brand" href="{{ route('entry.index') }}" aria-label="Nema ERP">
            <span class="brand-dot"></span>
            <span>nema</span>
        </a>

        <nav class="nav-links" aria-label="Navigation principale">
            <a href="#fondations">Applications</a>
            <a href="#fondations">Industries</a>
            <a href="#fondations">Communaute</a>
            <a href="#fondations">Tarification</a>
            <a href="#fondations">Aide</a>
        </nav>

        <div class="nav-actions">
            <a href="{{ route('login') }}">Se connecter</a>
            <a class="trial" href="#fondations">Essai gratuit</a>
        </div>
    </header>

    <main>
        <section class="hero" aria-label="Presentation Nema ERP">
            <div class="hero-inner">
                <h1>
                    Tout votre business sur<br>
                    <span class="highlight">une plateforme.</span>
                </h1>

                <div class="subline">Simple, efficace, et abordable !</div>

                <p class="hero-copy">
                    Avant la premiere vente, Nema prepare le compte, la base entreprise,
                    la comptabilite et les donnees essentielles. L ERP devient propre des le depart.
                </p>

                <div class="actions">
                    <a class="button button-primary" href="#fondations">Lancez-vous - c est gratuit</a>
                    <a class="button button-secondary" href="{{ route('login') }}">J ai deja un espace</a>
                </div>

                <div class="note">ERP + CRM + Caisse + Stock<br>dans un seul espace</div>
            </div>
        </section>

        <section id="fondations" class="foundations">
            <div class="section-inner">
                <div class="section-head">
                    <div>
                        <h2>Les 4 fondations avant Nema</h2>
                        <div class="muted">Comme dans un ERP solide, on cree d abord la base. La vente vient apres.</div>
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
                    on ne va pas directement vendre, on comprend d abord les bases qui rendent l ERP fiable.
                </div>
            </div>
        </section>
    </main>
</body>
</html>
