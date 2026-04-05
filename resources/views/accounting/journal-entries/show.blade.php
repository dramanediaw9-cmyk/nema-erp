@extends('layouts.app')

@section('title', 'Detail ecriture - Nema ERP')
@section('page-title', 'Ecriture '.$entry->journal_number)

@section('content')
    @php
        $canReverse = ! $entry->is_reversal && ! $entry->reversalEntry;
        $backSearch = $entry->reference ?: $entry->journal_number;
    @endphp

    <div class="page-head">
        <div>
            <h2 style="margin:0;">{{ $entry->description }}</h2>
            <div class="muted" style="margin-top:6px;">
                {{ $entry->entry_date?->format('d/m/Y') }} · Journal {{ $entry->journal_code }} · Reference {{ $entry->reference ?: 'N/A' }}
            </div>
            <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:10px;">
                <span class="badge {{ $entry->is_reversal ? 'badge-warning' : 'badge-success' }}">{{ $entry->is_reversal ? 'Contrepassation' : 'Postee' }}</span>
                @if ($entry->reversalEntry)
                    <span class="badge badge-muted">Deja contrepassee</span>
                @endif
            </div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="{{ route('accounting.journal-entries.index', ['search' => $backSearch]) }}" class="button button-secondary">Retour aux journaux</a>
            @if ($sourceContext)
                <a href="{{ $sourceContext['url'] }}" class="button button-primary">Ouvrir le document source</a>
            @endif
        </div>
    </div>

    @if ($errors->has('journal_entry'))
        <div class="card" style="margin-bottom:20px; border-left:4px solid #b91c1c;">
            <strong>Controle comptable</strong>
            <div style="margin-top:8px; color:#b91c1c;">{{ $errors->first('journal_entry') }}</div>
        </div>
    @endif

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

    @if ($entry->is_reversal && $entry->reversalOf)
        <section class="card" style="margin-bottom:20px;">
            <h2 style="margin-top:0;">Contrepassation controlee</h2>
            <div style="display:flex; justify-content:space-between; gap:18px; align-items:flex-start; flex-wrap:wrap;">
                <div>
                    <div style="font-weight:600;">Cette ecriture annule {{ $entry->reversalOf->journal_number }}</div>
                    <div class="muted" style="margin-top:8px;">Motif : {{ $entry->reversal_reason ?: 'Motif non renseigne' }}</div>
                </div>
                <a href="{{ route('accounting.journal-entries.show', $entry->reversalOf) }}" class="button button-secondary">Voir l ecriture d origine</a>
            </div>
        </section>
    @elseif ($entry->reversalEntry)
        <section class="card" style="margin-bottom:20px;">
            <h2 style="margin-top:0;">Statut de l ecriture</h2>
            <div style="display:flex; justify-content:space-between; gap:18px; align-items:flex-start; flex-wrap:wrap;">
                <div>
                    <div style="font-weight:600;">Cette ecriture a ete contrepassee par {{ $entry->reversalEntry->journal_number }}</div>
                    <div class="muted" style="margin-top:8px;">Motif : {{ $entry->reversalEntry->reversal_reason ?: 'Motif non renseigne' }}</div>
                </div>
                <a href="{{ route('accounting.journal-entries.show', $entry->reversalEntry) }}" class="button button-secondary">Voir la contrepassation</a>
            </div>
        </section>
    @endif

    @allowed('accounting.reverse')
        @if ($canReverse)
            <section class="card" style="margin-bottom:20px;">
                <h2 style="margin-top:0;">Annulation controlee</h2>
                <div class="muted" style="margin-bottom:14px;">La suppression est interdite. Utilise une contrepassation motivee pour neutraliser cette ecriture sans perdre la trace comptable.</div>
                <form method="POST" action="{{ route('accounting.journal-entries.reverse', $entry) }}" class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); align-items: start;">
                    @csrf
                    <div>
                        <label for="reversal_date">Date de contrepassation</label>
                        <input type="date" id="reversal_date" name="reversal_date" value="{{ old('reversal_date', now()->toDateString()) }}">
                        @error('reversal_date')
                            <div style="margin-top:6px; color:#b91c1c; font-size:12px;">{{ $message }}</div>
                        @enderror
                    </div>
                    <div style="grid-column: span 2; min-width: 260px;">
                        <label for="reversal_reason">Motif</label>
                        <textarea id="reversal_reason" name="reversal_reason" rows="3" placeholder="Explique pourquoi cette ecriture doit etre annulee.">{{ old('reversal_reason') }}</textarea>
                        @error('reversal_reason')
                            <div style="margin-top:6px; color:#b91c1c; font-size:12px;">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="actions" style="margin-top:0; justify-content:flex-start; align-self:end;">
                        <button type="submit" class="button button-primary">Contrepasser l ecriture</button>
                    </div>
                </form>
            </section>
        @endif
    @endallowed

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
