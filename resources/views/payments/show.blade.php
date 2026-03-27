@extends('layouts.app')

@section('title', 'Detail paiement - Nema ERP')
@section('page-title', 'Paiement '.$payment->payment_number)

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">{{ $payment->partner?->name ?? 'Tiers non renseigne' }}</h2>
            <div class="muted">{{ $payment->payment_date?->format('d/m/Y') }} · {{ $payment->branch?->name }} · {{ $paymentTypeLabel }}</div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="{{ route('payments.index', ['search' => $payment->payment_number]) }}" class="button button-secondary">Retour aux paiements</a>
            @allowed('accounting.view')
                <a href="{{ route('accounting.journal-entries.index', ['source_type' => 'payments', 'search' => $payment->payment_number]) }}" class="button button-secondary">Voir les ecritures</a>
            @endallowed
        </div>
    </div>

    <section class="card" style="margin-bottom:20px;">
        <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
            <a href="#linked-documents" class="card" style="padding:16px; display:block;">
                <strong>Documents lies</strong>
                <div class="muted" style="margin-top:8px;">Voir les factures couvertes par ce paiement.</div>
            </a>
            <a href="#accounting-effects" class="card" style="padding:16px; display:block;">
                <strong>Impact comptable</strong>
                <div class="muted" style="margin-top:8px;">Ouvrir les ecritures generees par ce flux de tresorerie.</div>
            </a>
            <a href="{{ route('cash-accounts.index') }}" class="card" style="padding:16px; display:block;">
                <strong>Compte de tresorerie</strong>
                <div class="muted" style="margin-top:8px;">Revenir au parametrage du compte utilise.</div>
            </a>
        </div>
    </section>

    <div class="grid stats-grid" style="margin-bottom:20px;">
        <div class="card"><div class="muted">Montant</div><div class="stat-value">{{ number_format((float) $payment->amount, 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Mode</div><div class="stat-value" style="font-size:24px;">{{ $methodOptions[$payment->method] ?? str($payment->method)->replace('_', ' ')->title() }}</div></div>
        <div class="card"><div class="muted">Compte</div><div class="stat-value" style="font-size:24px;">{{ $payment->cashAccount?->name ?? 'N/A' }}</div></div>
        <div class="card"><div class="muted">Documents lies</div><div class="stat-value">{{ $linkedDocuments->count() }}</div></div>
    </div>

    <div class="split" style="margin-bottom:20px;">
        <section class="card">
            <h2 style="margin-top:0;">Informations generales</h2>
            <div class="grid">
                <div><strong>Numero</strong><div class="muted">{{ $payment->payment_number }}</div></div>
                <div><strong>Type</strong><div class="muted">{{ $paymentTypeLabel }}</div></div>
                <div><strong>Date</strong><div class="muted">{{ $payment->payment_date?->format('d/m/Y') }}</div></div>
                <div><strong>Saisi par</strong><div class="muted">{{ $payment->creator?->name ?? 'Systeme' }}</div></div>
                <div><strong>Tiers</strong><div class="muted">{{ $payment->partner?->name ?? 'Non renseigne' }}</div></div>
                <div><strong>Reference externe</strong><div class="muted">{{ $payment->reference ?: 'Aucune' }}</div></div>
                <div><strong>Agence</strong><div class="muted">{{ $payment->branch?->name ?? 'N/A' }}</div></div>
                <div><strong>Session POS</strong><div class="muted">{{ $payment->posSession?->session_number ?? 'Aucune' }}</div></div>
                <div><strong>Notes</strong><div class="muted">{{ $payment->notes ?: 'Aucune note' }}</div></div>
            </div>
        </section>

        <section class="card">
            <h2 style="margin-top:0;">Lecture metier</h2>
            <div class="grid">
                <div><strong>Sens de flux</strong><div class="muted">{{ $payment->direction === 'out' ? 'Sortie de tresorerie' : 'Entree de tresorerie' }}</div></div>
                <div><strong>Compte impacte</strong><div class="muted">{{ $payment->cashAccount?->name ?? 'Non renseigne' }}</div></div>
                <div><strong>Pieces rattachees</strong><div class="muted">{{ $linkedDocuments->count() }} document(s) lie(s)</div></div>
                <div><strong>Impact comptable</strong><div class="muted">{{ $journalEntries->isNotEmpty() ? 'Ecriture generee' : 'Aucune ecriture detectee' }}</div></div>
            </div>
        </section>
    </div>

    <div class="split">
        <section class="card" id="linked-documents">
            <h2 style="margin-top:0;">Documents lies</h2>
            @forelse ($linkedDocuments as $document)
                <div style="padding-bottom:14px; border-bottom:1px solid #efe4d3; margin-bottom:14px;">
                    <div style="display:flex; justify-content:space-between; gap:12px; align-items:flex-start; flex-wrap:wrap;">
                        <div>
                            <div style="font-weight:600;">{{ $document['label'] }} {{ $document['number'] }}</div>
                            <div class="muted" style="margin-top:6px;">{{ $document['date']?->format('d/m/Y') ?? 'Date non renseignee' }}</div>
                            <div style="margin-top:6px;">Affectation : {{ number_format((float) $document['allocated_amount'], 0, ',', ' ') }} XOF</div>
                            <div class="muted" style="margin-top:6px;">Montant document : {{ number_format((float) $document['amount'], 0, ',', ' ') }} XOF</div>
                        </div>
                        <a href="{{ $document['url'] }}" class="button button-secondary">Ouvrir le document</a>
                    </div>
                </div>
            @empty
                <p class="muted">Aucun document n est rattache a ce paiement.</p>
            @endforelse
        </section>

        <section class="card" id="accounting-effects">
            <h2 style="margin-top:0;">Ecritures comptables liees</h2>
            @forelse ($journalEntries as $entry)
                <div style="padding-bottom:14px; border-bottom:1px solid #efe4d3; margin-bottom:14px;">
                    <div style="display:flex; justify-content:space-between; gap:12px; align-items:flex-start; flex-wrap:wrap;">
                        <div>
                            <div style="font-weight:600;">{{ $entry->journal_number }}</div>
                            <div class="muted" style="margin-top:6px;">{{ $entry->entry_date?->format('d/m/Y') }} · {{ $entry->description }}</div>
                            <div style="margin-top:6px;">Debit {{ number_format((float) $entry->total_debit, 0, ',', ' ') }} XOF · Credit {{ number_format((float) $entry->total_credit, 0, ',', ' ') }} XOF</div>
                        </div>
                        @allowed('accounting.view')
                            <a href="{{ route('accounting.journal-entries.show', $entry) }}" class="button button-secondary">Ouvrir</a>
                        @endallowed
                    </div>
                </div>
            @empty
                <p class="muted">Aucune ecriture comptable liee a ce paiement pour le moment.</p>
            @endforelse
        </section>
    </div>
@endsection
