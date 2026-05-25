@extends('layouts.app')

@section('title', 'Rapprochement '.$reconciliation->reconciliation_number.' - Nema ERP')
@section('page-title', 'Rapprochement '.$reconciliation->reconciliation_number)

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">{{ $reconciliation->cashAccount?->name }}</h2>
            <div class="muted">Releve du {{ $reconciliation->statement_date?->format('d/m/Y') }} · {{ $reconciliation->statement_reference ?: 'Sans reference' }}</div>
        </div>
        <a href="{{ route('treasury-reconciliations.index') }}" class="button button-secondary">Retour</a>
    </div>

    <div class="grid stats-grid" style="margin-bottom:20px;">
        <div class="card"><div class="muted">Statut</div><div class="stat-value" style="font-size:24px;">{{ $reconciliation->status === 'balanced' ? 'Equilibre' : 'Avec ecart' }}</div></div>
        <div class="card"><div class="muted">Solde comptable</div><div class="stat-value">{{ number_format((float) $reconciliation->book_balance, 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Solde releve</div><div class="stat-value">{{ number_format((float) $reconciliation->statement_balance, 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Ecart</div><div class="stat-value">{{ number_format((float) $reconciliation->difference, 0, ',', ' ') }}</div></div>
    </div>

    <div class="split" style="margin-bottom:20px;">
        <section class="card">
            <h2 style="margin-top:0;">Resume</h2>
            <div class="grid">
                <div><strong>Numero</strong><div class="muted">{{ $reconciliation->reconciliation_number }}</div></div>
                <div><strong>Compte</strong><div class="muted">{{ $reconciliation->cashAccount?->name }}</div></div>
                <div><strong>Mouvements rapproches</strong><div class="muted">{{ $reconciliation->payments_count }}</div></div>
                <div><strong>Total rapproche</strong><div class="muted">{{ number_format((float) $reconciliation->matched_total, 0, ',', ' ') }} XOF</div></div>
                <div><strong>Agence</strong><div class="muted">{{ $reconciliation->branch?->name ?: 'Toutes agences' }}</div></div>
                <div><strong>Cree par</strong><div class="muted">{{ $reconciliation->creator?->name ?: 'Systeme' }}</div></div>
            </div>
            @if ($reconciliation->notes)
                <div class="muted" style="margin-top:14px;">{{ $reconciliation->notes }}</div>
            @endif
        </section>

        <section class="card">
            <h2 style="margin-top:0;">Lecture de controle</h2>
            <ul class="summary-list">
                <li>Le solde comptable est calcule a partir du solde d ouverture et des paiements du compte jusqu a la date du releve.</li>
                <li>Seuls les mouvements selectionnes apparaissent comme rapproches sur cette fiche.</li>
                <li>Un ecart non nul signale un controle a reprendre sur le releve ou les mouvements manquants.</li>
            </ul>
        </section>
    </div>

    <section class="card table-wrap">
        <h2 style="margin-top:0;">Mouvements rapproches</h2>
        <table>
            <thead>
            <tr>
                <th>Numero</th>
                <th>Date</th>
                <th>Tiers</th>
                <th>Type</th>
                <th>Montant</th>
                <th>Document</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($reconciliation->items as $item)
                @php $payment = $item->payment; $allocation = $payment?->allocations->first(); $document = $allocation?->allocatable; @endphp
                <tr>
                    <td><a href="{{ route('payments.show', $payment) }}">{{ $payment->payment_number }}</a></td>
                    <td>{{ $payment->payment_date?->format('d/m/Y') }}</td>
                    <td>{{ $payment->partner?->name ?: 'Sans tiers' }}</td>
                    <td>{{ match ($payment->payment_type) {
                        'customer_refund' => 'Remboursement client',
                        'supplier_payment' => 'Reglement fournisseur',
                        'pos_refund' => 'Remboursement POS',
                        'internal_transfer' => $payment->direction === 'in' ? 'Reception de versement' : 'Versement interne',
                        default => 'Encaissement client',
                    } }}</td>
                    <td>{{ $payment->direction === 'out' ? '-' : '+' }}{{ number_format((float) $payment->amount, 0, ',', ' ') }} XOF</td>
                    <td>
                        @if ($document instanceof \App\Modules\Sales\Models\SalesInvoice)
                            <a href="{{ route('sales.show', $document) }}">{{ $document->invoice_number }}</a>
                        @elseif ($document instanceof \App\Modules\Purchases\Models\PurchaseBill)
                            <a href="{{ route('purchases.show', $document) }}">{{ $document->bill_number }}</a>
                        @else
                            <span class="muted">Sans document</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>
@endsection
