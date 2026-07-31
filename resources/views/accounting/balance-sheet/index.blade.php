@extends('layouts.app')

@section('title', 'Bilan - Nema ERP')
@section('page-title', 'Bilan simplifie')

@section('content')
    <div class="erp-work-page">
        <section class="erp-work-toolbar">
            <div class="erp-work-toolbar__context">
                <div>
                    <strong>Situation patrimoniale</strong>
                    <div class="muted">Actif, passif et capitaux propres à la date sélectionnée.</div>
                </div>
            </div>
            <div class="erp-work-toolbar__actions">
                <a href="{{ route('accounting.balance-sheet.export', request()->query()) }}" class="button button-secondary">Exporter CSV</a>
                <a href="{{ route('accounting.balance-sheet.print', request()->query()) }}" class="button button-secondary" target="_blank">PDF</a>
            </div>
        </section>

        <details class="card erp-filter-panel" @if(request()->has('date_to')) open @endif>
            <summary>Filtre de situation</summary>
            <div class="erp-filter-panel__body">
                <form method="GET" action="{{ route('accounting.balance-sheet.index') }}" class="form-grid" style="align-items:end; grid-template-columns: repeat(2, minmax(0, 1fr));">
                    <div>
                        <label for="date_to">Situation au</label>
                        <input type="date" id="date_to" name="date_to" value="{{ $filters['date_to'] }}">
                    </div>
                    <div class="actions" style="margin-top:0; justify-content:flex-start; align-self:end;">
                        <button type="submit" class="button button-primary">Actualiser</button>
                        <a href="{{ route('accounting.balance-sheet.index') }}" class="button button-secondary">Aujourd’hui</a>
                    </div>
                </form>
            </div>
        </details>

        <div class="erp-kpi-strip">
            <div class="card erp-kpi-card"><div class="label">Total actif</div><div class="value">{{ number_format((float) $report['asset_total'], 0, ',', ' ') }} XOF</div></div>
            <div class="card erp-kpi-card"><div class="label">Total passif</div><div class="value">{{ number_format((float) ($report['liability_total'] + $report['equity_total']), 0, ',', ' ') }} XOF</div></div>
            <div class="card erp-kpi-card"><div class="label">Capitaux propres</div><div class="value">{{ number_format((float) $report['equity_total'], 0, ',', ' ') }} XOF</div></div>
        </div>

        <div class="split">
        <section class="card table-wrap">
            <h2 style="margin-top:0;">Actif</h2>
            <table>
                <thead>
                <tr>
                    <th>Code</th>
                    <th>Libelle</th>
                    <th>Montant</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($report['assets'] as $line)
                    <tr>
                        <td><strong>{{ $line['code'] }}</strong></td>
                        <td>{{ $line['name'] }}</td>
                        <td>{{ number_format((float) $line['balance'], 0, ',', ' ') }} XOF</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="muted">Aucun actif comptabilise.</td></tr>
                @endforelse
                </tbody>
            </table>
        </section>

        <section class="card table-wrap">
            <h2 style="margin-top:0;">Passif</h2>
            <table>
                <thead>
                <tr>
                    <th>Code</th>
                    <th>Libelle</th>
                    <th>Montant</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($report['liabilities'] as $line)
                    <tr>
                        <td><strong>{{ $line['code'] }}</strong></td>
                        <td>{{ $line['name'] }}</td>
                        <td>{{ number_format((float) $line['balance'], 0, ',', ' ') }} XOF</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="muted">Aucun passif comptabilise.</td></tr>
                @endforelse
                @foreach ($report['equity'] as $line)
                    <tr>
                        <td><strong>{{ $line['code'] }}</strong></td>
                        <td>{{ $line['name'] }}</td>
                        <td>{{ number_format((float) $line['balance'], 0, ',', ' ') }} XOF</td>
                    </tr>
                @endforeach
                <tr>
                    <td><strong>{{ $report['current_result']['code'] }}</strong></td>
                    <td>{{ $report['current_result']['name'] }}</td>
                    <td>{{ number_format((float) $report['current_result']['balance'], 0, ',', ' ') }} XOF</td>
                </tr>
                </tbody>
            </table>
        </section>
        </div>
    </div>
@endsection
