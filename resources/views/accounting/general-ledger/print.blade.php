@extends('layouts.print')

@section('title', 'Grand livre')

@section('content')
    <div class="doc-header">
        <div>
            <h1>Grand livre</h1>
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
            <th>Date</th>
            <th>Journal</th>
            <th>Compte</th>
            <th>Libelle</th>
            <th>Tiers</th>
            <th class="right">Debit</th>
            <th class="right">Credit</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($lines as $line)
            <tr>
                <td>{{ $line->journalEntry?->entry_date?->format('d/m/Y') }}</td>
                <td>{{ $line->journalEntry?->journal_code }} / {{ $line->journalEntry?->journal_number }}</td>
                <td>{{ $line->account?->code }} - {{ $line->account?->name }}</td>
                <td>{{ $line->label ?: ($line->journalEntry?->description ?? '') }}</td>
                <td>{{ $line->partner?->name ?? 'N/A' }}</td>
                <td class="right">{{ number_format((float) $line->debit, 0, ',', ' ') }}</td>
                <td class="right">{{ number_format((float) $line->credit, 0, ',', ' ') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Debit</td><td class="right">{{ number_format((float) $summary['debit'], 0, ',', ' ') }} XOF</td></tr>
        <tr class="grand-total"><td>Credit</td><td class="right">{{ number_format((float) $summary['credit'], 0, ',', ' ') }} XOF</td></tr>
    </table>
@endsection
