@extends('layouts.app')

@section('title', 'Demarrage - Nema ERP')
@section('page-title', 'Parcours de demarrage')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">Checklist de mise en route</h2>
            <div class="muted">Cette page aide l entreprise a passer d une installation ERP a une utilisation quotidienne sans oublier les prealables.</div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            @if ($bannerDismissed)
                <form method="POST" action="{{ route('onboarding.reopen') }}">
                    @csrf
                    <button type="submit" class="button button-secondary">Reafficher le bandeau</button>
                </form>
            @else
                <form method="POST" action="{{ route('onboarding.dismiss') }}">
                    @csrf
                    <button type="submit" class="button button-secondary">Masquer le bandeau dashboard</button>
                </form>
            @endif
        </div>
    </div>

    <section class="card" style="margin-bottom:20px;">
        <div style="display:flex; justify-content:space-between; gap:16px; align-items:flex-start; flex-wrap:wrap;">
            <div>
                <div class="muted">Progression</div>
                <div class="stat-value" style="margin-top:6px;">{{ $summary['completed'] }}/{{ $summary['total'] }}</div>
                <div class="muted">{{ $summary['progress'] }}% des etapes sont terminees pour {{ $company->name }}.</div>
            </div>
            <div style="min-width:260px; flex:1; max-width:420px;">
                <div class="progress"><div class="progress-bar" style="width: {{ $summary['progress'] }}%;"></div></div>
                @if ($summary['is_complete'])
                    <div class="badge badge-success" style="margin-top:12px;">Societe prete a operer</div>
                @elseif ($summary['next_step'])
                    <div class="badge badge-warning" style="margin-top:12px;">Prochaine priorite : {{ $summary['next_step']['title'] }}</div>
                @endif
            </div>
        </div>
    </section>

    <section class="card" style="margin-bottom:20px;">
        <h2 style="margin-top:0;">Parcours recommande</h2>
        <div class="grid" style="grid-template-columns: repeat(4, minmax(0, 1fr));">
            <div class="card" style="padding:16px;">
                <strong>1. Importer les tiers</strong>
                <div class="muted" style="margin-top:8px;">Charge les clients et fournisseurs pour eviter la ressaisie.</div>
                @allowed('imports.manage')
                    <div style="margin-top:12px;"><a href="{{ route('imports.index') }}" class="button button-secondary">Ouvrir les imports</a></div>
                @endallowed
            </div>
            <div class="card" style="padding:16px;">
                <strong>2. Charger le catalogue</strong>
                <div class="muted" style="margin-top:8px;">Importe les produits puis verifie les categories et prix.</div>
                <div style="margin-top:12px;"><a href="{{ route('products.index') }}" class="button button-secondary">Voir les produits</a></div>
            </div>
            <div class="card" style="padding:16px;">
                <strong>3. Initialiser le stock</strong>
                <div class="muted" style="margin-top:8px;">Utilise l import en lot ou la saisie unitaire selon ton volume.</div>
                <div style="margin-top:12px;"><a href="{{ route('stock.opening.create') }}" class="button button-secondary">Initialiser le stock</a></div>
            </div>
            <div class="card" style="padding:16px;">
                <strong>4. Lancer l activite</strong>
                <div class="muted" style="margin-top:8px;">Passe la premiere vente ou le premier achat puis controle les rapports.</div>
                <div style="margin-top:12px;"><a href="{{ route('reports.index') }}" class="button button-secondary">Voir les rapports</a></div>
            </div>
        </div>
    </section>

    @allowed('imports.manage')
        <section class="card" style="margin-bottom:20px; border-color:#d7c08b; background:linear-gradient(135deg, #fffaf0 0%, #fff4dd 100%);">
            <div style="display:flex; justify-content:space-between; gap:18px; align-items:flex-start; flex-wrap:wrap;">
                <div style="max-width:760px;">
                    <div class="badge badge-warning">Onboarding de donnees</div>
                    <h2 style="margin:12px 0 8px;">Charge rapidement tes donnees de base avant le stock initial.</h2>
                    <div class="muted">Le centre d imports fournit les modeles CSV pour les clients, fournisseurs, produits et stock initial.</div>
                </div>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <a href="{{ route('imports.index') }}" class="button button-primary">Ouvrir le centre d imports</a>
                    <a href="{{ route('imports.templates.download', 'customers') }}" class="button button-secondary">Modele clients</a>
                    <a href="{{ route('imports.templates.download', 'suppliers') }}" class="button button-secondary">Modele fournisseurs</a>
                    <a href="{{ route('imports.templates.download', 'products') }}" class="button button-secondary">Modele produits</a>
                    <a href="{{ route('imports.templates.download', 'opening-stock') }}" class="button button-secondary">Modele stock</a>
                </div>
            </div>
        </section>
    @endallowed

    <section class="grid">
        @foreach ($summary['steps'] as $step)
            <div class="card" style="padding:18px;">
                <div style="display:flex; justify-content:space-between; gap:16px; align-items:flex-start; flex-wrap:wrap;">
                    <div style="flex:1; min-width:280px;">
                        <div style="display:flex; align-items:center; gap:10px; margin-bottom:8px;">
                            <strong>{{ $step['title'] }}</strong>
                            @if ($step['completed'])
                                <span class="badge badge-success">Terminee</span>
                            @else
                                <span class="badge badge-warning">A faire</span>
                            @endif
                        </div>
                        <div class="muted">{{ $step['description'] }}</div>
                        <div class="help" style="margin-top:10px;">Etat actuel : {{ $step['metric'] }}</div>
                    </div>
                    <div>
                        <a href="{{ $step['route'] }}" class="button {{ $step['completed'] ? 'button-secondary' : 'button-primary' }}">{{ $step['action'] }}</a>
                    </div>
                </div>
            </div>
        @endforeach
    </section>
@endsection
