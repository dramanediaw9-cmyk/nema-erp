<!doctype html>
<html lang="fr">
<head>
    @include('partials.security-csp-meta')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $status }} — Nema ERP</title>
    <style>
        :root { color-scheme: light; font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 20px; color: #172033; background: linear-gradient(145deg, #eef7f5, #f8efe3); }
        main { width: min(560px, 100%); padding: 28px; border: 1px solid rgba(15, 76, 83, .14); border-radius: 22px; background: rgba(255, 255, 255, .94); box-shadow: 0 24px 70px rgba(15, 58, 63, .12); }
        .brand { display: flex; align-items: center; gap: 12px; color: #0b4f56; font-weight: 800; }
        .mark { display: grid; place-items: center; width: 44px; height: 44px; border-radius: 13px; color: #fff; background: linear-gradient(135deg, #0f766e, #0b4f56); }
        .code { margin: 28px 0 4px; color: #ca6702; font-size: 14px; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; }
        h1 { margin: 0; font-size: clamp(26px, 5vw, 38px); line-height: 1.1; }
        p { margin: 14px 0 0; color: #64748b; line-height: 1.6; }
        .actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 24px; }
        a { display: inline-flex; align-items: center; min-height: 42px; padding: 10px 15px; border-radius: 11px; color: #fff; background: #0b4f56; font-weight: 750; text-decoration: none; }
        a.secondary { color: #172033; background: #f1e6d7; }
    </style>
</head>
<body>
<main role="main">
    <div class="brand"><span class="mark" aria-hidden="true">N</span><span>Nema ERP</span></div>
    <div class="code">Erreur {{ $status }}</div>
    <h1>{{ $title }}</h1>
    <p>{{ $message }}</p>
    <div class="actions">
        @auth
            <a href="{{ route('dashboard') }}">Retour au tableau de bord</a>
        @else
            <a href="{{ route('login') }}">Retour a la connexion</a>
        @endauth
        <a class="secondary" href="{{ url('/') }}">Accueil</a>
    </div>
</main>
</body>
</html>
