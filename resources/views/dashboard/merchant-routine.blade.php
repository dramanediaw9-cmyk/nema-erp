@extends('layouts.app')

@section('title', 'Routine commerce - Nema ERP')
@section('page-title', 'Routine commerce')

@push('page-styles')
    <style>
        .routine-shell {
            display: grid;
            gap: 22px;
        }
        .routine-hero {
            display: grid;
            grid-template-columns: minmax(0, 1.25fr) minmax(280px, .75fr);
            gap: 20px;
            padding: 24px 26px;
            border-radius: 26px;
            color: #fff;
            background: linear-gradient(135deg, #0f2e48 0%, #13646d 55%, #d7a64d 140%);
            box-shadow: 0 22px 46px rgba(15, 46, 72, 0.22);
        }
        .routine-hero h2 {
            margin: 0;
            font-size: clamp(28px, 4vw, 40px);
            letter-spacing: -.04em;
        }
        .routine-hero .muted,
        .routine-hero .help {
            color: rgba(255, 255, 255, .78);
        }
        .routine-hero .button-secondary {
            background: rgba(255, 255, 255, .12);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, .22);
        }
        .routine-badge-row,
        .routine-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        .routine-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 12px;
            border-radius: 999px;
            background: rgba(255, 255, 255, .12);
            border: 1px solid rgba(255, 255, 255, .18);
            font-size: 13px;
            font-weight: 700;
        }
        .routine-side-card {
            border-radius: 22px;
            padding: 18px;
            background: rgba(255, 255, 255, .1);
            border: 1px solid rgba(255, 255, 255, .14);
            display: grid;
            gap: 12px;
            align-content: start;
        }
        .routine-side-card strong {
            display: block;
            font-size: 18px;
        }
        .routine-side-grid,
        .routine-kpis,
        .routine-steps,
        .routine-focus-grid,
        .routine-ticket-list {
            display: grid;
            gap: 16px;
        }
        .routine-side-grid,
        .routine-kpis,
        .routine-focus-grid {
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        }
        .routine-kpi,
        .routine-focus-card,
        .routine-step-card,
        .routine-ticket-card,
        .routine-session-card {
            border: 1px solid rgba(102, 82, 56, .1);
            border-radius: 22px;
            padding: 18px;
            background: rgba(255, 255, 255, .82);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, .92);
        }
        .routine-kpi .label,
        .routine-focus-card .label,
        .routine-ticket-card .label,
        .routine-session-card .label {
            color: #6f8397;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .routine-kpi .value,
        .routine-focus-card .value,
        .routine-session-card .value {
            margin-top: 10px;
            font-size: 30px;
            font-weight: 800;
            letter-spacing: -.04em;
            color: #13263d;
        }
        .routine-section-head {
            display: flex;
            justify-content: space-between;
            gap: 14px;
            align-items: flex-start;
            margin-bottom: 16px;
        }
        .routine-section-head h3 {
            margin: 0;
            font-size: 24px;
            letter-spacing: -.03em;
        }
        .routine-layout {
            display: grid;
            gap: 20px;
            grid-template-columns: minmax(0, 1.15fr) minmax(320px, .85fr);
        }
        .routine-steps {
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        }
        .routine-step-card {
            position: relative;
            display: grid;
            gap: 12px;
        }
        .routine-step-number {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 800;
            background: #ecf5ff;
            color: #1f56a7;
        }
        .routine-step-top {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            align-items: flex-start;
        }
        .routine-step-card h4 {
            margin: 0;
            font-size: 18px;
            line-height: 1.2;
        }
        .routine-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }
        .routine-status.is-ok { background: #e8f7ef; color: #166534; }
        .routine-status.is-ready { background: #e9f2ff; color: #1d4ed8; }
        .routine-status.is-warning { background: #fff1dd; color: #9a4c0b; }
        .routine-status.is-todo { background: #fff4dc; color: #9a5a00; }
        .routine-status.is-blocked { background: #fbe9e7; color: #b42318; }
        .routine-status.is-wait { background: #eef2f7; color: #526375; }
        .routine-session-list,
        .routine-balance-list {
            display: grid;
            gap: 10px;
            margin-top: 14px;
        }
        .routine-session-item,
        .routine-balance-item,
        .routine-ticket-card {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: flex-start;
        }
        .routine-session-item,
        .routine-balance-item {
            padding: 12px 14px;
            border-radius: 16px;
            background: #fff;
            border: 1px solid #e6edf5;
        }
        .routine-ticket-list {
            margin-top: 14px;
        }
        .routine-ticket-card strong {
            display: block;
            margin-bottom: 4px;
        }
        .routine-ticket-card .muted {
            color: #6f8397;
        }
        .routine-empty {
            padding: 26px 18px;
            border-radius: 20px;
            border: 1px dashed rgba(102, 82, 56, .18);
            background: rgba(255, 255, 255, .55);
            text-align: center;
            color: #6f8397;
        }
        @media (max-width: 1080px) {
            .routine-hero,
            .routine-layout {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 680px) {
            .routine-section-head,
            .routine-actions,
            .routine-ticket-card,
            .routine-balance-item,
            .routine-session-item {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
@endpush

@section('content')
    <div class="routine-shell">
        <section class="routine-hero">
            <div style="display:grid; gap:14px; align-content:start;">
                <div class="topbar-label" style="color:rgba(255,255,255,.72);">Mode commercant</div>
                <h2>Routine commerce</h2>
                <p class="muted" style="margin:0; max-width:780px;">
                    Va droit a l essentiel: ouvrir la caisse, vendre, encaisser, verifier le stock, cloturer et sortir le resume du jour.
                    Tout est range dans l ordre de travail d une boutique.
                </p>
                <div class="routine-badge-row">
                    <span class="routine-chip">Connexion rapide</span>
                    <span class="routine-chip">Caisse et tickets</span>
                    <span class="routine-chip">Stock visible</span>
                    <span class="routine-chip">Resume du jour</span>
                </div>
                <div class="routine-actions">
                    @allowed('pos.view')
                        <a href="{{ $currentSession ? route('pos.sales.create', ['session' => $currentSession->id]) : route('pos.index') }}" class="button button-primary">{{ $currentSession ? 'Continuer la caisse' : 'Ouvrir la caisse' }}</a>
                    @endallowed
                    @allowed('pos.view')
                        <a href="{{ route('pos.report', $reportFilters) }}" class="button button-secondary">Rapport du jour</a>
                    @endallowed
                </div>
            </div>
            <aside class="routine-side-card">
                <div class="label">Etat du poste</div>
                <strong>{{ $currentSession ? 'Session ouverte sur ton poste' : 'Aucune caisse ouverte pour l instant' }}</strong>
                <div class="help">
                    @if ($currentSession)
                        {{ $currentSession->session_number }} · {{ $currentSession->warehouse?->name }} · {{ $currentSession->cashAccount?->name }}
                    @else
                        Ouvre une caisse pour commencer a vendre et encaisser sur ce poste.
                    @endif
                </div>
                <div class="routine-side-grid">
                    <div>
                        <div class="label">Tickets du jour</div>
                        <div style="font-size:26px; font-weight:800; margin-top:6px;">{{ $dailyReport['sales_count'] }}</div>
                    </div>
                    <div>
                        <div class="label">Encaisse net</div>
                        <div style="font-size:26px; font-weight:800; margin-top:6px;">{{ number_format($dailyReport['net_cash'], 0, ',', ' ') }} XOF</div>
                    </div>
                </div>
            </aside>
        </section>

        <div class="routine-kpis">
            <div class="routine-kpi">
                <div class="label">Ventes nettes du jour</div>
                <div class="value">{{ number_format($dailyReport['net_sales'], 0, ',', ' ') }}</div>
                <div class="help">{{ $dailyReport['sales_count'] }} ticket(s) aujourd hui.</div>
            </div>
            <div class="routine-kpi">
                <div class="label">Encaissements du jour</div>
                <div class="value">{{ number_format($dailyReport['incoming_total'], 0, ',', ' ') }}</div>
                <div class="help">Flux entrants sur les sessions du jour.</div>
            </div>
            <div class="routine-kpi">
                <div class="label">Produits a surveiller</div>
                <div class="value">{{ number_format($stockAlerts, 0, ',', ' ') }}</div>
                <div class="help">Articles au minimum de stock sur l agence active.</div>
            </div>
            <div class="routine-kpi">
                <div class="label">Panier moyen</div>
                <div class="value">{{ number_format($dailyReport['average_ticket'], 0, ',', ' ') }}</div>
                <div class="help">Lecture simple des tickets du jour.</div>
            </div>
        </div>

        <section class="card">
            <div class="routine-section-head">
                <div>
                    <h3>Parcours du jour</h3>
                    <p class="muted">Le commerçant peut suivre ces etapes dans l ordre sans chercher le bon module.</p>
                </div>
            </div>
            <div class="routine-steps">
                @foreach ($steps as $step)
                    <article class="routine-step-card">
                        <div class="routine-step-top">
                            <span class="routine-step-number">{{ $step['number'] }}</span>
                            <span class="routine-status is-{{ $step['status'] }}">{{ $step['status_label'] }}</span>
                        </div>
                        <div>
                            <h4>{{ $step['title'] }}</h4>
                            <p class="muted" style="margin:8px 0 0;">{{ $step['description'] }}</p>
                        </div>
                        <div>
                            <a href="{{ $step['action_url'] }}" class="button button-secondary">{{ $step['action_label'] }}</a>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <div class="routine-layout">
            <section class="card">
                <div class="routine-section-head">
                    <div>
                        <h3>{{ $currentSession ? 'Session active' : 'Avant d ouvrir la caisse' }}</h3>
                        <p class="muted">{{ $currentSession ? 'Les infos utiles pour continuer, surveiller et cloturer la session courante.' : 'Petit rappel des controles simples avant de demarrer la journee.' }}</p>
                    </div>
                </div>

                @if ($currentSession && $sessionSummary)
                    <div class="routine-focus-grid">
                        <div class="routine-focus-card">
                            <div class="label">Session</div>
                            <div class="value">{{ $currentSession->session_number }}</div>
                            <div class="help">Ouverte le {{ $currentSession->opened_at?->format('d/m H:i') }}</div>
                        </div>
                        <div class="routine-focus-card">
                            <div class="label">Ventes nettes session</div>
                            <div class="value">{{ number_format($sessionSummary['sales_total'], 0, ',', ' ') }}</div>
                            <div class="help">{{ $sessionSummary['sales_count'] }} ticket(s) sur cette caisse.</div>
                        </div>
                        <div class="routine-focus-card">
                            <div class="label">Encaisse attendu</div>
                            <div class="value">{{ number_format($sessionSummary['expected_amount'], 0, ',', ' ') }}</div>
                            <div class="help">Montant attendu avant comptage de cloture.</div>
                        </div>
                    </div>

                    <div class="routine-session-list">
                        <div class="routine-session-item">
                            <div>
                                <strong>Compte de caisse</strong>
                                <div class="muted">{{ $currentSession->cashAccount?->name ?? 'Non renseigne' }}</div>
                            </div>
                            <div style="font-weight:700;">{{ $currentSession->warehouse?->name ?? 'Sans entrepot' }}</div>
                        </div>
                        <div class="routine-session-item">
                            <div>
                                <strong>Remises accordees</strong>
                                <div class="muted">Impact total des remises sur cette session.</div>
                            </div>
                            <div style="font-weight:700;">{{ number_format($sessionSummary['discount_total'], 0, ',', ' ') }} XOF</div>
                        </div>
                        <div class="routine-session-item">
                            <div>
                                <strong>Retours session</strong>
                                <div class="muted">Retournes ou echanges deja traites.</div>
                            </div>
                            <div style="font-weight:700;">{{ number_format($sessionSummary['return_total'], 0, ',', ' ') }} XOF</div>
                        </div>
                    </div>

                    <div class="routine-ticket-list">
                        @forelse ($recentTickets as $ticket)
                            <div class="routine-ticket-card">
                                <div>
                                    <strong>{{ $ticket->invoice_number }}</strong>
                                    <div class="muted">{{ $ticket->customer?->name ?? 'Client comptoir' }} · {{ $ticket->invoice_date?->format('d/m/Y H:i') }}</div>
                                </div>
                                <div style="display:grid; gap:8px; justify-items:end;">
                                    <div style="font-weight:800; color:#13263d;">{{ number_format((float) $ticket->total, 0, ',', ' ') }} XOF</div>
                                    <div style="display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end;">
                                        <a href="{{ route('pos.receipt', $ticket) }}" class="button button-secondary">Ticket</a>
                                        <a href="{{ route('pos.receipt.thermal', $ticket) }}" class="button button-secondary">Thermique</a>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="routine-empty">Aucun ticket recent sur cette session pour le moment.</div>
                        @endforelse
                    </div>
                @else
                    <div class="routine-session-list">
                        <div class="routine-session-item">
                            <div>
                                <strong>1. Verifie le compte de caisse</strong>
                                <div class="muted">Choisis le bon compte physique ou mobile money pour les encaissements du poste.</div>
                            </div>
                        </div>
                        <div class="routine-session-item">
                            <div>
                                <strong>2. Verifie l entrepot de vente</strong>
                                <div class="muted">Les tickets comptoir sortiront le stock de cet entrepot pendant toute la session.</div>
                            </div>
                        </div>
                        <div class="routine-session-item">
                            <div>
                                <strong>3. Compte les especes</strong>
                                <div class="muted">Le detail par coupure facilite la cloture et evite les ecarts en fin de journee.</div>
                            </div>
                        </div>
                    </div>
                @endif
            </section>

            <aside class="card">
                <div class="routine-section-head">
                    <div>
                        <h3>Lecture simple</h3>
                        <p class="muted">Ce qu on nous doit, ce qu on doit et ce qui demande une reaction rapide.</p>
                    </div>
                </div>
                <div class="routine-balance-list">
                    @allowed('sales.view')
                        <div class="routine-balance-item">
                            <div>
                                <strong>Clients a relancer</strong>
                                <div class="muted">{{ $customerReceivables['count'] }} facture(s) ouvertes.</div>
                            </div>
                            <div style="font-weight:800;">{{ number_format($customerReceivables['amount'], 0, ',', ' ') }} XOF</div>
                        </div>
                    @endallowed
                    @allowed('purchases.view')
                        <div class="routine-balance-item">
                            <div>
                                <strong>Fournisseurs a regler</strong>
                                <div class="muted">{{ $supplierPayables['count'] }} facture(s) fournisseur ouvertes.</div>
                            </div>
                            <div style="font-weight:800;">{{ number_format($supplierPayables['amount'], 0, ',', ' ') }} XOF</div>
                        </div>
                    @endallowed
                    @allowed('stock.view')
                        <div class="routine-balance-item">
                            <div>
                                <strong>Stock critique</strong>
                                <div class="muted">Produits au minimum de stock sur l agence active.</div>
                            </div>
                            <div style="font-weight:800;">{{ number_format($stockAlerts, 0, ',', ' ') }}</div>
                        </div>
                    @endallowed
                    @allowed('pos.view')
                        <div class="routine-balance-item">
                            <div>
                                <strong>Resume du jour</strong>
                                <div class="muted">Tickets, ventes, encaissements et produits qui tournent.</div>
                            </div>
                            <div>
                                <a href="{{ route('pos.report', $reportFilters) }}" class="button button-secondary">Ouvrir</a>
                            </div>
                        </div>
                    @endallowed
                </div>
            </aside>
        </div>
    </div>
@endsection
