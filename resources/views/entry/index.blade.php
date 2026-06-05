<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nema ERP - Preparation de l espace entreprise</title>
    <style>
        :root { --ink:#13201c; --muted:#66736f; --line:#dbe5df; --soft:#f4f8f6; --brand:#0f766e; --dark:#0b4f4a; --warn:#b7791f; --white:#fff; }
        * { box-sizing:border-box; }
        body { margin:0; font-family:Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color:var(--ink); background:#fbfcfb; }
        a { color:inherit; }
        .topbar, .hero-inner, .content { width:min(1120px, calc(100% - 32px)); margin:0 auto; }
        .topbar { display:flex; align-items:center; justify-content:space-between; gap:18px; padding:18px 0; }
        .brand { display:flex; align-items:center; gap:10px; font-weight:900; }
        .mark { width:38px; height:38px; display:grid; place-items:center; border-radius:10px; background:var(--brand); color:var(--white); }
        .login-link, .button { border-radius:8px; text-decoration:none; font-weight:800; }
        .login-link { border:1px solid var(--line); padding:10px 14px; background:var(--white); }
        .hero { border-top:1px solid var(--line); border-bottom:1px solid var(--line); background:linear-gradient(120deg, rgba(15,118,110,.12), rgba(255,255,255,0) 46%), linear-gradient(0deg, #fff, #f2f8f5); }
        .hero-inner { padding:54px 0 44px; display:grid; grid-template-columns:minmax(0,1.08fr) minmax(320px,.92fr); gap:28px; align-items:stretch; }
        .eyebrow { display:inline-flex; width:fit-content; border:1px solid #b9d8d2; border-radius:999px; padding:8px 12px; color:var(--dark); background:rgba(255,255,255,.8); font-size:13px; font-weight:900; }
        h1 { margin:16px 0 14px; max-width:780px; font-size:clamp(36px, 5vw, 68px); line-height:1; letter-spacing:0; }
        .lead { max-width:700px; color:var(--muted); font-size:18px; line-height:1.6; }
        .actions { display:flex; flex-wrap:wrap; gap:12px; margin-top:28px; }
        .button { display:inline-flex; align-items:center; justify-content:center; min-height:46px; padding:12px 16px; border:1px solid transparent; }
        .button-primary { background:var(--brand); color:var(--white); }
        .button-secondary { background:var(--white); border-color:var(--line); }
        .panel, .foundation { background:var(--white); border:1px solid var(--line); border-radius:8px; }
        .panel { padding:22px; box-shadow:0 24px 60px rgba(19,32,28,.08); }
        .panel-title { font-size:18px; font-weight:900; margin-bottom:12px; }
        .checklist { display:grid; gap:12px; }
        .check-row { display:grid; grid-template-columns:34px 1fr; gap:12px; align-items:start; padding:12px; border:1px solid var(--line); border-radius:8px; background:var(--soft); }
        .tick { width:34px; height:34px; display:grid; place-items:center; border-radius:8px; background:#dff3ee; color:var(--dark); font-weight:900; font-size:13px; }
        .row-title { font-weight:900; margin-bottom:4px; }
        .row-text, .muted { color:var(--muted); line-height:1.55; }
        .row-text { font-size:14px; }
        .content { padding:34px 0 52px; }
        .section-head { margin-bottom:18px; }
        h2 { margin:0; font-size:28px; letter-spacing:0; }
        .grid { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:14px; }
        .foundation { padding:18px; min-height:248px; display:flex; flex-direction:column; gap:12px; }
        .number { width:fit-content; border-radius:999px; background:#fff4de; color:var(--warn); padding:6px 10px; font-size:12px; font-weight:900; }
        .foundation h3 { margin:0; font-size:19px; }
        .result { margin-top:auto; border-top:1px solid var(--line); padding-top:12px; color:var(--dark); font-size:14px; font-weight:800; line-height:1.45; }
        .notice { margin-top:22px; border:1px solid #f0d59f; border-radius:8px; background:#fff9ed; padding:16px; color:#65440d; line-height:1.55; }
        @media (max-width:900px) { .hero-inner { grid-template-columns:1fr; } .grid { grid-template-columns:repeat(2, minmax(0, 1fr)); } }
        @media (max-width:620px) { .topbar { align-items:flex-start; flex-direction:column; } .hero-inner { padding-top:36px; } .grid { grid-template-columns:1fr; } .foundation { min-height:auto; } }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="brand"><div class="mark">N</div><div>Nema ERP</div></div>
        <a class="login-link" href="{{ route('login') }}">J ai deja un espace</a>
    </header>

    <main>
        <section class="hero">
            <div class="hero-inner">
                <div>
                    <div class="eyebrow">Avant d entrer dans Nema</div>
                    <h1>On prepare d abord la base de l entreprise.</h1>
                    <p class="lead">Une vente ne doit pas venir avant le compte, la base entreprise, la comptabilite et les donnees de base. Nema suit cette logique pour eviter les erreurs des le premier jour.</p>
                    <div class="actions">
                        <a class="button button-primary" href="#fondations">Voir les 4 fondations</a>
                        <a class="button button-secondary" href="{{ route('login') }}">Entrer dans mon espace</a>
                    </div>
                </div>

                <aside class="panel" aria-label="Ordre recommande avant la premiere vente">
                    <div class="panel-title">Ordre obligatoire avant vente</div>
                    <div class="checklist">
                        @foreach ($foundations as $foundation)
                            <div class="check-row">
                                <div class="tick">{{ $foundation['number'] }}</div>
                                <div>
                                    <div class="row-title">{{ $foundation['title'] }}</div>
                                    <div class="row-text">{{ $foundation['result'] }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </aside>
            </div>
        </section>

        <section id="fondations" class="content">
            <div class="section-head">
                <h2>Les 4 fondations avant Nema</h2>
                <div class="muted">C est le meme principe qu un ERP solide : on cree l espace correctement avant de vendre.</div>
            </div>

            <div class="grid">
                @foreach ($foundations as $foundation)
                    <article class="foundation">
                        <div class="number">{{ $foundation['number'] }}</div>
                        <h3>{{ $foundation['title'] }}</h3>
                        <div class="muted">{{ $foundation['text'] }}</div>
                        <div class="result">{{ $foundation['result'] }}</div>
                    </article>
                @endforeach
            </div>

            <div class="notice">Pour la version actuelle de Nema Technologies, l espace existe deja. Cette page empeche l entree directe dans l ERP depuis le domaine principal et pose la bonne logique avant la connexion.</div>
        </section>
    </main>
</body>
</html>
