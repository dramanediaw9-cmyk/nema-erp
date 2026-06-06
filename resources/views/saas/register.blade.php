<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light">
    <title>Créer votre espace - Nema ERP</title>
    <style>
        :root { --ink:#17211f; --muted:#63706d; --line:#d7e0dd; --soft:#f4f7f6; --brand:#0f766e; --brand-dark:#0a4f49; --gold:#e9ad35; --danger:#a52a24; --white:#fff; }
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; color:var(--ink); background:var(--soft); font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; }
        a { color:inherit; }
        .topbar { min-height:62px; padding:0 24px; display:flex; align-items:center; justify-content:space-between; gap:18px; background:var(--white); border-bottom:1px solid var(--line); }
        .brand { display:inline-flex; align-items:center; gap:10px; text-decoration:none; font-weight:900; }
        .mark { width:34px; height:34px; display:grid; place-items:center; color:var(--white); background:var(--brand); border-radius:7px; }
        .login-link { color:var(--brand-dark); font-size:14px; font-weight:800; text-decoration:none; }
        .workspace { width:min(1120px,calc(100% - 32px)); min-height:calc(100vh - 94px); margin:16px auto; display:grid; grid-template-columns:290px minmax(0,1fr); background:var(--white); border:1px solid var(--line); border-radius:8px; overflow:hidden; }
        .side { padding:28px 22px; color:var(--white); background:#173d39; }
        .side h1 { margin:0 0 10px; font-size:25px; line-height:1.18; letter-spacing:0; }
        .side p { margin:0 0 28px; color:#c9dcda; font-size:14px; line-height:1.55; }
        .steps { display:grid; gap:8px; }
        .step { display:grid; grid-template-columns:30px 1fr; align-items:center; gap:10px; min-height:48px; padding:7px 9px; border:1px solid transparent; border-radius:7px; color:#b8cecb; }
        .step strong { display:block; color:inherit; font-size:14px; }
        .step span:last-child { display:block; margin-top:2px; font-size:12px; }
        .step-number { width:28px; height:28px; display:grid; place-items:center; border:1px solid #6e918d; border-radius:50%; font-size:12px; font-weight:900; }
        .step.active { color:var(--white); background:rgba(255,255,255,.1); border-color:rgba(255,255,255,.16); }
        .step.active .step-number { color:#173d39; background:var(--gold); border-color:var(--gold); }
        .content { padding:30px clamp(24px,5vw,64px); display:flex; flex-direction:column; }
        .content-head { margin-bottom:22px; padding-bottom:18px; border-bottom:1px solid var(--line); }
        .eyebrow { margin-bottom:6px; color:var(--brand); font-size:13px; font-weight:900; text-transform:uppercase; }
        h2 { margin:0 0 7px; font-size:clamp(25px,4vw,34px); line-height:1.15; letter-spacing:0; }
        .intro { margin:0; color:var(--muted); line-height:1.55; }
        .alert { margin-bottom:18px; padding:12px 14px; color:var(--danger); background:#fff1f0; border:1px solid #efc4c0; border-radius:7px; font-size:14px; }
        .form-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px 18px; }
        .field.full { grid-column:1/-1; }
        label { display:block; margin-bottom:7px; font-size:14px; font-weight:800; }
        input,select,textarea { width:100%; min-height:44px; padding:10px 12px; color:var(--ink); background:var(--white); border:1px solid #bccac6; border-radius:6px; font:inherit; }
        textarea { min-height:86px; resize:vertical; }
        input:focus,select:focus,textarea:focus { outline:3px solid rgba(15,118,110,.14); border-color:var(--brand); }
        .help { margin-top:5px; color:var(--muted); font-size:12px; line-height:1.4; }
        .plans { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; }
        .plan { position:relative; display:block; min-height:190px; margin:0; padding:16px; border:1px solid var(--line); border-radius:7px; cursor:pointer; }
        .plan:has(input:checked) { border-color:var(--brand); box-shadow:inset 0 0 0 1px var(--brand); background:#f1faf8; }
        .plan input { width:18px; min-height:18px; margin:0 0 15px; accent-color:var(--brand); }
        .plan-title { display:block; margin-bottom:6px; font-size:19px; font-weight:900; }
        .plan-summary { display:block; min-height:58px; color:var(--muted); font-size:13px; line-height:1.45; }
        .plan-limits { display:block; margin-top:12px; padding-top:10px; color:var(--brand-dark); border-top:1px solid var(--line); font-size:13px; font-weight:850; }
        .recommended { position:absolute; top:12px; right:12px; padding:4px 7px; color:#6a4606; background:#fff0ca; border-radius:5px; font-size:10px; font-weight:900; text-transform:uppercase; }
        .terms { display:flex; align-items:flex-start; gap:9px; margin-top:18px; color:#43504d; font-size:13px; line-height:1.5; }
        .terms input { width:18px; min-height:18px; margin-top:1px; accent-color:var(--brand); }
        .honeypot { position:absolute; left:-10000px; width:1px; height:1px; overflow:hidden; }
        .actions { margin-top:auto; padding-top:26px; display:flex; align-items:center; justify-content:space-between; gap:12px; }
        .button { min-height:44px; display:inline-flex; align-items:center; justify-content:center; padding:10px 17px; border:1px solid transparent; border-radius:7px; font:inherit; font-weight:900; text-decoration:none; cursor:pointer; }
        .button-primary { color:var(--white); background:var(--brand); }
        .button-secondary { color:var(--brand-dark); background:var(--white); border-color:var(--line); }
        @media (max-width:820px) {
            .workspace { grid-template-columns:1fr; }
            .side { padding:18px; }
            .side h1,.side p { display:none; }
            .steps { grid-template-columns:repeat(4,minmax(0,1fr)); }
            .step { grid-template-columns:1fr; justify-items:center; text-align:center; padding:6px; }
            .step span:last-child { display:none; }
            .content { padding:24px 20px; }
            .plans { grid-template-columns:1fr; }
            .plan { min-height:auto; }
            .plan-summary { min-height:auto; }
        }
        @media (max-width:560px) {
            .topbar { padding:0 16px; }
            .workspace { width:100%; margin:0; min-height:calc(100vh - 62px); border:0; border-radius:0; }
            .form-grid { grid-template-columns:1fr; }
            .field.full { grid-column:auto; }
            .actions { align-items:stretch; flex-direction:column-reverse; }
            .button { width:100%; }
        }
    </style>
</head>
<body>
    @php
        $steps = [
            'account' => ['Compte', 'Votre accès sécurisé'],
            'company' => ['Entreprise', 'Votre identité légale'],
            'workspace' => ['Espace métier', 'Votre organisation'],
            'plan' => ['Formule', 'Votre capacité'],
        ];
        $stepNumber = array_search($step, array_keys($steps), true) + 1;
    @endphp

    <header class="topbar">
        <a class="brand" href="{{ route('entry.index') }}"><span class="mark">N</span><span>Nema Technologies</span></a>
        <a class="login-link" href="{{ route('login') }}">J’ai déjà un espace</a>
    </header>

    <main class="workspace">
        <aside class="side">
            <h1>Votre entreprise, prête avant la première vente.</h1>
            <p>Quatre étapes courtes pour créer un environnement Nema séparé et configuré pour votre activité.</p>
            <div class="steps" aria-label="Progression">
                @foreach ($steps as $key => [$label, $description])
                    <div class="step {{ $step === $key ? 'active' : '' }}">
                        <span class="step-number">{{ $loop->iteration }}</span>
                        <span><strong>{{ $label }}</strong><span>{{ $description }}</span></span>
                    </div>
                @endforeach
            </div>
        </aside>

        <section class="content">
            <div class="content-head">
                <div class="eyebrow">Étape {{ $stepNumber }} sur 4</div>
                @if ($step === 'account')
                    <h2>Créons votre compte administrateur</h2><p class="intro">Ce compte sera le responsable principal de votre espace Nema.</p>
                @elseif ($step === 'company')
                    <h2>Présentez votre entreprise</h2><p class="intro">Ces informations serviront aux documents et réglages de base.</p>
                @elseif ($step === 'workspace')
                    <h2>Préparons votre espace métier</h2><p class="intro">Nema adaptera le démarrage à votre secteur et à votre agence principale.</p>
                @else
                    <h2>Choisissez votre capacité de départ</h2><p class="intro">Toutes les formules commencent par 14 jours d’essai. Aucun paiement n’est demandé maintenant.</p>
                @endif
            </div>

            @if ($errors->any())
                <div class="alert" role="alert">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
            @endif

            @if ($step === 'account')
                <form method="POST" action="{{ route('saas.register.account.store') }}">
                    @csrf
                    <div class="form-grid">
                        <div class="field full"><label for="name">Nom complet</label><input id="name" name="name" value="{{ old('name', data_get($registration, 'account.name')) }}" required autofocus autocomplete="name"></div>
                        <div class="field"><label for="email">Adresse e-mail</label><input id="email" type="email" name="email" value="{{ old('email', data_get($registration, 'account.email')) }}" required autocomplete="email"></div>
                        <div class="field"><label for="phone">Téléphone</label><input id="phone" name="phone" value="{{ old('phone', data_get($registration, 'account.phone')) }}" autocomplete="tel"></div>
                        <div class="field"><label for="password">Mot de passe</label><input id="password" type="password" name="password" required minlength="8" autocomplete="new-password"><div class="help">Au moins 8 caractères.</div></div>
                        <div class="field"><label for="password_confirmation">Confirmer le mot de passe</label><input id="password_confirmation" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password"></div>
                    </div>
                    <div class="actions"><a class="button button-secondary" href="{{ route('entry.index') }}">Retour</a><button class="button button-primary" type="submit">Continuer</button></div>
                </form>
            @elseif ($step === 'company')
                <form method="POST" action="{{ route('saas.register.company.store') }}">
                    @csrf
                    <div class="form-grid">
                        <div class="field"><label for="name">Nom commercial</label><input id="name" name="name" value="{{ old('name', data_get($registration, 'company.name')) }}" required autofocus autocomplete="organization"></div>
                        <div class="field"><label for="legal_name">Raison sociale</label><input id="legal_name" name="legal_name" value="{{ old('legal_name', data_get($registration, 'company.legal_name')) }}"></div>
                        <div class="field"><label for="phone">Téléphone de l’entreprise</label><input id="phone" name="phone" value="{{ old('phone', data_get($registration, 'company.phone')) }}" autocomplete="tel"></div>
                        <div class="field"><label for="email">E-mail professionnel</label><input id="email" type="email" name="email" value="{{ old('email', data_get($registration, 'company.email')) }}" autocomplete="email"></div>
                        <div class="field full"><label for="address">Adresse</label><textarea id="address" name="address" autocomplete="street-address">{{ old('address', data_get($registration, 'company.address')) }}</textarea></div>
                    </div>
                    <div class="actions"><a class="button button-secondary" href="{{ route('saas.register.account') }}">Retour</a><button class="button button-primary" type="submit">Continuer</button></div>
                </form>
            @elseif ($step === 'workspace')
                <form method="POST" action="{{ route('saas.register.workspace.store') }}">
                    @csrf
                    <div class="form-grid">
                        <div class="field full"><label for="slug">Identifiant de votre espace</label><input id="slug" name="slug" value="{{ old('slug', data_get($registration, 'workspace.slug')) }}" required autofocus><div class="help">Identifiant unique, sans espace. Exemple : nema-bamako.</div></div>
                        <div class="field full"><label for="sector_profile">Secteur d’activité</label><select id="sector_profile" name="sector_profile" required>@foreach ($profiles as $profile)<option value="{{ $profile['key'] }}" @selected(old('sector_profile', data_get($registration, 'workspace.sector_profile', 'general_trade')) === $profile['key'])>{{ $profile['label'] }}</option>@endforeach</select></div>
                        <div class="field"><label for="branch_name">Agence principale</label><input id="branch_name" name="branch_name" value="{{ old('branch_name', data_get($registration, 'workspace.branch_name', 'Siège')) }}" required></div>
                        <div class="field"><label for="city">Ville</label><input id="city" name="city" value="{{ old('city', data_get($registration, 'workspace.city', 'Bamako')) }}" autocomplete="address-level2"></div>
                    </div>
                    <div class="actions"><a class="button button-secondary" href="{{ route('saas.register.company') }}">Retour</a><button class="button button-primary" type="submit">Continuer</button></div>
                </form>
            @else
                <form method="POST" action="{{ route('saas.register.complete') }}">
                    @csrf
                    <div class="plans">
                        @foreach ($plans as $key => $plan)
                            <label class="plan">
                                <input type="radio" name="plan" value="{{ $key }}" @checked(old('plan', 'growth') === $key)>
                                @if ($plan['recommended'] ?? false)<span class="recommended">Recommandé</span>@endif
                                <span class="plan-title">{{ $plan['label'] }}</span><span class="plan-summary">{{ $plan['summary'] }}</span><span class="plan-limits">{{ $plan['users'] }} utilisateurs · {{ $plan['branches'] }} agences</span>
                            </label>
                        @endforeach
                    </div>
                    <label class="terms" for="terms"><input id="terms" type="checkbox" name="terms" value="1" required @checked(old('terms'))><span>J’accepte les conditions d’utilisation et la création d’un espace d’essai de 14 jours.</span></label>
                    <div class="honeypot" aria-hidden="true"><label for="website">Site web</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
                    <div class="actions"><a class="button button-secondary" href="{{ route('saas.register.workspace') }}">Retour</a><button class="button button-primary" type="submit">Créer mon espace Nema</button></div>
                </form>
            @endif
        </section>
    </main>
</body>
</html>
