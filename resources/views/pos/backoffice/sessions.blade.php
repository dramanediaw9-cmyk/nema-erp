@extends('layouts.app')

@section('title', 'Sessions POS - Nema ERP')
@section('page-title', 'Sessions POS')

@section('content')
    <style>
        .pos-session-stack {
            display: grid;
            gap: 14px;
        }
        .pos-session-mobile-card {
            border: 1px solid #dbe5f0;
            border-radius: 22px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            box-shadow: 0 14px 28px rgba(15, 23, 42, 0.06);
            padding: 18px;
            display: grid;
            gap: 14px;
        }
        .pos-session-mobile-head {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: flex-start;
        }
        .pos-session-mobile-head strong {
            display: block;
            font-size: 18px;
            color: #14263c;
        }
        .pos-session-mobile-meta {
            color: #64748b;
            font-size: 13px;
            line-height: 1.5;
        }
        .pos-session-mobile-grid {
            display: grid;
            gap: 10px;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        }
        .pos-session-mobile-kpi {
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            background: #fff;
            padding: 12px 14px;
        }
        .pos-session-mobile-kpi .label {
            color: #64748b;
            font-size: 11px;
            letter-spacing: .08em;
            text-transform: uppercase;
            font-weight: 700;
        }
        .pos-session-mobile-kpi .value {
            margin-top: 8px;
            font-size: 20px;
            font-weight: 800;
            color: #14263c;
            letter-spacing: -.03em;
        }
        .pos-session-mobile-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
    </style>

    <div class="grid" style="gap:18px;">
        @include('pos.partials.backoffice-nav')

        <div class="page-head">
            <div>
                <h2 style="margin:0;">Pilotage des sessions</h2>
                <div class="muted">Ouvertures, clotures, ecarts de caisse et suivi de la performance par session.</div>
            </div>
            <a href="{{ route('pos.index') }}" class="button button-secondary">Retour tableau POS</a>
        </div>

        <div class="grid stats-grid">
            <div class="card"><div class="muted">Ouvertes</div><div class="stat-value">{{ $data['summary']['open_sessions'] }}</div></div>
            <div class="card"><div class="muted">Cloturees</div><div class="stat-value">{{ $data['summary']['closed_sessions'] }}</div></div>
            <div class="card"><div class="muted">Caisse attendue</div><div class="stat-value">{{ number_format($data['summary']['expected_cash'], 0, ',', ' ') }} XOF</div></div>
            <div class="card"><div class="muted">Variance totale</div><div class="stat-value">{{ number_format($data['summary']['variance_total'], 0, ',', ' ') }} XOF</div></div>
        </div>

        <section class="pos-session-stack">
            @foreach ($data['sessions'] as $session)
                <article class="pos-session-mobile-card">
                    <div class="pos-session-mobile-head">
                        <div>
                            <strong>{{ $session->session_number }}</strong>
                            <div class="pos-session-mobile-meta">
                                {{ $session->warehouse?->name ?? 'Sans entrepot' }} · {{ $session->cashAccount?->name ?? 'Sans caisse' }}<br>
                                Ouverte {{ $session->opened_at?->format('d/m H:i') ?? 'n/a' }} · {{ $session->opener?->name ?? 'n/a' }}
                                @if ($session->closed_at)
                                    <br>Cloturee {{ $session->closed_at?->format('d/m H:i') ?? 'n/a' }} · {{ $session->closer?->name ?? 'n/a' }}
                                @endif
                            </div>
                        </div>
                        <span class="badge {{ $session->status === 'open' ? 'badge-success' : 'badge-warning' }}">
                            {{ $session->status === 'open' ? 'En cours' : 'Fermee & comptabilisee' }}
                        </span>
                    </div>

                    <div class="pos-session-mobile-grid">
                        <div class="pos-session-mobile-kpi">
                            <div class="label">Commandes</div>
                            <div class="value">{{ $session->orders_count }}</div>
                        </div>
                        <div class="pos-session-mobile-kpi">
                            <div class="label">Paiements</div>
                            <div class="value">{{ number_format((float) ($session->payments_total ?? 0), 0, ',', ' ') }}</div>
                        </div>
                        <div class="pos-session-mobile-kpi">
                            <div class="label">Mouvements</div>
                            <div class="value">{{ $session->returns_count }}</div>
                        </div>
                        <div class="pos-session-mobile-kpi">
                            <div class="label">Ecart caisse</div>
                            <div class="value">{{ number_format((float) $session->variance_amount, 0, ',', ' ') }}</div>
                        </div>
                    </div>

                    <div class="pos-session-mobile-actions">
                        <a href="{{ route('pos.show', $session) }}" class="button button-secondary">Ouvrir la session</a>
                        @if ($session->status === 'open')
                            <a href="{{ route('pos.sales.create', ['session' => $session->id]) }}" class="button button-primary">Continuer la vente</a>
                        @endif
                    </div>
                </article>
            @endforeach
        </section>

        <section class="card">
            <h3 class="section-title">Sessions recentes</h3>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Session</th>
                            <th>Entrepot</th>
                            <th>Caisse</th>
                            <th>Ouverte par</th>
                            <th>Statut</th>
                            <th>Variance</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($data['sessions'] as $session)
                            <tr>
                                <td>{{ $session->session_number }}</td>
                                <td>{{ $session->warehouse?->name ?? 'n/a' }}</td>
                                <td>{{ $session->cashAccount?->name ?? 'n/a' }}</td>
                                <td>{{ $session->opener?->name ?? 'n/a' }}</td>
                                <td><span class="badge {{ $session->status === 'open' ? 'badge-success' : 'badge-muted' }}">{{ strtoupper($session->status) }}</span></td>
                                <td>{{ number_format((float) $session->variance_amount, 0, ',', ' ') }} XOF</td>
                                <td><a href="{{ route('pos.show', $session) }}" class="button button-secondary">Voir</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="muted">Aucune session POS disponible.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
