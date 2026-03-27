@extends('layouts.print')

@section('title', 'Facture '.$invoice->invoice_number.' - Nema ERP')

@section('content')
    <section class="doc-header">
        <div>
            <div class="doc-chip">Facture client</div>
            <h1>{{ $invoice->invoice_number }}</h1>
            <div><strong>{{ $invoice->company?->legal_name ?: $invoice->company?->name }}</strong></div>
            <div class="meta">{{ $invoice->company?->address }}</div>
            <div class="meta">Tel : {{ $invoice->company?->phone ?: 'N/A' }} @if($invoice->company?->email)· {{ $invoice->company?->email }} @endif</div>
            <div class="meta">NIF : {{ $invoice->company?->nif ?: 'N/A' }} · RCCM : {{ $invoice->company?->rccm ?: 'N/A' }}</div>
        </div>
        <div class="right">
            <div><strong>Date :</strong> {{ $invoice->invoice_date?->format('d/m/Y') }}</div>
            <div class="meta">Echeance : {{ $invoice->due_date?->format('d/m/Y') ?: 'Non definie' }}</div>
            <div class="meta">Agence : {{ $invoice->branch?->name }}</div>
            <div class="meta">Workflow : {{ $invoice->status === 'validated' ? 'Approuvee' : 'En attente' }}</div>
            <div class="meta">Devise : {{ $invoice->company?->currency_code ?: 'XOF' }}</div>
        </div>
    </section>

    <section class="grid grid-2">
        <div class="panel">
            <h2>Client facture</h2>
            <div><strong>{{ $invoice->customer?->name }}</strong></div>
            <div class="muted">Code : {{ $invoice->customer?->code ?: 'N/A' }}</div>
            <div class="muted">Adresse : {{ $invoice->customer?->address ?: 'Non renseignee' }}</div>
            <div class="muted">Telephone : {{ $invoice->customer?->phone ?: 'Non renseigne' }}</div>
            <div class="muted">Email : {{ $invoice->customer?->email ?: 'Non renseigne' }}</div>
        </div>
        <div class="panel">
            <h2>Situation de reglement</h2>
            <div><strong>Workflow :</strong> {{ $invoice->status === 'validated' ? 'Approuvee' : 'En attente d approbation' }}</div>
            <div><strong>Approuvee par :</strong> {{ $invoice->approver?->name ?? 'Non approuvee' }}</div>
            <div><strong>Statut paiement :</strong> {{ $invoice->payment_status === 'paid' ? 'Payee' : ($invoice->payment_status === 'partial' ? 'Partiellement payee' : 'Impayee') }}</div>
            <div><strong>Montant paye :</strong> {{ number_format((float) $invoice->amount_paid, 0, ',', ' ') }} XOF</div>
            <div><strong>Solde restant :</strong> {{ number_format((float) $invoice->balance_due, 0, ',', ' ') }} XOF</div>
            <div><strong>Reference interne :</strong> {{ $invoice->id }}</div>
        </div>
    </section>

    <table>
        <thead>
            <tr>
                <th>Produit</th>
                <th>Description</th>
                <th class="right">Quantite</th>
                <th class="right">PU</th>
                <th class="right">Montant</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td>@include('partials.product-inline', ['product' => $item->product, 'meta' => $item->product?->barcode ?: $item->product?->sku, 'size' => 34, 'link' => null])</td>
                    <td>{{ $item->description }}</td>
                    <td class="right">{{ number_format((float) $item->qty, 3, ',', ' ') }}</td>
                    <td class="right">{{ number_format((float) $item->unit_price, 0, ',', ' ') }} XOF</td>
                    <td class="right">{{ number_format((float) $item->line_total, 0, ',', ' ') }} XOF</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>Sous-total</td>
            <td class="right">{{ number_format((float) $invoice->subtotal, 0, ',', ' ') }} XOF</td>
        </tr>
        @if ($invoice->hasDiscount())
            <tr>
                <td>Remise</td>
                <td class="right">- {{ number_format((float) $invoice->discount_total, 0, ',', ' ') }} XOF</td>
            </tr>
        @endif
        <tr>
            <td>Total facture</td>
            <td class="right">{{ number_format((float) $invoice->total, 0, ',', ' ') }} XOF</td>
        </tr>
        <tr>
            <td>Montant paye</td>
            <td class="right">{{ number_format((float) $invoice->amount_paid, 0, ',', ' ') }} XOF</td>
        </tr>
        <tr class="grand-total">
            <td>Reste a payer</td>
            <td class="right">{{ number_format((float) $invoice->balance_due, 0, ',', ' ') }} XOF</td>
        </tr>
    </table>

    @if ($invoice->notes)
        <div class="footer">
            <strong>Notes :</strong> {{ $invoice->notes }}
        </div>
    @endif

    <div class="signatures">
        <div class="signature-box">Signature / cachet entreprise</div>
        <div class="signature-box">Signature client</div>
    </div>
@endsection

