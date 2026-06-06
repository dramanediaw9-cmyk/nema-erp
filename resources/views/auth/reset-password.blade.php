<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Nouveau mot de passe - Nema ERP</title>
    <style>
        body { margin:0; min-height:100vh; display:grid; place-items:center; background:#eef3f4; font-family:"Segoe UI",Tahoma,sans-serif; color:#172b31; }
        .panel { width:min(440px,calc(100vw - 32px)); background:#fff; border:1px solid #d6e0e2; border-radius:8px; padding:28px; box-shadow:0 14px 36px rgba(23,43,49,.09); }
        h1 { margin:0 0 8px; font-size:26px; } p { color:#607178; }
        label { display:block; font-size:14px; font-weight:700; margin:16px 0 8px; }
        input { width:100%; box-sizing:border-box; padding:12px; border:1px solid #b9c8cc; border-radius:6px; font:inherit; }
        button { width:100%; margin-top:18px; padding:12px; border:0; border-radius:6px; background:#005f73; color:#fff; font-weight:800; cursor:pointer; }
        .alert { padding:12px; border-radius:6px; margin:16px 0; background:#feeceb; color:#94261e; }
    </style>
</head>
<body>
<main class="panel">
    <h1>Créer un nouveau mot de passe</h1>
    <p>Utilisez au moins huit caractères et évitez un mot de passe déjà utilisé ailleurs.</p>

    @if ($errors->any())<div class="alert">{{ $errors->first() }}</div>@endif

    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <label for="email">Adresse e-mail</label>
        <input id="email" type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="email">

        <label for="password">Nouveau mot de passe</label>
        <input id="password" type="password" name="password" required autocomplete="new-password">

        <label for="password_confirmation">Confirmer le mot de passe</label>
        <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">

        <button type="submit">Enregistrer le nouveau mot de passe</button>
    </form>
</main>
</body>
</html>
