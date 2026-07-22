<!DOCTYPE html>
<html lang="fr">
<head>
    @include('partials.security-csp-meta')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Mot de passe oublié - Nema ERP</title>
    <style>
        body { margin:0; min-height:100vh; display:grid; place-items:center; background:#eef3f4; font-family:"Segoe UI",Tahoma,sans-serif; color:#172b31; }
        .panel { width:min(440px,calc(100vw - 32px)); background:#fff; border:1px solid #d6e0e2; border-radius:8px; padding:28px; box-shadow:0 14px 36px rgba(23,43,49,.09); }
        h1 { margin:0 0 8px; font-size:26px; } p { color:#607178; }
        label { display:block; font-size:14px; font-weight:700; margin:18px 0 8px; }
        input { width:100%; box-sizing:border-box; padding:12px; border:1px solid #b9c8cc; border-radius:6px; font:inherit; }
        button { width:100%; margin-top:18px; padding:12px; border:0; border-radius:6px; background:#005f73; color:#fff; font-weight:800; cursor:pointer; }
        .alert,.success { padding:12px; border-radius:6px; margin:16px 0; }
        .alert { background:#feeceb; color:#94261e; } .success { background:#e8f6ee; color:#17643a; }
        a { display:inline-block; margin-top:18px; color:#005f73; font-weight:700; text-decoration:none; }
    </style>
</head>
<body>
<main class="panel">
    <h1>Retrouver votre accès</h1>
    <p>Indiquez votre adresse e-mail. Nema vous enverra un lien sécurisé de réinitialisation.</p>

    @if (session('status'))<div class="success">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="alert">{{ $errors->first() }}</div>@endif

    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <label for="email">Adresse e-mail</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email">
        <button type="submit">Envoyer le lien</button>
    </form>

    <a href="{{ route('login') }}">Retour à la connexion</a>
</main>
</body>
</html>
