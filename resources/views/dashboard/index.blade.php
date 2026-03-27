@extends('layouts.app')

@section('title', 'Dashboard - Nema ERP')
@section('page-title', 'Dashboard administrateur')

@section('content')
    @if ($showOnboardingBanner && $onboarding)
        <section class="card" style="margin-bottom:20px; border-color:#d7c08b; background:linear-gradient(135deg, #fffaf0 0%, #fff4dd 100%);">
            <div style="display:flex; justify-content:space-between; gap:18px; align-items:flex-start; flex-wrap:wrap;">
                <div style="max-width:720px;">
                    <div class="badge badge-warning">Demarrage guide</div>
                    <h2 style="margin:12px 0 8px;">Le noyau ERP est pret, il reste {{ $onboarding['total'] - $onboarding['completed'] }} etape(s) pour finaliser la mise en route.</h2>
                    <div class="muted">Progression actuelle : {{ $onboarding['completed'] }}/{{ $onboarding['total'] }} etapes completees. Prochaine priorite : {{ $onboarding['next_step']['title'] ?? 'Finaliser les derniers reglages' }}.</div>
                    <div class="progress" style="margin-top:14px; max-width:520px;"><div class="progress-bar" style="width: {{ $onboarding['progress'] }}%;"></div></div>
                </div>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <a href="{{ route('onboarding.index') }}" class="button button-primary">Voir la checklist</a>
                    <form method="POST" action="{{ route('onboarding.dismiss') }}">
                        @csrf
                        <button type="submit" class="button button-secondary">Masquer</button>
                    </form>
                </div>
            </div>
        </section>
    @endif

    @if (! empty($quickLinks))
        <section class="card" style="margin-bottom:20px;">
            <div class="page-head" style="margin-bottom:14px;">
                <div>
                    <h2 style="margin:0;">Actions rapides</h2>
                    <div class="muted">Raccourcis vers les operations les plus frequentes du noyau.</div>
                </div>
            </div>
            <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
                @foreach ($quickLinks as $link)
                    <a href="{{ $link['url'] }}" class="card" style="padding:16px; display:block;">
                        <strong>{{ $link['label'] }}</strong>
                        <div class="muted" style="margin-top:8px;">{{ $link['description'] }}</div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if (! empty($operationalWatchlist))
        <section class="card" style="margin-bottom:20px;">
            <div class="page-head" style="margin-bottom:14px;">
                <div>
                    <h2 style="margin:0;">Suivi operationnel</h2>
                    <div class="muted">Les points chauds du jour, avec un clic direct vers l ecran utile.</div>
                </div>
            </div>
            <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
                @foreach ($operationalWatchlist as $item)
                    <a href="{{ $item['url'] }}" class="card" style="padding:16px; display:block;">
                        <div class="muted">{{ $item['label'] }}</div>
                        <div class="stat-value" style="margin-top:8px;">{{ number_format((float) $item['count'], 0, ',', ' ') }}</div>
                        <div class="muted" style="margin-top:8px;">{{ $item['description'] }}</div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if ($currentPeriodSummary)
        <section class="card" style="margin-bottom:20px; border-color:{{ $currentPeriodSummary['period']?->isClosed() ? '#f0c36d' : '#c7d7dc' }}; background:{{ $currentPeriodSummary['period']?->isClosed() ? 'linear-gradient(135deg, #fff7e8 0%, #fff1d6 100%)' : 'linear-gradient(135deg, #f7fbfc 0%, #eef6f8 100%)' }};">
            <div style="display:flex; justify-content:space-between; gap:18px; align-items:flex-start; flex-wrap:wrap;">
                <div style="max-width:760px;">
                    <div class="badge {{ $currentPeriodSummary['period']?->isClosed() ? 'badge-warning' : ($currentPeriodSummary['status'] === 'ready' ? 'badge-success' : 'badge-muted') }}">
                        {{ $currentPeriodSummary['period']?->isClosed() ? 'Periode cloturee' : 'Periode en cours' }}
                    </div>
                    <h2 style="margin:12px 0 8px;">{{ $currentPeriodSummary['period']?->name }} · {{ $currentPeriodSummary['start_date']->format('d/m/Y') }} au {{ $currentPeriodSummary['end_date']->format('d/m/Y') }}</h2>
                    @if ($currentPeriodSummary['period']?->isClosed())
                        <div class="muted">Les operations datees sur cette periode sont bloquees. Utilise la reouverture seulement si une correction est vraiment necessaire.</div>
                    @elseif (! $currentPeriodSummary['can_close'])
                        <div class="muted">Cloture bloquee : des documents en attente d approbation doivent etre traites avant fermeture.</div>
                    @elseif ($currentPeriodSummary['status'] === 'warning')
                        <div class="muted">Cloture possible, mais des soldes ouverts restent a suivre pour une fin de mois plus propre.</div>
                    @else
                        <div class="muted">La periode est prete pour une cloture propre.</div>
                    @endif
                </div>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <a href="{{ route('accounting.periods.index') }}" class="button button-primary">Gerer les periodes</a>
                    <a href="{{ route('reports.index') }}" class="button button-secondary">Voir les rapports</a>
                </div>
            </div>
            <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); margin-top:18px;">
                @foreach ($currentPeriodSummary['checklist'] as $item)
                    <div class="card" style="padding:16px;">
                        <div style="display:flex; justify-content:space-between; gap:12px; align-items:flex-start;">
                            <strong style="max-width:70%;">{{ $item['title'] }}</strong>
                            <span class="badge {{ $item['state'] === 'blocked' ? 'badge-warning' : ($item['state'] === 'warning' ? 'badge-muted' : 'badge-success') }}">
                                {{ $item['state'] === 'blocked' ? 'Bloquant' : ($item['state'] === 'warning' ? 'A suivre' : 'OK') }}
                            </span>
                        </div>
                        <div class="stat-value" style="font-size:24px; margin-top:8px;">{{ $item['count'] }}</div>
                        <div class="muted">{{ $item['message'] }}</div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <div class="grid stats-grid" style="margin-bottom: 20px;">
        <div class="card"><div class="muted">Entreprises</div><div class="stat-value">{{ $stats['entreprises'] }}</div></div>
        <div class="card"><div class="muted">Agences</div><div class="stat-value">{{ $stats['agences'] }}</div></div>
        <div class="card"><div class="muted">Clients</div><div class="stat-value">{{ $stats['clients'] }}</div></div>
        <div class="card"><div class="muted">Produits</div><div class="stat-value">{{ $stats['produits'] }}</div></div>
        <div class="card"><div class="muted">Ventes du mois</div><div class="stat-value">{{ number_format($stats['ventes_mois'], 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Ventes en attente</div><div class="stat-value">{{ $stats['ventes_en_attente'] }}</div></div>
        <div class="card"><div class="muted">Achats du mois</div><div class="stat-value">{{ number_format($stats['achats_mois'], 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Encaissements du mois</div><div class="stat-value">{{ number_format($stats['encaissements_mois'], 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Depenses du mois</div><div class="stat-value">{{ number_format($stats['depenses_mois'], 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Resultat du mois</div><div class="stat-value">{{ number_format($stats['resultat_mois'], 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Ecritures du mois</div><div class="stat-value">{{ $stats['ecritures_mois'] }}</div></div>
        <div class="card"><div class="muted">Alertes stock</div><div class="stat-value">{{ $stats['alertes_stock'] }}</div></div>
    </div>

    <div class="split">
        <section class="card">
            <h2 style="margin-top:0;">Lecture dirigeant</h2>
            <p class="muted">Le noyau couvre maintenant les ventes, les achats, le stock, les encaissements, les depenses et les ecritures comptables de base. Les documents en attente d approbation sont suivis a part et n impactent pas encore le stock, la tresorerie ou la comptabilite.</p>
            <div class="grid" style="grid-template-columns: repeat(2, minmax(0, 1fr)); margin-top:18px;">
                <div class="card" style="padding:16px;">
                    <strong>Recouvrement</strong>
                    <div class="muted" style="margin-top:8px;">{{ $stats['factures_impayees'] }} facture(s) client approuvee(s) encore a suivre, pour un reste global de {{ number_format($stats['reste_a_encaisser'], 0, ',', ' ') }} XOF.</div>
                    <div class="muted" style="margin-top:8px;">En attente d approbation : {{ $stats['ventes_en_attente'] }} vente(s).</div>
                </div>
                <div class="card" style="padding:16px;">
                    <strong>Fournisseurs et charges</strong>
                    <div class="muted" style="margin-top:8px;">{{ $stats['factures_fournisseurs_impayees'] }} facture(s) fournisseur ouvertes pour {{ number_format($stats['dettes_fournisseurs'], 0, ',', ' ') }} XOF, et {{ $stats['depenses_non_reglees'] }} depense(s) approuvee(s) restent a regler.</div>
                    <div class="muted" style="margin-top:8px;">En attente d approbation : {{ $stats['achats_en_attente'] }} achat(s) et {{ $stats['depenses_en_attente'] }} depense(s).</div>
                </div>
            </div>
        </section>

        <section class="card">
            <h2 style="margin-top:0;">Activite recente</h2>
            @if ($recentActivities->isEmpty())
                <p class="muted">Aucune activite enregistree pour le moment.</p>
            @else
                <div class="grid">
                    @foreach ($recentActivities as $activity)
                        <div style="padding-bottom: 12px; border-bottom: 1px solid #efe4d3;">
                            <div style="font-weight: 600;">{{ $activity->description }}</div>
                            <div class="muted" style="font-size: 14px; margin-top: 6px;">
                                {{ $activity->user?->name ?? 'Systeme' }} · {{ $activity->created_at?->format('d/m/Y H:i') }}
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
@endsection
