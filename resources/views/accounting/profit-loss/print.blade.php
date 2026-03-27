@extends('layouts.print')

@section('title', 'Compte de resultat')

@section('content')
    <div class="doc-header">
        <div>
            <h1>Compte de resultat</h1>
            <div class="meta">{{ $company?->name }} · Periode {{ \Illuminate\Support\Carbon::parse($filters['date_from'])->format('d/m/Y') }} au {{ \Illuminate\Support\Carbon::parse($filters['date_to'])->format('d/m/Y') }}</div>
        </div>
    </div>

    <div class="grid grid-2">
        <div class="panel">
            <h2>Produits</h2>
            <table>
                <thead><tr><th>Code</th><th>Libelle</th><th class="right">Montant</th></tr></thead>
                <tbody>
                @foreach ($report['income'] as $line)
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
            <h2>Charges</h2>
            <table>
                <thead><tr><th>Code</th><th>Libelle</th><th class="right">Montant</th></tr></thead>
                <tbody>
                @foreach ($report['expenses'] as $line)
                    <tr>
                        <td>{{ $line['code'] }}</td>
                        <td>{{ $line['name'] }}</td>
                        <td class="right">{{ number_format((float) $line['balance'], 0, ',', ' ') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <table class="totals">
        <tr><td>Total produits</td><td class="right">{{ number_format((float) $report['income_total'], 0, ',', ' ') }} XOF</td></tr>
        <tr><td>Total charges</td><td class="right">{{ number_format((float) $report['expense_total'], 0, ',', ' ') }} XOF</td></tr>
        <tr class="grand-total"><td>Resultat net</td><td class="right">{{ number_format((float) $report['net_result'], 0, ',', ' ') }} XOF</td></tr>
    </table>
@endsection
