@extends('layouts.app')

@section('title', 'Detail ecriture - Nema ERP')
@section('page-title', 'Ecriture '.$entry->journal_number)

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">{{ $entry->description }}</h2>
            <div class="muted">{{ $entry->entry_date?->format('d/m/Y') }} · Journal {{ $entry->journal_code }} · Reference {{ $entry->reference ?: 'N/A' }}</div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="{{ route('accounting.journal-entries.index', ['search' => $entry->reference]) }}" class="button button-secondary">Retour aux journaux</a>
            @if ($sourceContext)
                <a href="{{ $sourceContext['url'] }}" class="button button-primary">Ouvrir le document source</a>
            @endif
        </div>
    </div>

    <div class="grid stats-grid" style="margin-bottom:20px;">
        <div class="card"><div class="muted">Debit total</div><div class="stat-value">{{ number_format((float) $entry->total_debit, 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Credit total</div><div class="stat-value">{{ number_format((float) $entry->total_credit, 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Agence</div><div class="stat-value" style="font-size:22px;">{{ $entry->branch?->name ?? 'N/A' }}</div></div>
        <div class="card"><div class="muted">Saisie par</div><div class="stat-value" style="font-size:22px;">{{ $entry->creator?->name ?? 'Systeme' }}</div></div>
    </div>

    @if ($sourceContext)
        <section class="card" style="margin-bottom:20px;">
            <h2 style="margin-top:0;">Source liee</h2>
            <div style="display:flex; justify-content:space-between; gap:18px; align-items:flex-start; flex-wrap:wrap;">
                <div>
                    <div style="font-weight:600;">{{ $sourceContext['label'] }} {{ $sourceContext['number'] }}</div>
                    @if (! empty($sourceContext['hint']))
                        <div class="muted" style="margin-top:8px;">{{ $sourceContext['hint'] }}</div>
                    @endif
                </div>
                <a href="{{ $sourceContext['url'] }}" class="button button-secondary">Voir le detail</a>
            </div>
        </section>
    @endif

    <section class="card table-wrap">
        <table>
            <thead>
            <tr>
                <th>Compte</th>
                <th>Libelle</th>
                <th>Tiers</th>
                <th>Debit</th>
                <th>Credit</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($entry->lines as $line)
                <tr>
                    <td><strong>{{ $line->account?->code }}</strong> - {{ $line->account?->name }}</td>
                    <td>{{ $line->label }}</td>
                    <td>{{ $line->partner?->name ?? 'N/A' }}</td>
                    <td>{{ number_format((float) $line->debit, 0, ',', ' ') }} XOF</td>
                    <td>{{ number_format((float) $line->credit, 0, ',', ' ') }} XOF</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>
@endsection
