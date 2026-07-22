@extends('layouts.app')

@section('title', 'Pilotage manager - Nema ERP')
@section('page-title', 'Pilotage manager')

@push('page-styles')
    <style>
        .manager-grid { display:grid; gap:14px; }
        .manager-kpis { grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); }
        .manager-two { grid-template-columns:minmax(0, 1.15fr) minmax(320px, .85fr); align-items:start; }
        .manager-three { grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); align-items:start; }
        .manager-card-tight { padding:14px 16px; }
        .manager-kpi-label { color:#667987; font-size:11px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .manager-kpi-value { margin-top:7px; font-size:25px; font-weight:900; color:#172b31; letter-spacing:0; }
        .manager-kpi-help { margin-top:4px; color:#71808a; font-size:12px; }
        .manager-headline { display:flex; justify-content:space-between; gap:12px; align-items:flex-start; flex-wrap:wrap; margin-bottom:12px; }
        .manager-headline h2 { margin:0; font-size:18px; letter-spacing:0; }
        .manager-list { display:grid; gap:8px; }
        .manager-row { display:flex; justify-content:space-between; gap:12px; align-items:flex-start; padding:10px 0; border-bottom:1px solid rgba(15,23,42,.08); }
        .manager-row:last-child { border-bottom:0; }
        .manager-row strong { display:block; color:#172b31; }
        .manager-row .amount { font-weight:900; white-space:nowrap; color:#172b31; }
        .manager-plan { display:grid; gap:8px; }
        .manager-plan-item { display:flex; gap:10px; align-items:flex-start; padding:11px 12px; border-radius:8px; border:1px solid rgba(15,23,42,.1); background:#fff; }
        .manager-dot { width:10px; height:10px; border-radius:999px; margin-top:5px; flex:0 0 10px; background:#0f766e; }
        .manager-dot.is-warning { background:#ca6702; }
        .manager-dot.is-danger { background:#b42318; }
        .manager-dot.is-ok { background:#16875f; }
        .manager-pill-row { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
        .manager-empty { padding:18px; border-radius:8px; background:#f8faf9; color:#71808a; text-align:center; border:1px dashed rgba(15,23,42,.14); }
        @media (max-width: 1020px) { .manager-two { grid-template-columns:1fr; } }
    </style>
@endpush

@section('content')
    @php
        $customerLabel = $businessVocabulary['client'] ?? 'Client';
        $productLabel = $businessVocabulary['product'] ?? 'Produit';
        $productsLabel = $businessVocabulary['products'] ?? 'Produits';
        $saleLabel = $businessVocabulary['sale'] ?? 'Vente';
        $salesLabel = $businessVocabulary['sales'] ?? 'Ventes';
        $stockLabel = $businessVocabulary['stock'] ?? 'Stock';
        $cashierLabel = $businessVocabulary['cashier'] ?? 'Caissier';
        $cashiersLabel = $businessVocabulary['cashiers'] ?? 'Caissiers';
        $replenishmentLabel = $businessVocabulary['replenishment'] ?? 'Reappro';
        $money = fn ($value) => number_format((float) $value, 0, ',', ' ').' F';
        $qty = fn ($value) => number_format((float) $value, 2, ',', ' ');
        $stockAlerts = $report['stock_alerts'] ?? ['out_of_stock_count' => 0, 'low_stock_count' => 0, 'items' => collect()];
        $settlementWatch = $report['settlement_watch'] ?? ['methods' => []];
    @endphp

    <div class="page-head">
        <div>
            <div class="eyebrow">Travail manager</div>
            <h2 style="margin:0;">Pilotage quotidien</h2>
            <div class="muted">{{ $salesLabel }}, caisse, marge, ruptures et actions sensibles pour l agence active.</div>
        </div>
        <div class="manager-pill-row">
            <a href="{{ route('manager.pilot.print', ['date' => $date, 'branch_id' => $branchId]) }}" class="button button-primary" target="_blank">Imprimer synthese</a>
            <a href="{{ route('notifications.index', ['scope' => 'active']) }}" class="button button-secondary">Alertes</a>
            <a href="{{ route('activity-logs.index', ['category' => 'sensitive']) }}" class="button button-secondary">Audit</a>
        </div>
    </div>

    <section class="card" style="margin-bottom:14px;">
        <form method="GET" action="{{ route('manager.pilot') }}" class="form-grid" style="grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); align-items:end;">
            <div>
                <label for="date">Date</label>
                <input id="date" type="date" name="date" value="{{ $date }}">
            </div>
            <div>
                <label for="branch_id">Agence</label>
                <select id="branch_id" name="branch_id">
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((int) $branchId === (int) $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="actions" style="margin-top:0; justify-content:flex-start;">
                <button class="button button-primary" type="submit">Actualiser</button>
                <a href="{{ route('manager.pilot') }}" class="button button-secondary">Aujourd'hui</a>
            </div>
        </form>
    </section>

    <div class="manager-grid manager-kpis" style="margin-bottom:14px;">
        <section class="card manager-card-tight"><div class="manager-kpi-label">{{ $salesLabel }} nettes</div><div class="manager-kpi-value">{{ $money($report['net_sales'] ?? 0) }}</div><div class="manager-kpi-help">{{ number_format((int) ($report['sales_count'] ?? 0), 0, ',', ' ') }} ticket(s)</div></section>
        <section class="card manager-card-tight"><div class="manager-kpi-label">Encaissements nets</div><div class="manager-kpi-value">{{ $money($report['net_cash'] ?? 0) }}</div><div class="manager-kpi-help">Flux caisse du jour</div></section>
        <section class="card manager-card-tight"><div class="manager-kpi-label">Marge estimee</div><div class="manager-kpi-value">{{ $money($report['estimated_margin'] ?? 0) }}</div><div class="manager-kpi-help">{{ number_format((float) ($report['estimated_margin_rate'] ?? 0), 1, ',', ' ') }}%</div></section>
        <section class="card manager-card-tight"><div class="manager-kpi-label">Ticket moyen</div><div class="manager-kpi-value">{{ $money($report['average_ticket'] ?? 0) }}</div><div class="manager-kpi-help">Panier comptoir</div></section>
        <section class="card manager-card-tight"><div class="manager-kpi-label">Ruptures</div><div class="manager-kpi-value">{{ number_format((int) ($stockAlerts['out_of_stock_count'] ?? 0), 0, ',', ' ') }}</div><div class="manager-kpi-help">{{ number_format((int) ($stockAlerts['low_stock_count'] ?? 0), 0, ',', ' ') }} {{ strtolower($stockLabel) }} critique(s)</div></section>
        <section class="card manager-card-tight"><div class="manager-kpi-label">Alertes actives</div><div class="manager-kpi-value">{{ number_format($activeAlerts->count(), 0, ',', ' ') }}</div><div class="manager-kpi-help">{{ number_format($activeAlerts->where('level', 'danger')->count(), 0, ',', ' ') }} critique(s)</div></section>
    </div>

    <div class="manager-grid manager-two" style="margin-bottom:14px;">
        <section class="card">
            <div class="manager-headline">
                <div>
                    <h2>Plan d'action</h2>
                    <div class="muted">Ce qui demande une decision manager maintenant.</div>
                </div>
                <a href="{{ route('pos.report', ['date' => $date]) }}" class="button button-secondary">Rapport complet</a>
            </div>
            <div class="manager-plan">
                @foreach ($actionPlan as $item)
                    <a href="{{ $item['url'] }}" class="manager-plan-item" style="text-decoration:none; color:inherit;">
                        <span class="manager-dot is-{{ $item['level'] }}"></span>
                        <span style="min-width:0;">
                            <strong>{{ $item['title'] }} · {{ number_format((int) $item['value'], 0, ',', ' ') }}</strong>
                            <span class="muted" style="display:block; margin-top:4px;">{{ $item['message'] }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>

        <section class="card">
            <div class="manager-headline">
                <div>
                    <h2>Sessions ouvertes</h2>
                    <div class="muted">Caisses actives a surveiller.</div>
                </div>
                <a href="{{ route('pos.sessions.index') }}" class="button button-secondary">Voir sessions</a>
            </div>
            <div class="manager-list">
                @forelse ($openSessions as $session)
                    <div class="manager-row">
                        <div>
                            <strong>{{ $session->session_number }}</strong>
                            <div class="muted">{{ $session->opener?->name ?? $cashierLabel }} · {{ $session->cashAccount?->name ?? 'Caisse' }}</div>
                            <div class="muted">{{ $session->opened_at?->format('d/m H:i') }} · {{ $session->warehouse?->name ?? 'Entrepot' }}</div>
                        </div>
                        <a href="{{ route('pos.show', $session) }}" class="button button-secondary">Ouvrir</a>
                    </div>
                @empty
                    <div class="manager-empty">Aucune session ouverte sur cette agence.</div>
                @endforelse
            </div>
        </section>
    </div>

    <div class="manager-grid manager-three" style="margin-bottom:14px;">
        <section class="card">
            <div class="manager-headline"><div><h2>Caisse par {{ strtolower($cashierLabel) }}</h2><div class="muted">Performance comptoir du jour.</div></div></div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>{{ $cashierLabel }}</th><th>Tickets</th><th>{{ $salesLabel }}</th><th>Marge</th></tr></thead>
                    <tbody>
                    @forelse ($report['cashier_breakdown'] as $row)
                        <tr>
                            <td>{{ $row->cashier_name }}</td>
                            <td>{{ number_format((float) $row->sales_count, 0, ',', ' ') }}</td>
                            <td>{{ $money($row->total_sales) }}</td>
                            <td>{{ $money($row->estimated_margin) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="muted">Aucune {{ strtolower($saleLabel) }} par {{ strtolower($cashierLabel) }}.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card">
            <div class="manager-headline"><div><h2>Top {{ strtolower($productsLabel) }}</h2><div class="muted">{{ $productsLabel }} les plus vendus.</div></div></div>
            <div class="manager-list">
                @forelse ($report['top_products'] as $product)
                    <div class="manager-row">
                        <div>
                            <strong>{{ $product->name }}</strong>
                            <div class="muted">{{ $product->scan_code ?: $product->sku }} · Qté {{ $qty($product->qty) }}</div>
                        </div>
                        <div class="amount">{{ $money($product->amount) }}</div>
                    </div>
                @empty
                        <div class="manager-empty">Aucun {{ strtolower($productLabel) }} vendu sur cette date.</div>
                @endforelse
            </div>
        </section>

        <section class="card">
            <div class="manager-headline"><div><h2>Alertes actives</h2><div class="muted">Priorite terrain et controle.</div></div></div>
            <div class="manager-list">
                @forelse ($activeAlerts as $alert)
                    <div class="manager-row">
                        <div>
                            <strong>{{ $alert->title }}</strong>
                            <div class="muted">{{ \Illuminate\Support\Str::limit($alert->message, 92) }}</div>
                        </div>
                        @include('partials.erp-status-badge', ['label' => $alert->level === 'danger' ? 'Critique' : 'A suivre', 'tone' => $alert->level === 'danger' ? 'danger' : 'warning'])
                    </div>
                @empty
                    <div class="manager-empty">Aucune alerte active pour cette agence.</div>
                @endforelse
            </div>
        </section>
    </div>

    <div class="manager-grid manager-two">
        <section class="card">
            <div class="manager-headline">
                <div>
                    <h2>Ruptures et stock critique</h2>
                    <div class="muted">{{ $productsLabel }} a compter ou reapprovisionner.</div>
                </div>
                <div class="manager-pill-row">
                    <a href="{{ route('stock-counts.quick') }}" class="button button-secondary">Inventaire rapide</a>
                    <a href="{{ route('replenishments.index') }}" class="button button-primary">{{ $replenishmentLabel }}</a>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>{{ $productLabel }}</th><th>Code</th><th>{{ $stockLabel }}</th><th>Mini</th><th>Etat</th></tr></thead>
                    <tbody>
                    @forelse ($stockAlerts['items'] as $product)
                        <tr>
                            <td><strong>{{ $product->name }}</strong><div class="muted">{{ $product->category_name ?: 'Sans categorie' }}</div></td>
                            <td>{{ $product->barcode ?: $product->sku }}</td>
                            <td>{{ $qty($product->current_stock) }} {{ $product->unit }}</td>
                            <td>{{ $qty($product->min_stock) }} {{ $product->unit }}</td>
                            <td>
                                @if ((float) $product->current_stock <= 0.0001)
                                    <span class="badge badge-danger">Rupture</span>
                                @else
                                    <span class="badge badge-warning">Critique</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="muted">Aucune rupture ni alerte {{ strtolower($stockLabel) }}.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card">
            <div class="manager-headline"><div><h2>Controle encaissements</h2><div class="muted">Modes de paiement, ecarts et rapprochement.</div></div></div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Mode</th><th>Net</th><th>Ecart</th><th>Non rapproche</th></tr></thead>
                    <tbody>
                    @forelse ($settlementWatch['methods'] as $row)
                        <tr>
                            <td>{{ $row['label'] }}</td>
                            <td>{{ $money($row['expected_total'] ?? 0) }}</td>
                            <td>{{ $money($row['variance'] ?? 0) }}</td>
                            <td>{{ $money(abs((float) ($row['unreconciled_amount'] ?? 0))) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="muted">Aucun flux a controler.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="manager-headline" style="margin-top:18px;"><div><h2>Tickets recents</h2><div class="muted">Dernieres ventes POS du jour.</div></div></div>
            <div class="manager-list">
                @forelse ($recentTickets as $ticket)
                    <div class="manager-row">
                        <div>
                            <strong>{{ $ticket->invoice_number }}</strong>
                            <div class="muted">{{ $ticket->customer?->name ?? $customerLabel.' comptoir' }} · {{ $ticket->posSession?->opener?->name ?? $cashierLabel }}</div>
                        </div>
                        <div class="amount">{{ $money($ticket->total) }}</div>
                    </div>
                @empty
                    <div class="manager-empty">Aucun ticket recent.</div>
                @endforelse
            </div>
        </section>
    </div>

    <section class="card" style="margin-top:14px;">
        <div class="manager-headline">
            <div>
                <h2>Actions sensibles recentes</h2>
                <div class="muted">Prix, stock, caisse, droits et parametres sur les dernieres 24 h.</div>
            </div>
            <a href="{{ route('activity-logs.index', ['category' => 'sensitive']) }}" class="button button-secondary">Journal audit</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Date</th><th>Action</th><th>Description</th><th>Utilisateur</th></tr></thead>
                <tbody>
                @forelse ($sensitiveActions as $log)
                    <tr>
                        <td>{{ $log->created_at?->format('d/m H:i') }}</td>
                        <td>{{ $log->action }}</td>
                        <td>{{ $log->description }}</td>
                        <td>{{ $log->user?->name ?? 'Systeme' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">Aucune action sensible recente.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
