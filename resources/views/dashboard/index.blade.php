@extends('layouts.app')

@section('title', 'Dashboard - Nema ERP')
@section('page-title', $dashboardProfile['page_title'])

@push('page-styles')
    <style>
        .dashboard-shell {
            display: grid;
            gap: 22px;
        }
        .dashboard-banner {
            position: relative;
            overflow: hidden;
        }
        .dashboard-banner::after {
            content: "";
            position: absolute;
            inset: auto -60px -90px auto;
            width: 220px;
            height: 220px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.32);
            filter: blur(4px);
            pointer-events: none;
        }
        .dashboard-banner--warm {
            background: linear-gradient(135deg, #fffaf0 0%, #fff2da 100%);
            border-color: rgba(197, 106, 24, 0.18);
        }
        .dashboard-banner--hero {
            background: linear-gradient(135deg, rgba(255, 249, 240, 0.96) 0%, rgba(240, 248, 246, 0.96) 55%, rgba(255, 241, 221, 0.92) 100%);
            border-color: rgba(11, 79, 86, 0.12);
        }
        .dashboard-banner--period-open {
            background: linear-gradient(135deg, #f7fbfc 0%, #eef6f8 100%);
            border-color: rgba(15, 118, 110, 0.14);
        }
        .dashboard-banner--period-closed {
            background: linear-gradient(135deg, #fff7e8 0%, #fff1d6 100%);
            border-color: rgba(197, 106, 24, 0.18);
        }
        .dashboard-banner--sector {
            background: linear-gradient(135deg, rgba(239, 250, 248, 0.98) 0%, rgba(247, 252, 251, 0.96) 58%, rgba(255, 244, 227, 0.94) 100%);
            border-color: rgba(15, 118, 110, 0.18);
        }
        .dashboard-banner--premium {
            background: linear-gradient(135deg, rgba(10, 27, 44, 0.98) 0%, rgba(12, 64, 89, 0.94) 52%, rgba(179, 126, 30, 0.22) 100%);
            border-color: rgba(12, 64, 89, 0.18);
            color: #eef8f8;
        }
        .dashboard-banner--premium .dashboard-copy,
        .dashboard-banner--premium .muted,
        .dashboard-banner--premium .help {
            color: rgba(238, 248, 248, 0.78);
        }
        .dashboard-banner--premium .dashboard-chip {
            background: rgba(255, 255, 255, 0.1);
            color: #eef8f8;
            border-color: rgba(255, 255, 255, 0.14);
        }
        .dashboard-banner__layout {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: minmax(0, 1.45fr) minmax(280px, .85fr);
            gap: 20px;
            align-items: start;
        }
        .dashboard-banner__copy {
            display: grid;
            gap: 12px;
        }
        .dashboard-display {
            margin: 0;
            font-size: clamp(28px, 4vw, 42px);
            line-height: 1.04;
            letter-spacing: -.04em;
        }
        .dashboard-copy {
            margin: 0;
            font-size: 15px;
            max-width: 760px;
        }
        .dashboard-banner__aside {
            display: grid;
            gap: 14px;
        }
        .dashboard-panel {
            border: 1px solid rgba(102, 82, 56, 0.1);
            border-radius: 20px;
            padding: 16px 18px;
            background: rgba(255, 255, 255, 0.72);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.85);
        }
        .dashboard-panel--contrast {
            background: linear-gradient(135deg, rgba(11, 79, 86, 0.94) 0%, rgba(15, 118, 110, 0.88) 100%);
            color: #effaf8;
            border-color: rgba(11, 79, 86, 0.12);
        }
        .dashboard-panel--contrast .muted,
        .dashboard-panel--contrast .help {
            color: rgba(239, 250, 248, 0.78);
        }
        .dashboard-panel strong {
            display: block;
            font-size: 16px;
            margin-bottom: 8px;
        }
        .dashboard-panel p {
            margin: 0;
        }
        .dashboard-chip-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .dashboard-chip {
            border: 1px solid rgba(102, 82, 56, 0.12);
            background: rgba(255, 255, 255, 0.76);
            border-radius: 999px;
            padding: 9px 13px;
            font-size: 13px;
            font-weight: 700;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.9);
        }
        .dashboard-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        .dashboard-section-head {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: flex-start;
            margin-bottom: 16px;
        }
        .dashboard-section-head h2 {
            margin: 0;
            font-size: 24px;
            letter-spacing: -.03em;
        }
        .dashboard-section-head p {
            margin: 6px 0 0;
        }
        .dashboard-link-grid,
        .dashboard-kpi-grid,
        .dashboard-watch-grid,
        .dashboard-checklist-grid,
        .dashboard-analysis-grid {
            display: grid;
            gap: 16px;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        }
        .dashboard-link-card,
        .dashboard-watch-card,
        .dashboard-analysis-card,
        .dashboard-checklist-card,
        .dashboard-kpi-card {
            position: relative;
            display: block;
            border: 1px solid rgba(102, 82, 56, 0.1);
            border-radius: 20px;
            padding: 18px;
            background: rgba(255, 255, 255, 0.76);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.92);
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
        }
        .dashboard-link-card:hover,
        .dashboard-watch-card:hover,
        .dashboard-analysis-card:hover,
        .dashboard-checklist-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 18px 32px rgba(42, 28, 18, 0.08);
            border-color: rgba(15, 118, 110, 0.18);
        }
        .dashboard-link-card::after,
        .dashboard-watch-card::after,
        .dashboard-analysis-card::after {
            content: ">";
            position: absolute;
            right: 18px;
            top: 18px;
            color: rgba(15, 118, 110, 0.6);
            font-weight: 800;
        }
        .dashboard-link-card strong,
        .dashboard-analysis-card .stat-value,
        .dashboard-watch-card .stat-value {
            display: block;
        }
        .dashboard-link-card p,
        .dashboard-analysis-card p,
        .dashboard-watch-card p,
        .dashboard-checklist-card p {
            margin: 8px 0 0;
        }
        .dashboard-kpi-card {
            background: linear-gradient(180deg, rgba(255, 254, 250, 0.96) 0%, rgba(247, 239, 228, 0.92) 100%);
        }
        .dashboard-kpi-card .stat-value {
            margin-top: 10px;
        }
        .dashboard-kpi-card .help {
            margin-top: 8px;
        }
        .dashboard-watch-card .muted:first-child,
        .dashboard-analysis-card .muted:first-child {
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: .08em;
        }
        .dashboard-period-summary {
            display: grid;
            gap: 18px;
        }
        .dashboard-split {
            display: grid;
            gap: 20px;
            grid-template-columns: minmax(0, 1.12fr) minmax(300px, .88fr);
        }
        .dashboard-activity-list {
            display: grid;
            gap: 14px;
        }
        .dashboard-activity-item {
            padding-bottom: 14px;
            border-bottom: 1px solid rgba(102, 82, 56, 0.1);
        }
        .dashboard-activity-item:last-child {
            padding-bottom: 0;
            border-bottom: 0;
        }
        .dashboard-activity-item strong {
            display: block;
            margin-bottom: 6px;
        }
        .dashboard-empty {
            text-align: center;
            padding: 28px 18px;
            border: 1px dashed rgba(102, 82, 56, 0.18);
            border-radius: 20px;
            color: var(--muted);
            background: rgba(255, 255, 255, 0.5);
        }
        .dashboard-micro-grid {
            display: grid;
            gap: 10px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .dashboard-micro-item {
            border-radius: 16px;
            padding: 12px 14px;
            background: rgba(255, 255, 255, 0.16);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        .dashboard-micro-item span {
            display: block;
            font-size: 12px;
            letter-spacing: .08em;
            text-transform: uppercase;
            opacity: .82;
        }
        .dashboard-micro-item strong {
            margin: 8px 0 0;
            font-size: 20px;
        }
        .dashboard-premium-grid {
            display: grid;
            gap: 16px;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            margin-top: 18px;
        }
        .dashboard-premium-card {
            position: relative;
            display: block;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 20px;
            padding: 18px;
            color: #eef8f8;
            background: rgba(255, 255, 255, 0.08);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
        }
        .dashboard-premium-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 18px 32px rgba(6, 17, 28, 0.22);
            border-color: rgba(255, 255, 255, 0.22);
        }
        .dashboard-premium-card::after {
            content: ">";
            position: absolute;
            right: 18px;
            top: 18px;
            color: rgba(255, 255, 255, 0.7);
            font-weight: 800;
        }
        .dashboard-premium-card strong {
            display: block;
            margin-top: 12px;
            font-size: 18px;
            line-height: 1.2;
        }
        .dashboard-premium-card .stat-value {
            margin-top: 14px;
            color: #ffffff;
        }
        .dashboard-premium-card .muted,
        .dashboard-premium-card .help {
            color: rgba(238, 248, 248, 0.78);
        }
        .dashboard-premium-meta {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }
        .dashboard-premium-card--high {
            background: linear-gradient(180deg, rgba(163, 48, 43, 0.18) 0%, rgba(255, 255, 255, 0.08) 100%);
        }
        .dashboard-premium-card--medium {
            background: linear-gradient(180deg, rgba(197, 106, 24, 0.16) 0%, rgba(255, 255, 255, 0.08) 100%);
        }
        .dashboard-premium-card--low {
            background: linear-gradient(180deg, rgba(15, 118, 110, 0.18) 0%, rgba(255, 255, 255, 0.08) 100%);
        }
        @media (max-width: 1080px) {
            .dashboard-banner__layout,
            .dashboard-split {
                grid-template-columns: 1fr;
            }
            .dashboard-micro-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
        @media (max-width: 640px) {
            .dashboard-section-head,
            .dashboard-actions {
                flex-direction: column;
                align-items: flex-start;
            }
            .dashboard-micro-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    <div class="dashboard-shell">
        @if ($showOnboardingBanner && $onboarding)
            <section class="card dashboard-banner dashboard-banner--warm">
                <div class="dashboard-banner__layout">
                    <div class="dashboard-banner__copy">
                        <div class="badge badge-warning">Demarrage guide</div>
                        <h2 class="dashboard-display">Le noyau ERP est pret, il reste {{ $onboarding['total'] - $onboarding['completed'] }} etape(s) pour finaliser la mise en route.</h2>
                        <p class="dashboard-copy muted">Progression actuelle : {{ $onboarding['completed'] }}/{{ $onboarding['total'] }} etapes completees. Prochaine priorite : {{ $onboarding['next_step']['title'] ?? 'Finaliser les derniers reglages' }}.</p>
                        <div class="progress"><div class="progress-bar" style="width: {{ $onboarding['progress'] }}%;"></div></div>
                    </div>
                    <div class="dashboard-banner__aside">
                        <div class="dashboard-panel">
                            <strong>Checklist de demarrage</strong>
                            <p class="muted">Ferme les derniers ecarts pour avoir une base propre avant exploitation quotidienne.</p>
                        </div>
                        <div class="dashboard-actions">
                            <a href="{{ route('onboarding.index') }}" class="button button-primary">Voir la checklist</a>
                            <form method="POST" action="{{ route('onboarding.dismiss') }}">
                                @csrf
                                <button type="submit" class="button button-secondary">Masquer</button>
                            </form>
                        </div>
                    </div>
                </div>
            </section>
        @endif

        <section class="card dashboard-banner dashboard-banner--hero">
            <div class="dashboard-banner__layout">
                <div class="dashboard-banner__copy">
                    <div class="badge badge-muted">{{ $dashboardProfile['badge'] }}</div>
                    <h2 class="dashboard-display">{{ $dashboardProfile['headline'] }}</h2>
                    <p class="dashboard-copy muted">{{ $dashboardProfile['description'] }}</p>
                    <div class="dashboard-chip-row">
                        @foreach ($dashboardProfile['priorities'] as $priority)
                            <span class="dashboard-chip">{{ $priority }}</span>
                        @endforeach
                    </div>
                </div>
                <div class="dashboard-banner__aside">
                    <div class="dashboard-panel">
                        <strong>Recherche globale</strong>
                        <p class="muted">La barre de recherche en haut retrouve rapidement clients, produits, ventes, achats, paiements et documents utiles.</p>
                        <div class="help" style="margin-top:8px;">Exemples : {{ implode(' | ', $dashboardProfile['search_examples']) }}</div>
                    </div>
                    @if ($currentPeriodSummary)
                        <div class="dashboard-panel dashboard-panel--contrast">
                            <div class="badge {{ $currentPeriodSummary['period']?->isClosed() ? 'badge-warning' : ($currentPeriodSummary['status'] === 'ready' ? 'badge-success' : 'badge-muted') }}">{{ $currentPeriodSummary['period']?->isClosed() ? 'Periode cloturee' : 'Periode en cours' }}</div>
                            <strong style="margin-top:10px;">{{ $currentPeriodSummary['period']?->name }}</strong>
                            <p class="muted">{{ $currentPeriodSummary['start_date']->format('d/m/Y') }} au {{ $currentPeriodSummary['end_date']->format('d/m/Y') }}</p>
                            <div class="dashboard-micro-grid" style="margin-top:14px;">
                                <div class="dashboard-micro-item">
                                    <span>Checklist</span>
                                    <strong>{{ count($currentPeriodSummary['checklist']) }}</strong>
                                </div>
                                <div class="dashboard-micro-item">
                                    <span>Cloture</span>
                                    <strong>{{ $currentPeriodSummary['can_close'] ? 'Possible' : 'Bloquee' }}</strong>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </section>

        <section class="card dashboard-banner dashboard-banner--sector">
            <div class="dashboard-banner__layout">
                <div class="dashboard-banner__copy">
                    <div class="badge badge-success">Pack metier actif</div>
                    <h2 class="dashboard-display" style="font-size:clamp(24px, 3vw, 34px);">{{ $sectorProfile['label'] }}</h2>
                    <p class="dashboard-copy muted">{{ $sectorProfile['description'] }}</p>
                    <div class="dashboard-chip-row">
                        @foreach ($sectorProfile['use_cases'] as $useCase)
                            <span class="dashboard-chip">{{ $useCase }}</span>
                        @endforeach
                    </div>
                </div>
                <div class="dashboard-banner__aside">
                    <div class="dashboard-panel">
                        <strong>Ce que Nema ERP privilegie</strong>
                        <p class="muted">{{ implode(' · ', $sectorProfile['operational_focus']) }}</p>
                        <div class="help" style="margin-top:8px;">Catalogue de depart : {{ implode(' · ', $sectorProfile['starter_catalog']) }}</div>
                    </div>
                    <div class="dashboard-panel">
                        <strong>Reglages conseilles</strong>
                        <p class="muted">Unites : {{ implode(' · ', $sectorProfile['recommended_units']) }}</p>
                        <p class="muted" style="margin-top:8px;">Paiements terrain : {{ implode(' · ', $sectorProfile['recommended_payments']) }}</p>
                        @if (auth()->user()?->hasPermission('settings.view'))
                            <div class="dashboard-actions" style="margin-top:12px;">
                                <a href="{{ route('settings.index') }}" class="button button-secondary">Ajuster le profil</a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            @if (! empty($sectorActionPlan))
                <div style="margin-top:18px;">
                    <div class="dashboard-section-head" style="margin-bottom:14px;">
                        <div>
                            <h2 style="font-size:22px;">Modules recommandes pour ce secteur</h2>
                            <p class="muted">Raccourcis priorises selon le pack metier actif.</p>
                        </div>
                    </div>
                    <div class="dashboard-link-grid">
                        @foreach ($sectorActionPlan as $action)
                            <a href="{{ $action['url'] }}" class="dashboard-link-card">
                                <strong>{{ $action['label'] }}</strong>
                                <p class="muted">{{ $action['description'] }}</p>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </section>


        @if (! empty($premiumActionCenter))
            <section class="card dashboard-banner dashboard-banner--premium">
                <div class="dashboard-banner__layout">
                    <div class="dashboard-banner__copy">
                        <div class="badge badge-success">Centre d actions premium</div>
                        <h2 class="dashboard-display" style="font-size:clamp(24px, 3vw, 34px);">{{ $premiumBrief['headline'] }}</h2>
                        <p class="dashboard-copy">{{ $premiumBrief['description'] }}</p>
                        @if (! empty($premiumBrief['focus']))
                            <div class="dashboard-chip-row">
                                @foreach (explode(' | ', $premiumBrief['focus']) as $focus)
                                    <span class="dashboard-chip">{{ $focus }}</span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <div class="dashboard-banner__aside">
                        <div class="dashboard-panel">
                            <strong>Pourquoi c est premium</strong>
                            <p class="muted">Nema ERP remonte ici les actions qui changent vraiment le pilotage : cash, execution, cloture, approvisionnement et sante technique.</p>
                        </div>
                        <div class="dashboard-panel">
                            <strong>Lecture express</strong>
                            <p class="muted">Chaque carte pointe un levier concret a lancer, pas juste un chiffre a contempler.</p>
                        </div>
                    </div>
                </div>
                <div class="dashboard-premium-grid">
                    @foreach ($premiumActionCenter as $item)
                        <a href="{{ $item['url'] }}" class="dashboard-premium-card dashboard-premium-card--{{ $item['priority'] }}">
                            <div class="dashboard-premium-meta">
                                <span class="badge {{ $item['priority'] === 'high' ? 'badge-warning' : ($item['priority'] === 'medium' ? 'badge-muted' : 'badge-success') }}">
                                    {{ $item['priority'] === 'high' ? 'Priorite immediate' : ($item['priority'] === 'medium' ? 'Levier rapide' : 'Sous controle') }}
                                </span>
                                <span class="muted">{{ $item['eyebrow'] }}</span>
                            </div>
                            <strong>{{ $item['label'] }}</strong>
                            <div class="stat-value" style="font-size:32px;">{{ $item['metric'] }}</div>
                            <p class="muted">{{ $item['description'] }}</p>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        @if (! empty($executiveBrief['items']))
            <section class="card">
                <div class="dashboard-section-head">
                    <div>
                        <h2>Briefing dirigeant</h2>
                        <p class="muted">{{ $executiveBrief['headline'] }}</p>
                    </div>
                    @if (! empty($executiveBrief['summary']))
                        <div class="dashboard-chip-row">
                            @foreach (explode(' | ', $executiveBrief['summary']) as $focus)
                                <span class="dashboard-chip">{{ $focus }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>
                <div class="dashboard-analysis-grid">
                    @foreach ($executiveBrief['items'] as $item)
                        <a href="{{ $item['action_url'] }}" class="dashboard-analysis-card">
                            <div class="dashboard-chip-row" style="margin-bottom:10px;">
                                <span class="badge {{ $item['tone'] === 'danger' ? 'badge-warning' : ($item['tone'] === 'warning' ? 'badge-muted' : 'badge-success') }}">{{ strtoupper($item['tone']) }}</span>
                            </div>
                            <strong>{{ $item['title'] }}</strong>
                            <p class="muted">{{ $item['message'] }}</p>
                            <div class="help" style="margin-top:10px;">{{ $item['action_label'] }}</div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
        @if (! empty($roleActionPlan))
            <section class="card">
                <div class="dashboard-section-head">
                    <div>
                        <h2>{{ $dashboardProfile['focus_title'] }}</h2>
                        <p class="muted">{{ $dashboardProfile['focus_description'] }}</p>
                    </div>
                </div>
                <div class="dashboard-link-grid">
                    @foreach ($roleActionPlan as $action)
                        <a href="{{ $action['url'] }}" class="dashboard-link-card">
                            <strong>{{ $action['label'] }}</strong>
                            <p class="muted">{{ $action['description'] }}</p>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        @if (! empty($quickLinks))
            <section class="card">
                <div class="dashboard-section-head">
                    <div>
                        <h2>Actions rapides</h2>
                        <p class="muted">Raccourcis vers les operations les plus frequentes du noyau.</p>
                    </div>
                </div>
                <div class="dashboard-link-grid">
                    @foreach ($quickLinks as $link)
                        <a href="{{ $link['url'] }}" class="dashboard-link-card">
                            <strong>{{ $link['label'] }}</strong>
                            <p class="muted">{{ $link['description'] }}</p>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        @if (! empty($dashboardKpis))
            <section class="dashboard-kpi-grid">
                @foreach ($dashboardKpis as $kpi)
                    <article class="dashboard-kpi-card">
                        <div class="muted">{{ $kpi['label'] }}</div>
                        <div class="stat-value">{{ $kpi['value'] }}</div>
                        <div class="help">{{ $kpi['description'] }}</div>
                    </article>
                @endforeach
            </section>
        @endif

        @if (! empty($operationalWatchlist))
            <section class="card">
                <div class="dashboard-section-head">
                    <div>
                        <h2>Suivi operationnel</h2>
                        <p class="muted">Les points chauds du jour, avec un clic direct vers l ecran utile.</p>
                    </div>
                </div>
                <div class="dashboard-watch-grid">
                    @foreach ($operationalWatchlist as $item)
                        <a href="{{ $item['url'] }}" class="dashboard-watch-card">
                            <div class="muted">{{ $item['label'] }}</div>
                            <div class="stat-value">{{ number_format((float) $item['count'], 0, ',', ' ') }}</div>
                            <p class="muted">{{ $item['description'] }}</p>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($currentPeriodSummary)
            <section class="card dashboard-banner {{ $currentPeriodSummary['period']?->isClosed() ? 'dashboard-banner--period-closed' : 'dashboard-banner--period-open' }}">
                <div class="dashboard-period-summary">
                    <div class="dashboard-banner__layout">
                        <div class="dashboard-banner__copy">
                            <div class="badge {{ $currentPeriodSummary['period']?->isClosed() ? 'badge-warning' : ($currentPeriodSummary['status'] === 'ready' ? 'badge-success' : 'badge-muted') }}">{{ $currentPeriodSummary['period']?->isClosed() ? 'Periode cloturee' : 'Periode en cours' }}</div>
                            <h2 style="margin:0; font-size:30px; letter-spacing:-.03em;">{{ $currentPeriodSummary['period']?->name }} | {{ $currentPeriodSummary['start_date']->format('d/m/Y') }} au {{ $currentPeriodSummary['end_date']->format('d/m/Y') }}</h2>
                            @if ($currentPeriodSummary['period']?->isClosed())
                                <p class="dashboard-copy muted">Les operations datees sur cette periode sont bloquees. Utilise la reouverture seulement si une correction est vraiment necessaire.</p>
                            @elseif (! $currentPeriodSummary['can_close'])
                                <p class="dashboard-copy muted">Cloture bloquee : des documents en attente d approbation doivent etre traites avant fermeture.</p>
                            @elseif ($currentPeriodSummary['status'] === 'warning')
                                <p class="dashboard-copy muted">Cloture possible, mais des soldes ouverts restent a suivre pour une fin de mois plus propre.</p>
                            @else
                                <p class="dashboard-copy muted">La periode est prete pour une cloture propre.</p>
                            @endif
                        </div>
                        <div class="dashboard-actions" style="justify-content:flex-end; align-items:flex-start;">
                            <a href="{{ route('accounting.periods.index') }}" class="button button-primary">Gerer les periodes</a>
                            <a href="{{ route('reports.index') }}" class="button button-secondary">Voir les rapports</a>
                        </div>
                    </div>
                    <div class="dashboard-checklist-grid">
                        @foreach ($currentPeriodSummary['checklist'] as $item)
                            <div class="dashboard-checklist-card">
                                <div style="display:flex; justify-content:space-between; gap:12px; align-items:flex-start;">
                                    <strong style="max-width:72%;">{{ $item['title'] }}</strong>
                                    <span class="badge {{ $item['state'] === 'blocked' ? 'badge-warning' : ($item['state'] === 'warning' ? 'badge-muted' : 'badge-success') }}">{{ $item['state'] === 'blocked' ? 'Bloquant' : ($item['state'] === 'warning' ? 'A suivre' : 'OK') }}</span>
                                </div>
                                <div class="stat-value" style="font-size:26px;">{{ $item['count'] }}</div>
                                <p class="muted">{{ $item['message'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        <div class="dashboard-split">
            <section class="card">
                <div class="dashboard-section-head">
                    <div>
                        <h2>{{ $dashboardProfile['analysis_title'] }}</h2>
                        <p class="muted">{{ $dashboardProfile['analysis_description'] }}</p>
                    </div>
                </div>
                <div class="dashboard-analysis-grid">
                    @foreach ($roleSpotlight as $item)
                        <a href="{{ $item['url'] }}" class="dashboard-analysis-card">
                            <div class="muted">{{ $item['label'] }}</div>
                            <div class="stat-value" style="font-size:30px;">{{ $item['value'] }}</div>
                            <p class="muted">{{ $item['description'] }}</p>
                        </a>
                    @endforeach
                </div>
            </section>

            <section class="card">
                <div class="dashboard-section-head">
                    <div>
                        <h2>Activite recente</h2>
                        <p class="muted">Les derniers mouvements qui merite ton attention.</p>
                    </div>
                </div>
                @if ($recentActivities->isEmpty())
                    <div class="dashboard-empty">Aucune activite enregistree pour le moment.</div>
                @else
                    <div class="dashboard-activity-list">
                        @foreach ($recentActivities as $activity)
                            <div class="dashboard-activity-item">
                                <strong>{{ $activity->description }}</strong>
                                <div class="muted">{{ $activity->user?->name ?? 'Systeme' }} | {{ $activity->created_at?->format('d/m/Y H:i') }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </div>
@endsection







