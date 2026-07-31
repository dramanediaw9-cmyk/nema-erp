@extends('layouts.app')

@section('title', 'Balance - Nema ERP')
@section('page-title', 'Balance comptable')

@section('content')
    <div class="erp-work-page">
        <section class="erp-work-toolbar">
            <div class="erp-work-toolbar__context">
                <div>
                    <strong>Balance générale</strong>
                    <div class="muted">Vue synthétique des comptes sur la période sélectionnée.</div>
                </div>
            </div>
            <div class="erp-work-toolbar__actions">
                <a href="{{ route('accounting.balance.export', request()->query()) }}" class="button button-secondary">Exporter CSV</a>
                <a href="{{ route('accounting.balance.print', request()->query()) }}" class="button button-secondary" target="_blank">PDF</a>
            </div>
        </section>

        <details class="card erp-filter-panel" @if(request()->hasAny(['date_from', 'date_to'])) open @endif>
            <summary>Filtres de la balance</summary>
            <div class="erp-filter-panel__body">
                <form method="GET" action="{{ route('accounting.balance.index') }}" class="form-grid" style="align-items:end;">
                    <div>
                        <label for="date_from">Date début</label>
                        <input type="date" id="date_from" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
                    </div>
                    <div>
                        <label for="date_to">Date fin</label>
                        <input type="date" id="date_to" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
                    </div>
                    <div class="full actions" style="margin-top:0; justify-content:flex-start;">
                        <button type="submit" class="button button-primary">Filtrer la balance</button>
                        <a href="{{ route('accounting.balance.index') }}" class="button button-secondary">Réinitialiser</a>
                    </div>
                </form>
            </div>
        </details>

        <div class="erp-kpi-strip">
            <div class="card erp-kpi-card">
                <div class="label">Débit cumulé</div>
                <div class="value">{{ number_format((float) $summary['debit'], 0, ',', ' ') }} XOF</div>
            </div>
            <div class="card erp-kpi-card">
                <div class="label">Crédit cumulé</div>
                <div class="value">{{ number_format((float) $summary['credit'], 0, ',', ' ') }} XOF</div>
            </div>
            <div class="card erp-kpi-card">
                <div class="label">Résultat cumulé</div>
                <div class="value">{{ number_format((float) $summary['result'], 0, ',', ' ') }} XOF</div>
            </div>
        </div>

        <section class="card table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Code</th>
                    <th>Libellé</th>
                    <th>Type</th>
                    <th>Débit</th>
                    <th>Crédit</th>
                    <th>Solde</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($balances as $balance)
                    <tr>
                        <td><strong>{{ $balance['code'] }}</strong></td>
                        <td>{{ $balance['name'] }}</td>
                        <td>{{ ucfirst($balance['type']) }}</td>
                        <td>{{ number_format((float) $balance['total_debit'], 0, ',', ' ') }} XOF</td>
                        <td>{{ number_format((float) $balance['total_credit'], 0, ',', ' ') }} XOF</td>
                        <td>{{ number_format((float) $balance['balance'], 0, ',', ' ') }} XOF</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="muted">Aucune donnée comptable disponible.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </section>
    </div>
@endsection
