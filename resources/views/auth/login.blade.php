<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Connexion - Nema ERP</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            background: radial-gradient(circle at top right, #fef0d9, #f6f1e8 45%, #e5f0f2 100%);
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            color: #291f16;
        }
        .panel {
            width: min(460px, calc(100vw - 32px));
            background: rgba(255,255,255,.9);
            border: 1px solid rgba(228,216,196,.9);
            border-radius: 24px;
            padding: 30px;
            box-shadow: 0 18px 50px rgba(41,31,22,.08);
        }
        h1 { margin: 0 0 8px; }
        p { color: #746556; margin-top: 0; }
        label { display:block; font-size: 14px; font-weight: 600; margin-bottom: 8px; }
        input {
            width: 100%;
            padding: 13px 14px;
            border-radius: 12px;
            border: 1px solid #d9cdbd;
            margin-bottom: 16px;
            font: inherit;
            box-sizing: border-box;
        }
        button {
            width: 100%;
            padding: 13px 16px;
            border-radius: 12px;
            border: 0;
            background: #005f73;
            color: white;
            font-weight: 700;
            cursor: pointer;
        }
        .remember { display:flex; gap:10px; align-items:center; margin-bottom:18px; color:#574b3e; }
        .remember input { width: auto; margin: 0; }
        .alert {
            padding: 14px 16px;
            border-radius: 14px;
            margin-bottom: 18px;
            background: #feeceb;
            border: 1px solid #f4c7c3;
            color: #9a2c22;
        }
        .hint { margin-top: 18px; font-size: 13px; color: #746556; }
    </style>
</head>
<body>
    <div class="panel">
        <h1>Nema ERP</h1>
        <p>Connectez-vous pour accéder au noyau de gestion de votre entreprise.</p>

        @if ($errors->any())
            <div class="alert">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('login.store') }}">
            @csrf
            <label for="email">Adresse e-mail</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>

            <label for="password">Mot de passe</label>
            <input id="password" type="password" name="password" required>

            <label class="remember" for="remember">
                <input id="remember" type="checkbox" name="remember" value="1">
                <span>Se souvenir de moi</span>
            </label>

            <button type="submit">Se connecter</button>
        </form>

        <div class="hint">Compte démo administrateur : <strong>admin@nema-erp.test</strong> / <strong>password</strong></div>
    </div>
</body>
</html>
