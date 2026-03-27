@extends('layouts.print')

@section('title', 'Balance comptable')

@section('content')
    <div class="doc-header">
        <div>
            <h1>Balance comptable</h1>
            <div class="meta">{{ $company?->name }}
                @if ($filters['date_from'] || $filters['date_to'])
                    · Periode {{ $filters['date_from'] ? \Illuminate\Support\Carbon::parse($filters['date_from'])->format('d/m/Y') : 'Debut' }} au {{ $filters['date_to'] ? \Illuminate\Support\Carbon::parse($filters['date_to'])->format('d/m/Y') : 'Aujourd hui' }}
                @endif
            </div>
        </div>
    </div>

    <table>
        <thead>
        <tr>
            <th>Code</th>
            <th>Libelle</th>
            <th>Type</th>
            <th class="right">Debit</th>
            <th class="right">Credit</th>
            <th class="right">Solde</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($balances as $balance)
            <tr>
                <td>{{ $balance['code'] }}</td>
                <td>{{ $balance['name'] }}</td>
                <td>{{ ucfirst($balance['type']) }}</td>
                <td class="right">{{ number_format((float) $balance['total_debit'], 0, ',', ' ') }}</td>
                <td class="right">{{ number_format((float) $balance['total_credit'], 0, ',', ' ') }}</td>
                <td class="right">{{ number_format((float) $balance['balance'], 0, ',', ' ') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Debit</td><td class="right">{{ number_format((float) $summary['debit'], 0, ',', ' ') }} XOF</td></tr>
        <tr><td>Credit</td><td class="right">{{ number_format((float) $summary['credit'], 0, ',', ' ') }} XOF</td></tr>
        <tr class="grand-total"><td>Resultat</td><td class="right">{{ number_format((float) $summary['result'], 0, ',', ' ') }} XOF</td></tr>
    </table>
@endsection
