@extends('layouts.print')

@section('title', 'Bilan')

@section('content')
    <div class="doc-header">
        <div>
            <h1>Bilan simplifie</h1>
            <div class="meta">{{ $company?->name }} · Situation au {{ \Illuminate\Support\Carbon::parse($filters['date_to'])->format('d/m/Y') }}</div>
        </div>
    </div>

    <div class="grid grid-2">
        <div class="panel">
            <h2>Actif</h2>
            <table>
                <thead><tr><th>Code</th><th>Libelle</th><th class="right">Montant</th></tr></thead>
                <tbody>
                @foreach ($report['assets'] as $line)
                    <tr>
                        <td>{{ $line['code'] }}</td>
                        <td>{{ $line['name'] }}</td>
                        <td class="right">{{ number_format((float) $line['balance'], 0, ',', ' ') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="panel">
            <h2>Passif et capitaux propres</h2>
            <table>
                <thead><tr><th>Code</th><th>Libelle</th><th class="right">Montant</th></tr></thead>
                <tbody>
                @foreach ($report['liabilities'] as $line)
                    <tr>
                        <td>{{ $line['code'] }}</td>
                        <td>{{ $line['name'] }}</td>
                        <td class="right">{{ number_format((float) $line['balance'], 0, ',', ' ') }}</td>
                    </tr>
                @endforeach
                @foreach ($report['equity'] as $line)
                    <tr>
                        <td>{{ $line['code'] }}</td>
                        <td>{{ $line['name'] }}</td>
                        <td class="right">{{ number_format((float) $line['balance'], 0, ',', ' ') }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td>{{ $report['current_result']['code'] }}</td>
                    <td>{{ $report['current_result']['name'] }}</td>
                    <td class="right">{{ number_format((float) $report['current_result']['balance'], 0, ',', ' ') }}</td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>

    <table class="totals">
        <tr><td>Total actif</td><td class="right">{{ number_format((float) $report['asset_total'], 0, ',', ' ') }} XOF</td></tr>
        <tr class="grand-total"><td>Total passif</td><td class="right">{{ number_format((float) ($report['liability_total'] + $report['equity_total']), 0, ',', ' ') }} XOF</td></tr>
    </table>
@endsection
