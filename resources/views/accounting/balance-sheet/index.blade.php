@extends('layouts.app')

@section('title', 'Bilan - Nema ERP')
@section('page-title', 'Bilan simplifie')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">Situation patrimoniale</h2>
            <div class="muted">Actif, passif et capitaux propres a la date selectionnee.</div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="{{ route('accounting.balance-sheet.export', request()->query()) }}" class="button button-secondary">Exporter CSV</a>
            <a href="{{ route('accounting.balance-sheet.print', request()->query()) }}" class="button button-secondary" target="_blank">PDF</a>
        </div>
    </div>

    <section class="card" style="margin-bottom:20px;">
        <form method="GET" action="{{ route('accounting.balance-sheet.index') }}" class="form-grid" style="align-items:end; grid-template-columns: repeat(2, minmax(0, 1fr));">
            <div>
                <label for="date_to">Situation au</label>
                <input type="date" id="date_to" name="date_to" value="{{ $filters['date_to'] }}">
            </div>
            <div class="actions" style="margin-top:0; justify-content:flex-start; align-self:end;">
                <button type="submit" class="button button-primary">Actualiser</button>
                <a href="{{ route('accounting.balance-sheet.index') }}" class="button button-secondary">Aujourd hui</a>
            </div>
        </form>
    </section>

    <div class="grid stats-grid" style="margin-bottom:20px;">
        <div class="card"><div class="muted">Total actif</div><div class="stat-value">{{ number_format((float) $report['asset_total'], 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Total passif</div><div class="stat-value">{{ number_format((float) ($report['liability_total'] + $report['equity_total']), 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Capitaux propres</div><div class="stat-value">{{ number_format((float) $report['equity_total'], 0, ',', ' ') }}</div></div>
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
@endsection
