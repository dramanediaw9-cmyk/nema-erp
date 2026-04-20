@extends('layouts.app')

@section('title', 'Detail depense - Nema ERP')
@section('page-title', 'Depense '.$expense->expense_number)

@section('content')
    @php($paymentMethodOptions = $paymentMethodOptions ?? \App\Support\PaymentMethodCatalog::options())
    @php
        $workflowLabel = match ($expense->status) {
            'validated' => 'Approuvee',
            'rejected' => 'Rejetee',
            default => 'En attente',
        };
    @endphp

    <div class="page-head">
        <div>
            <h2 style="margin:0;">{{ $expense->description }}</h2>
            <div class="muted">Depense du {{ $expense->expense_date?->format('d/m/Y') }} · Agence {{ $expense->branch?->name }}</div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="{{ route('expenses.print', $expense) }}" class="button button-secondary" target="_blank">PDF</a>
            @allowed('accounting.view')
                <a href="{{ route('accounting.journal-entries.index', ['source_type' => 'expenses', 'search' => $expense->expense_number]) }}" class="button button-secondary">Voir les ecritures</a>
            @endallowed
            @if ($expense->status === 'pending_approval')
                @allowed('expenses.approve')
                    <form method="POST" action="{{ route('expenses.approve', $expense) }}">
                        @csrf
                        <button type="submit" class="button button-primary">Valider l etape suivante</button>
                    </form>
                    <form method="POST" action="{{ route('expenses.reject', $expense) }}" style="display:grid; gap:8px;">
                        @csrf
                        <input type="text" name="rejection_reason" maxlength="1000" required placeholder="Motif du rejet">
                        <button type="submit" class="button button-secondary">Rejeter avec motif</button>
                    </form>
                @endallowed
            @endif
        </div>
    </div>

    <section class="card" style="margin-bottom:20px;">
        <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
            <a href="{{ route('expenses.index', ['search' => $expense->expense_number]) }}" class="card" style="padding:16px; display:block;">
                <strong>Retour aux depenses</strong>
                <div class="muted" style="margin-top:8px;">Retrouver cette depense dans la liste filtree.</div>
            </a>
            @allowed('accounting.view')
                <a href="{{ route('accounting.journal-entries.index', ['source_type' => 'expenses', 'search' => $expense->expense_number]) }}" class="card" style="padding:16px; display:block;">
                    <strong>Ecriture comptable</strong>
                    <div class="muted" style="margin-top:8px;">Ouvrir directement les journaux lies a cette depense.</div>
                </a>
            @endallowed
            <a href="#accounting-effects" class="card" style="padding:16px; display:block;">
                <strong>Impact comptable</strong>
                <div class="muted" style="margin-top:8px;">Voir l ecriture generee et sa trace detaillee.</div>
            </a>
            <a href="#general-info" class="card" style="padding:16px; display:block;">
                <strong>Informations generales</strong>
                <div class="muted" style="margin-top:8px;">Revenir rapidement aux donnees de saisie et aux notes.</div>
            </a>
        </div>
    </section>

    <div class="grid stats-grid" style="margin-bottom:20px;">
        <div class="card"><div class="muted">Workflow</div><div class="stat-value" style="font-size:24px;">{{ $workflowLabel }}</div></div>
        <div class="card"><div class="muted">Montant</div><div class="stat-value">{{ number_format((float) $expense->total, 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Statut paiement</div><div class="stat-value" style="font-size:24px;">{{ $expense->payment_status === 'paid' ? 'Payee' : 'Non payee' }}</div></div>
        <div class="card"><div class="muted">Categorie</div><div class="stat-value" style="font-size:24px;">{{ $expense->category?->name }}</div></div>
    </div>

    <div class="split" style="margin-bottom:20px;">
        <section class="card">
            <h2 style="margin-top:0;">Validation</h2>
            <div class="grid">
                <div><strong>Statut</strong><div class="muted">{{ $expense->status === 'validated' ? 'Approuvee' : ($expense->status === 'rejected' ? 'Rejetee' : 'En attente d approbation') }}</div></div>
                <div><strong>Creee par</strong><div class="muted">{{ $expense->creator?->name ?? 'Systeme' }}</div></div>
                <div><strong>Approuvee par</strong><div class="muted">{{ $expense->approver?->name ?? 'Non approuvee' }}</div></div>
                <div><strong>Date d approbation</strong><div class="muted">{{ $expense->approved_at?->format('d/m/Y H:i') ?? 'Non disponible' }}</div></div>
                <div><strong>Rejetee par</strong><div class="muted">{{ $expense->rejector?->name ?? 'Non rejetee' }}</div></div>
                <div><strong>Date de rejet</strong><div class="muted">{{ $expense->rejected_at?->format('d/m/Y H:i') ?? 'Non disponible' }}</div></div>
                <div style="grid-column:1 / -1;"><strong>Motif du rejet</strong><div class="muted">{{ $expense->rejection_reason ?: 'Aucun motif enregistre' }}</div></div>
            </div>
            @include('partials.approval-steps', ['approvalSteps' => $expense->approvalSteps])
        </section>

        <section class="card">
            <h2 style="margin-top:0;">Paiement</h2>
            <div class="grid">
                <div><strong>Statut</strong><div class="muted">{{ $expense->payment_status === 'paid' ? 'Payee' : 'Non payee' }}</div></div>
                <div><strong>Compte</strong><div class="muted">{{ $expense->cashAccount?->name ?? 'Aucun' }}</div></div>
                <div><strong>Date de paiement</strong><div class="muted">{{ $expense->payment_date?->format('d/m/Y') ?? 'Non renseignee' }}</div></div>
                <div><strong>Mode</strong><div class="muted">{{ $expense->payment_method ? ($paymentMethodOptions[$expense->payment_method] ?? str($expense->payment_method)->replace('_', ' ')->title()) : 'Non renseigne' }}</div></div>
                <div><strong>Reference</strong><div class="muted">{{ $expense->payment_reference ?: 'Aucune' }}</div></div>
                <div><strong>Effet comptable</strong><div class="muted">{{ $expense->status === 'validated' ? 'Ecriture generee' : ($expense->status === 'rejected' ? 'Aucune ecriture finale' : 'Ecriture en attente') }}</div></div>
            </div>
        </section>
    </div>

    <div class="split">
        <section class="card" id="general-info">
            <h2 style="margin-top:0;">Informations generales</h2>
            <div class="grid">
                <div><strong>Description</strong><div class="muted">{{ $expense->description }}</div></div>
                <div><strong>Agence</strong><div class="muted">{{ $expense->branch?->name }}</div></div>
                <div><strong>Fournisseur</strong><div class="muted">{{ $expense->supplier?->name ?? 'Non renseigne' }}</div></div>
                <div><strong>Saisi par</strong><div class="muted">{{ $expense->creator?->name ?? 'Systeme' }}</div></div>
                <div><strong>Notes</strong><div class="muted">{{ $expense->notes ?: 'Aucune note' }}</div></div>
            </div>
        </section>

        <section class="card" id="accounting-effects">
            <h2 style="margin-top:0;">Ecritures comptables liees</h2>
            @forelse ($journalEntries as $entry)
                <div style="padding-bottom: 14px; border-bottom: 1px solid #efe4d3; margin-bottom:14px;">
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
                <p class="muted">Aucune ecriture comptable liee a cette depense pour le moment.</p>
            @endforelse
        </section>
    </div>
    @include('partials.document-collaboration', ['document' => $expense, 'documentType' => 'expense', 'managePermission' => 'expenses.manage'])
@endsection
