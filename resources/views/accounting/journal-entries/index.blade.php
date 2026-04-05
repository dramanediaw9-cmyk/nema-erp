@extends('layouts.app')

@section('title', 'Journaux comptables - Nema ERP')
@section('page-title', 'Journaux comptables')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">Ecritures generees par le noyau ERP</h2>
            <div class="muted">Chaque vente, achat, paiement ou depense validee genere une ecriture automatiquement.</div>
        </div>
    </div>

    <section class="card" style="margin-bottom:20px;">
        <form method="GET" action="{{ route('accounting.journal-entries.index') }}" class="form-grid" style="align-items:end; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));">
            <div style="grid-column:span 2; min-width:220px;">
                <label for="search">Recherche</label>
                <input type="text" id="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Numero, reference, description...">
            </div>
            <div>
                <label for="date_from">Date debut</label>
                <input type="date" id="date_from" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
            </div>
            <div>
                <label for="date_to">Date fin</label>
                <input type="date" id="date_to" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
            </div>
            <div>
                <label for="journal_code">Journal</label>
                <select id="journal_code" name="journal_code">
                    <option value="">Tous</option>
                    @foreach ($journalCodes as $code)
                        <option value="{{ $code }}" @selected(($filters['journal_code'] ?? null) === $code)>{{ $code }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="source_type">Source</label>
                <select id="source_type" name="source_type">
                    <option value="">Toutes</option>
                    @foreach ($sourceOptions as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['source_key'] ?? null) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="actions" style="margin-top:0; justify-content:flex-start; align-self:end;">
                <button type="submit" class="button button-primary">Filtrer</button>
                <a href="{{ route('accounting.journal-entries.index') }}" class="button button-secondary">Reinitialiser</a>
            </div>
        </form>
    </section>

    <section class="card table-wrap">
        <table>
            <thead>
            <tr>
                <th>Numero</th>
                <th>Date</th>
                <th>Journal</th>
                <th>Source</th>
                <th>Reference</th>
                <th>Description</th>
                <th>Debit</th>
                <th>Credit</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($entries as $entry)
                @php
                    $sourceLabel = match (true) {
                        (bool) $entry->is_reversal => 'Contrepassation',
                        $entry->source_type === \App\Modules\Sales\Models\SalesInvoice::class => 'Vente',
                        $entry->source_type === \App\Modules\Purchases\Models\PurchaseBill::class => 'Achat',
                        $entry->source_type === \App\Modules\Expenses\Models\Expense::class => 'Depense',
                        $entry->source_type === \App\Modules\Treasury\Models\Payment::class => 'Paiement',
                        default => 'Autre',
                    };
                @endphp
                <tr>
                    <td>
                        <strong>{{ $entry->journal_number }}</strong>
                        @if ($entry->reversalEntry)
                            <div class="muted" style="margin-top:4px; font-size:12px;">Contrepassee</div>
                        @endif
                    </td>
                    <td>{{ $entry->entry_date?->format('d/m/Y') }}</td>
                    <td>{{ $entry->journal_code }}</td>
                    <td>
                        <div>{{ $sourceLabel }}</div>
                        @if ($entry->is_reversal)
                            <span class="badge badge-warning">Controle</span>
                        @elseif ($entry->reversalEntry)
                            <span class="badge badge-muted">Archivee par contrepassation</span>
                        @endif
                    </td>
                    <td>{{ $entry->reference ?: '-' }}</td>
                    <td>
                        <div>{{ $entry->description }}</div>
                        @if ($entry->is_reversal && $entry->reversalOf)
                            <div class="muted" style="margin-top:4px; font-size:12px;">Annule l ecriture {{ $entry->reversalOf->journal_number }}</div>
                        @elseif ($entry->reversalEntry)
                            <div class="muted" style="margin-top:4px; font-size:12px;">Contrepassee par {{ $entry->reversalEntry->journal_number }}</div>
                        @endif
                    </td>
                    <td>{{ number_format((float) $entry->total_debit, 0, ',', ' ') }} XOF</td>
                    <td>{{ number_format((float) $entry->total_credit, 0, ',', ' ') }} XOF</td>
                    <td><a href="{{ route('accounting.journal-entries.show', $entry) }}">Voir</a></td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="muted">Aucune ecriture comptable disponible.</td>
                </tr>
            @endforelse
            </tbody>
        </table>

        @if (method_exists($entries, 'links'))
            <div style="margin-top:18px;">{{ $entries->links() }}</div>
        @endif
    </section>
@endsection
