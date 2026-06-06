@extends('layouts.app')

@section('title', 'Mon compte - Nema ERP')
@section('page-title', 'Mon compte')

@section('content')
    @php
        $roleNames = $accountUser->roles->pluck('name')->filter()->join(', ');
    @endphp

    <div class="stats-grid" style="margin-bottom:16px;">
        <div class="card">
            <div class="muted">Entreprise</div>
            <div style="font-weight:800; margin-top:4px;">{{ $accountUser->company?->name ?? 'Non attribuée' }}</div>
        </div>
        <div class="card">
            <div class="muted">Agence</div>
            <div style="font-weight:800; margin-top:4px;">{{ $accountUser->branch?->name ?? 'Toutes les agences' }}</div>
        </div>
        <div class="card">
            <div class="muted">Rôle</div>
            <div style="font-weight:800; margin-top:4px;">{{ $roleNames ?: 'Utilisateur' }}</div>
        </div>
        <div class="card">
            <div class="muted">Dernière connexion</div>
            <div style="font-weight:800; margin-top:4px;">
                {{ $accountUser->last_login_at?->format('d/m/Y à H:i') ?? 'Première connexion' }}
            </div>
        </div>
    </div>

    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:16px; align-items:start;">
        <form method="POST" action="{{ route('account.profile.update') }}" class="card">
            @csrf
            @method('PUT')

            <div style="margin-bottom:16px;">
                <h2 style="margin:0 0 4px; font-size:18px;">Informations personnelles</h2>
                <div class="muted">Ces informations identifient votre compte dans Nema.</div>
            </div>

            <div class="form-grid" style="grid-template-columns:1fr;">
                <div>
                    <label for="name">Nom complet</label>
                    <input id="name" type="text" name="name" value="{{ old('name', $accountUser->name) }}" autocomplete="name" required>
                </div>
                <div>
                    <label for="email">Adresse e-mail</label>
                    <input id="email" type="email" name="email" value="{{ old('email', $accountUser->email) }}" autocomplete="email" required>
                </div>
                <div>
                    <label for="phone">Téléphone</label>
                    <input id="phone" type="text" name="phone" value="{{ old('phone', $accountUser->phone) }}" autocomplete="tel">
                </div>
            </div>

            <div class="actions">
                <button type="submit" class="button button-primary">Enregistrer</button>
            </div>
        </form>

        <form method="POST" action="{{ route('account.profile.password') }}" class="card">
            @csrf
            @method('PUT')

            <div style="margin-bottom:16px;">
                <h2 style="margin:0 0 4px; font-size:18px;">Sécurité</h2>
                <div class="muted">Utilisez au moins 10 caractères avec majuscule, chiffre et symbole.</div>
            </div>

            <div class="form-grid" style="grid-template-columns:1fr;">
                <div>
                    <label for="current_password">Mot de passe actuel</label>
                    <input id="current_password" type="password" name="current_password" autocomplete="current-password" required>
                </div>
                <div>
                    <label for="password">Nouveau mot de passe</label>
                    <input id="password" type="password" name="password" autocomplete="new-password" required>
                </div>
                <div>
                    <label for="password_confirmation">Confirmer le nouveau mot de passe</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required>
                </div>
            </div>

            <div class="actions">
                <button type="submit" class="button button-primary">Changer le mot de passe</button>
            </div>
        </form>
    </div>
@endsection
