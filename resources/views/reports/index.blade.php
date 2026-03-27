@extends('layouts.app')

@section('title', 'Rapports - Nema ERP')
@section('page-title', 'Rapports dirigeant')

@section('content')
    @php
        $today = now()->format('Y-m-d');
        $weekStart = now()->subDays(6)->format('Y-m-d');
        $thirtyStart = now()->subDays(29)->format('Y-m-d');
        $monthStart = now()->startOfMonth()->format('Y-m-d');
        $cashNet = (float) $treasury['in'] - (float) $treasury['out'];
    @endphp

    <div class="page-head">
        <div>
            <h2 style="margin:0;">Vue synthetique de l'activite</h2>
            <div class="muted">Periode du {{ \Illuminate\Support\Carbon::parse($filters['date_from'])->format('d/m/Y') }} au {{ \Illuminate\Support\Carbon::parse($filters['date_to'])->format('d/m/Y') }} · agence {{ $branch?->name }}</div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="{{ route('sales.export', $filters) }}" class="button button-secondary">CSV ventes</a>
            <a href="{{ route('purchases.export', $filters) }}" class="button button-secondary">CSV achats</a>
            <a href="{{ route('payments.export', $filters) }}" class="button button-secondary">CSV paiements</a>
            <a href="{{ route('expenses.export', $filters) }}" class="button button-secondary">CSV depenses</a>
        </div>
    </div>

    <section class="card" style="margin-bottom:20px;">
        <div class="filter-pills">
            <a href="{{ route('reports.index', ['date_from' => $today, 'date_to' => $today]) }}" class="button button-secondary">Aujourd hui</a>
            <a href="{{ route('reports.index', ['date_from' => $weekStart, 'date_to' => $today]) }}" class="button button-secondary">7 derniers jours</a>
            <a href="{{ route('reports.index', ['date_from' => $thirtyStart, 'date_to' => $today]) }}" class="button button-secondary">30 derniers jours</a>
            <a href="{{ route('reports.index', ['date_from' => $monthStart, 'date_to' => $today]) }}" class="button button-secondary">Mois en cours</a>
        </div>

        <form method="GET" action="{{ route('reports.index') }}" class="form-grid" style="align-items:end;">
            <div>
                <label for="date_from">Date debut</label>
                <input type="date" id="date_from" name="date_from" value="{{ $filters['date_from'] }}">
            </div>
            <div>
                <label for="date_to">Date fin</label>
                <input type="date" id="date_to" name="date_to" value="{{ $filters['date_to'] }}">
            </div>
            <div class="full actions" style="margin-top:0; justify-content:flex-start;">
                <button type="submit" class="button button-primary">Actualiser le rapport</button>
                <a href="{{ route('reports.index') }}" class="button button-secondary">Mois en cours</a>
            </div>
        </form>
    </section>

    <div class="grid stats-grid" style="margin-bottom:20px;">
        <div class="card"><div class="muted">CA periode</div><div class="stat-value">{{ number_format($sales['total'], 0, ',', ' ') }}</div><div class="muted">{{ $sales['count'] }} facture(s)</div></div>
        <div class="card"><div class="muted">Achats periode</div><div class="stat-value">{{ number_format($purchases['total'], 0, ',', ' ') }}</div><div class="muted">{{ $purchases['count'] }} facture(s)</div></div>
        <div class="card"><div class="muted">Encaissements</div><div class="stat-value">{{ number_format($treasury['in'], 0, ',', ' ') }}</div><div class="muted">Flux entrants</div></div>
        <div class="card"><div class="muted">Decaissements</div><div class="stat-value">{{ number_format($treasury['out'], 0, ',', ' ') }}</div><div class="muted">Flux sortants</div></div>
        <div class="card"><div class="muted">Flux net</div><div class="stat-value">{{ number_format($cashNet, 0, ',', ' ') }}</div><div class="muted">Tresorerie sur la periode</div></div>
        <div class="card"><div class="muted">Depenses periode</div><div class="stat-value">{{ number_format($expenses['total'], 0, ',', ' ') }}</div><div class="muted">{{ $expenses['count'] }} depense(s)</div></div>
        <div class="card"><div class="muted">Stock valorise</div><div class="stat-value">{{ number_format($stock['valuation'], 0, ',', ' ') }}</div><div class="muted">{{ $stock['product_count'] }} article(s)</div></div>
    </div>

    <div class="split">
        <section class="card">
            <h2 style="margin-top:0;">Commercial et recouvrement</h2>
            <div class="grid" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
                <div class="card" style="padding:16px;">
                    <strong>Ventes de la periode</strong>
                    <div class="muted" style="margin-top:8px;">Encaisse sur la periode : {{ number_format($sales['paid'], 0, ',', ' ') }} XOF.</div>
                    <div class="muted">Reste a encaisser sur ces ventes : {{ number_format($sales['due'], 0, ',', ' ') }} XOF.</div>
                </div>
                <div class="card" style="padding:16px;">
                    <strong>Creances ouvertes</strong>
                    <div class="muted" style="margin-top:8px;">{{ $receivables['count'] }} facture(s) client encore ouvertes.</div>
                    <div class="muted">Encours global : {{ number_format($receivables['total'], 0, ',', ' ') }} XOF.</div>
                </div>
            </div>
        </section>

        <section class="card">
            <h2 style="margin-top:0;">Approvisionnements et dettes</h2>
            <div class="grid" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
                <div class="card" style="padding:16px;">
                    <strong>Achats de la periode</strong>
                    <div class="muted" style="margin-top:8px;">Regle sur la periode : {{ number_format($purchases['paid'], 0, ',', ' ') }} XOF.</div>
                    <div class="muted">Reste a regler sur ces achats : {{ number_format($purchases['due'], 0, ',', ' ') }} XOF.</div>
                </div>
                <div class="card" style="padding:16px;">
                    <strong>Dettes fournisseurs</strong>
                    <div class="muted" style="margin-top:8px;">{{ $payables['count'] }} facture(s) fournisseur encore ouvertes.</div>
                    <div class="muted">Encours global : {{ number_format($payables['total'], 0, ',', ' ') }} XOF.</div>
                </div>
            </div>
        </section>
    </div>

    <div class="split" style="margin-top:20px;">
        <section class="card">
            <h2 style="margin-top:0;">Tresorerie et charges</h2>
            <div class="muted" style="margin-bottom:14px;">{{ $treasury['count'] }} mouvement(s) de tresorerie sur la periode.</div>
            <table>
                <tbody>
                <tr>
                    <th style="width:50%;">Encaissements</th>
                    <td>{{ number_format($treasury['in'], 0, ',', ' ') }} XOF</td>
                </tr>
                <tr>
                    <th>Decaissements</th>
                    <td>{{ number_format($treasury['out'], 0, ',', ' ') }} XOF</td>
                </tr>
                <tr>
                    <th>Flux net</th>
                    <td>{{ number_format($cashNet, 0, ',', ' ') }} XOF</td>
                </tr>
                <tr>
                    <th>Depenses saisies</th>
                    <td>{{ number_format($expenses['total'], 0, ',', ' ') }} XOF</td>
                </tr>
                <tr>
                    <th>Depenses non payees</th>
                    <td>{{ $expenses['unpaid'] }}</td>
                </tr>
                </tbody>
            </table>
        </section>

        <section class="card">
            <h2 style="margin-top:0;">Stock et alertes</h2>
            <table>
                <tbody>
                <tr>
                    <th style="width:50%;">Articles stockes</th>
                    <td>{{ $stock['product_count'] }}</td>
                </tr>
                <tr>
                    <th>Valorisation stock</th>
                    <td>{{ number_format($stock['valuation'], 0, ',', ' ') }} XOF</td>
                </tr>
                <tr>
                    <th>Alertes stock faible</th>
                    <td>{{ $stock['alerts'] }}</td>
                </tr>
                </tbody>
            </table>
            <div style="margin-top:16px; display:flex; gap:10px; flex-wrap:wrap;">
                <a href="{{ route('stock.index') }}" class="button button-secondary">Voir le stock</a>
                <a href="{{ route('stock.export') }}" class="button button-secondary">Exporter le stock</a>
            </div>
        </section>
    </div>
@endsection
