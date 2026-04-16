@extends('layouts.app')

@section('title', 'Grand livre - Nema ERP')
@section('page-title', 'Grand livre')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">Grand livre comptable</h2>
            <div class="muted">Vue detaillee des lignes d ecriture par compte sur la periode.</div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="{{ route('accounting.general-ledger.export', request()->query()) }}" class="button button-secondary">Exporter CSV</a>
            <a href="{{ route('accounting.general-ledger.print', request()->query()) }}" class="button button-secondary" target="_blank">PDF</a>
        </div>
    </div>

    <section class="card" style="margin-bottom:20px;">
        <form method="GET" action="{{ route('accounting.general-ledger.index') }}" class="form-grid" style="align-items:end; grid-template-columns: repeat(4, minmax(0, 1fr));">
            <div>
                <label for="date_from">Date debut</label>
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
                <a href="{{ route('accounting.general-ledger.index') }}" class="button button-secondary">Reinitialiser</a>
            </div>
        </form>
    </section>

    <div class="grid stats-grid" style="margin-bottom:20px;">
        <div class="card"><div class="muted">Debit</div><div class="stat-value">{{ number_format((float) $summary['debit'], 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Credit</div><div class="stat-value">{{ number_format((float) $summary['credit'], 0, ',', ' ') }}</div></div>
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
@endsection
