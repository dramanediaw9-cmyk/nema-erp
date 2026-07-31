@extends('layouts.app')

@section('title', 'Compte de resultat - Nema ERP')
@section('page-title', 'Compte de resultat')

@section('content')
    <div class="erp-work-page">
        <section class="erp-work-toolbar">
            <div class="erp-work-toolbar__context">
                <div>
                    <strong>Performance comptable</strong>
                    <div class="muted">Produits, charges et résultat net sur la période.</div>
                </div>
            </div>
            <div class="erp-work-toolbar__actions">
                <a href="{{ route('accounting.profit-loss.export', request()->query()) }}" class="button button-secondary">Exporter CSV</a>
                <a href="{{ route('accounting.profit-loss.print', request()->query()) }}" class="button button-secondary" target="_blank">PDF</a>
            </div>
        </section>

        <details class="card erp-filter-panel" @if(request()->hasAny(['date_from', 'date_to'])) open @endif>
            <summary>Filtres du compte de résultat</summary>
            <div class="erp-filter-panel__body">
                <form method="GET" action="{{ route('accounting.profit-loss.index') }}" class="form-grid" style="align-items:end;">
                    <div>
                        <label for="date_from">Date début</label>
                        <input type="date" id="date_from" name="date_from" value="{{ $filters['date_from'] }}">
                    </div>
                    <div>
                        <label for="date_to">Date fin</label>
                        <input type="date" id="date_to" name="date_to" value="{{ $filters['date_to'] }}">
                    </div>
                    <div class="full actions" style="margin-top:0; justify-content:flex-start;">
                        <button type="submit" class="button button-primary">Actualiser</button>
                        <a href="{{ route('accounting.profit-loss.index') }}" class="button button-secondary">Mois en cours</a>
                    </div>
                </form>
            </div>
        </details>

        <div class="erp-kpi-strip">
            <div class="card erp-kpi-card"><div class="label">Produits</div><div class="value">{{ number_format((float) $report['income_total'], 0, ',', ' ') }} XOF</div></div>
            <div class="card erp-kpi-card"><div class="label">Charges</div><div class="value">{{ number_format((float) $report['expense_total'], 0, ',', ' ') }} XOF</div></div>
            <div class="card erp-kpi-card"><div class="label">Résultat net</div><div class="value">{{ number_format((float) $report['net_result'], 0, ',', ' ') }} XOF</div></div>
        </div>

        <div class="split">
        <section class="card table-wrap">
            <h2 style="margin-top:0;">Produits</h2>
            <table>
                <thead>
                <tr>
                    <th>Code</th>
                    <th>Libelle</th>
                    <th>Montant</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($report['income'] as $line)
                    <tr>
                        <td><strong>{{ $line['code'] }}</strong></td>
                        <td>{{ $line['name'] }}</td>
                        <td>{{ number_format((float) $line['balance'], 0, ',', ' ') }} XOF</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="muted">Aucun produit comptable sur la periode.</td></tr>
                @endforelse
                </tbody>
            </table>
        </section>

        <section class="card table-wrap">
            <h2 style="margin-top:0;">Charges</h2>
            <table>
                <thead>
                <tr>
                    <th>Code</th>
                    <th>Libelle</th>
                    <th>Montant</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($report['expenses'] as $line)
                    <tr>
                        <td><strong>{{ $line['code'] }}</strong></td>
                        <td>{{ $line['name'] }}</td>
                        <td>{{ number_format((float) $line['balance'], 0, ',', ' ') }} XOF</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="muted">Aucune charge comptable sur la periode.</td></tr>
                @endforelse
                </tbody>
            </table>
        </section>
        </div>
    </div>
@endsection
