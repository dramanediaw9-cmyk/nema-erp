@extends('layouts.print')

@section('title', 'Depense '.$expense->expense_number.' - Nema ERP')

@section('content')
    <section class="doc-header">
        <div>
            <div class="doc-chip">Fiche de depense</div>
            <h1>{{ $expense->expense_number }}</h1>
            <div><strong>{{ $expense->company?->legal_name ?: $expense->company?->name }}</strong></div>
            <div class="meta">{{ $expense->company?->address }}</div>
            <div class="meta">Tel : {{ $expense->company?->phone ?: 'N/A' }} @if($expense->company?->email)· {{ $expense->company?->email }} @endif</div>
            <div class="meta">NIF : {{ $expense->company?->nif ?: 'N/A' }} · RCCM : {{ $expense->company?->rccm ?: 'N/A' }}</div>
        </div>
        <div class="right">
            <div><strong>Date :</strong> {{ $expense->expense_date?->format('d/m/Y') }}</div>
            <div class="meta">Agence : {{ $expense->branch?->name }}</div>
            <div class="meta">Categorie : {{ $expense->category?->name }}</div>
            <div class="meta">Workflow : {{ $expense->status === 'validated' ? 'Approuvee' : ($expense->status === 'rejected' ? 'Rejetee' : 'En attente') }}</div>
            <div class="meta">Devise : {{ $expense->company?->currency_code ?: 'XOF' }}</div>
        </div>
    </section>

    <section class="grid grid-2">
        <div class="panel">
            <h2>Informations</h2>
            <div><strong>Description :</strong> {{ $expense->description }}</div>
            <div><strong>Fournisseur :</strong> {{ $expense->supplier?->name ?: 'Non renseigne' }}</div>
            <div><strong>Saisi par :</strong> {{ $expense->creator?->name ?: 'Systeme' }}</div>
            <div><strong>Approuvee par :</strong> {{ $expense->approver?->name ?: 'Non approuvee' }}</div>
            <div><strong>Rejetee par :</strong> {{ $expense->rejector?->name ?: 'Non rejetee' }}</div>
        </div>
        <div class="panel">
            <h2>Situation</h2>
            <div><strong>Workflow :</strong> {{ $expense->status === 'validated' ? 'Approuvee' : ($expense->status === 'rejected' ? 'Rejetee' : 'En attente d approbation') }}</div>
            <div><strong>Statut paiement :</strong> {{ $expense->payment_status === 'paid' ? 'Payee' : 'Non payee' }}</div>
            <div><strong>Compte :</strong> {{ $expense->cashAccount?->name ?: 'Aucun' }}</div>
            <div><strong>Date de paiement :</strong> {{ $expense->payment_date?->format('d/m/Y') ?: 'Non renseignee' }}</div>
        </div>
    </section>

    <table class="totals" style="margin-top:28px; width:420px;">
        <tr>
            <td>Montant total</td>
            <td class="right">{{ number_format((float) $expense->total, 0, ',', ' ') }} XOF</td>
        </tr>
        <tr>
            <td>Reference paiement</td>
            <td class="right">{{ $expense->payment_reference ?: 'Aucune' }}</td>
        </tr>
        <tr class="grand-total">
            <td>Workflow</td>
            <td class="right">{{ $expense->status === 'validated' ? 'Approuvee' : ($expense->status === 'rejected' ? 'Rejetee' : 'En attente') }}</td>
        </tr>
    </table>

    @if ($expense->notes)
        <div class="footer">
            <strong>Notes :</strong> {{ $expense->notes }}
        </div>
    @endif

    <div class="signatures">
        <div class="signature-box">Validation responsable</div>
        <div class="signature-box">Visa caisse / comptabilite</div>
    </div>
@endsection
