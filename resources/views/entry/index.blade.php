<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nema Technologies - ERP pour entreprises</title>
    <style>
        :root { --ink:#10201f; --muted:#667370; --line:#dce6e2; --soft:#f3f8f6; --brand:#0f766e; --brand-dark:#0b4f4a; --gold:#f2b84b; --white:#fff; }
        * { box-sizing:border-box; }
        body { margin:0; color:var(--ink); background:var(--white); font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; }
        a { color:inherit; }
        .nav { width:min(1200px,calc(100% - 36px)); min-height:70px; margin:0 auto; display:flex; align-items:center; justify-content:space-between; gap:22px; }
        .brand { display:inline-flex; align-items:center; gap:10px; text-decoration:none; font-weight:900; }
        .mark { width:38px; height:38px; display:grid; place-items:center; color:var(--white); background:var(--brand); border-radius:8px; }
        .nav-links { display:flex; gap:28px; color:#374151; font-size:14px; font-weight:800; }
        .nav-links a,.nav-actions a { text-decoration:none; }
        .nav-actions { display:flex; align-items:center; gap:14px; font-size:14px; font-weight:850; }
        .start { padding:11px 15px; color:var(--white); background:var(--brand); border-radius:7px; }
        .hero { min-height:calc(82vh - 70px); display:grid; align-items:center; background:#f8fbfa; border-block:1px solid var(--line); }
        .hero-inner { width:min(1040px,calc(100% - 36px)); margin:0 auto; padding:64px 0 74px; text-align:center; }
        .eyebrow { color:var(--brand-dark); font-size:14px; font-weight:900; text-transform:uppercase; }
        h1 { max-width:900px; margin:16px auto 0; font-size:clamp(42px,7vw,80px); line-height:1.02; letter-spacing:0; }
        .accent { text-decoration:underline; text-decoration-color:var(--gold); text-decoration-thickness:.16em; text-underline-offset:.08em; }
        .lead { max-width:720px; margin:22px auto 0; color:var(--muted); font-size:19px; line-height:1.6; }
        .actions { margin-top:30px; display:flex; justify-content:center; gap:12px; flex-wrap:wrap; }
        .button { min-height:48px; display:inline-flex; align-items:center; justify-content:center; padding:12px 19px; border:1px solid transparent; border-radius:7px; font-weight:900; text-decoration:none; }
        .button-primary { color:var(--white); background:var(--brand); }
        .button-secondary { color:var(--brand-dark); background:var(--white); border-color:var(--line); }
        .foundation-section { padding:62px 18px 72px; background:var(--white); }
        .section-inner { width:min(1120px,100%); margin:0 auto; }
        .section-head { margin-bottom:22px; display:flex; align-items:end; justify-content:space-between; gap:18px; }
        h2 { margin:0 0 7px; font-size:clamp(28px,4vw,42px); letter-spacing:0; }
        .section-head p { margin:0; color:var(--muted); }
        .badge { padding:8px 11px; color:var(--brand-dark); background:#e4f4f1; border:1px solid #b7dcd6; border-radius:999px; font-size:13px; font-weight:900; }
        .grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; }
        .card { min-height:232px; padding:18px; display:flex; flex-direction:column; border:1px solid var(--line); border-radius:8px; }
        .number { color:#8a5b08; font-size:13px; font-weight:900; }
        .card h3 { margin:15px 0 8px; font-size:20px; letter-spacing:0; }
        .card p { margin:0; color:var(--muted); font-size:14px; line-height:1.55; }
        .result { margin-top:auto; padding-top:14px; color:var(--brand-dark); border-top:1px solid var(--line); font-size:14px; font-weight:850; line-height:1.45; }
        .final-action { margin-top:24px; padding:22px; display:flex; align-items:center; justify-content:space-between; gap:18px; background:var(--soft); border:1px solid var(--line); border-radius:8px; }
        .final-action strong { display:block; margin-bottom:4px; }
        .final-action span { color:var(--muted); font-size:14px; }
        @media (max-width:850px) { .nav-links { display:none; } .grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
        @media (max-width:580px) {
            .nav { flex-wrap:wrap; padding:12px 0; }
            .nav-actions { width:100%; justify-content:space-between; }
            .hero-inner { padding:50px 0 58px; }
            .lead { font-size:17px; }
            .button { width:100%; }
            .section-head,.final-action { align-items:stretch; flex-direction:column; }
            .grid { grid-template-columns:1fr; }
            .card { min-height:auto; }
        }
    </style>
</head>
<body>
    <header class="nav">
        <a class="brand" href="{{ route('entry.index') }}" aria-label="Nema Technologies"><span class="mark">N</span><span>Nema Technologies</span></a>
        <nav class="nav-links" aria-label="Navigation principale"><a href="#fondations">Applications</a><a href="#fondations">Secteurs</a><a href="#fondations">Méthode</a><a href="#fondations">Tarifs</a></nav>
        <div class="nav-actions"><a href="{{ route('login') }}">Se connecter</a><a class="start" href="{{ route('saas.register.account') }}">Démarrer</a></div>
    </header>

    <main>
        <section class="hero">
            <div class="hero-inner">
                <div class="eyebrow">ERP, CRM, caisse et stock pour PME</div>
                <h1>Nema prépare votre entreprise <span class="accent">avant la première vente.</span></h1>
                <p class="lead">Créez votre compte, votre entreprise, votre espace métier et votre formule. Nema ouvre ensuite un environnement séparé, prêt à configurer.</p>
                <div class="actions"><a class="button button-primary" href="{{ route('saas.register.account') }}">Créer mon espace Nema</a><a class="button button-secondary" href="{{ route('login') }}">J’ai déjà un espace</a></div>
            </div>
        </section>

        <section id="fondations" class="foundation-section">
            <div class="section-inner">
                <div class="section-head"><div><h2>Les 4 étapes avant d’entrer dans Nema</h2><p>Une base claire pour chaque entreprise et chaque équipe.</p></div><div class="badge">Avant connexion</div></div>
                <div class="grid">
                    @foreach ($foundations as $foundation)
                        <article class="card"><div class="number">{{ $foundation['number'] }}</div><h3>{{ $foundation['title'] }}</h3><p>{{ $foundation['text'] }}</p><div class="result">{{ $foundation['result'] }}</div></article>
                    @endforeach
                </div>
                <div class="final-action"><div><strong>Essai de 14 jours</strong><span>Aucun paiement demandé pour créer votre espace.</span></div><a class="button button-primary" href="{{ route('saas.register.account') }}">Commencer maintenant</a></div>
            </div>
        </section>
    </main>
</body>
</html>
