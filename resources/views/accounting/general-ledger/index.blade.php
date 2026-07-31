@extends('layouts.app')

@section('title', 'Grand livre - Nema ERP')
@section('page-title', 'Grand livre')

@section('content')
    <div class="erp-work-page">
        <section class="erp-work-toolbar">
            <div class="erp-work-toolbar__context">
                <div>
                    <strong>Grand livre comptable</strong>
                    <div class="muted">Lignes d’écriture par compte sur la période.</div>
                </div>
            </div>
            <div class="erp-work-toolbar__actions">
                <a href="{{ route('accounting.general-ledger.export', request()->query()) }}" class="button button-secondary">Exporter CSV</a>
                <a href="{{ route('accounting.general-ledger.print', request()->query()) }}" class="button button-secondary" target="_blank">PDF</a>
            </div>
        </section>

        <details class="card erp-filter-panel" @if(request()->hasAny(['date_from', 'date_to', 'account_id'])) open @endif>
            <summary>Filtres du grand livre</summary>
            <div class="erp-filter-panel__body">
                <form method="GET" action="{{ route('accounting.general-ledger.index') }}" class="form-grid" style="align-items:end; grid-template-columns: repeat(4, minmax(0, 1fr));">
                    <div>
                        <label for="date_from">Date début</label>
                        <input type="date" id="date_from" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
                    </div>
                    <div>
                        <label for="date_to">Date fin</label>
                        <input type="date" id="date_to" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
                    </div>
                    <div>
                        <label for="account_id">Compte</label>
                        <select id="account_id" name="account_id">
                            <option value="">Tous les comptes</option>
                            @foreach ($accounts as $account)
                                <option value="{{ $account->id }}" @selected((string) ($filters['account_id'] ?? '') === (string) $account->id)>{{ $account->code }} - {{ $account->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="actions" style="margin-top:0; justify-content:flex-start; align-self:end;">
                        <button type="submit" class="button button-primary">Filtrer</button>
                        <a href="{{ route('accounting.general-ledger.index') }}" class="button button-secondary">Réinitialiser</a>
                    </div>
                </form>
            </div>
        </details>

        <div class="erp-kpi-strip">
            <div class="card erp-kpi-card"><div class="label">Débit</div><div class="value">{{ number_format((float) $summary['debit'], 0, ',', ' ') }} XOF</div></div>
            <div class="card erp-kpi-card"><div class="label">Crédit</div><div class="value">{{ number_format((float) $summary['credit'], 0, ',', ' ') }} XOF</div></div>
        </div>

        <section class="card table-wrap">
        <table>
            <thead>
            <tr>
                <th>Date</th>
                <th>Journal</th>
                <th>Compte</th>
                <th>Libelle</th>
                <th>Tiers</th>
                <th>Debit</th>
                <th>Credit</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($lines as $line)
                <tr>
                    <td>{{ $line->journalEntry?->entry_date?->format('d/m/Y') }}</td>
                    <td>
                        <div style="font-weight:600;">{{ $line->journalEntry?->journal_code }}</div>
                        <div class="muted" style="font-size:14px;">{{ $line->journalEntry?->journal_number }}</div>
                    </td>
                    <td>{{ $line->account?->code }} - {{ $line->account?->name }}</td>
                    <td>{{ $line->label ?: ($line->journalEntry?->description ?? '') }}</td>
                    <td>{{ $line->partner?->name ?? 'N/A' }}</td>
                    <td>{{ number_format((float) $line->debit, 0, ',', ' ') }} XOF</td>
                    <td>{{ number_format((float) $line->credit, 0, ',', ' ') }} XOF</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="muted">Aucune ligne comptable pour ce filtre.</td>
                </tr>
            @endforelse
            </tbody>
        </table>

        @if (method_exists($lines, 'links'))
            <div style="margin-top:18px;">{{ $lines->links() }}</div>
        @endif
        </section>
    </div>
@endsection
