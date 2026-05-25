@extends('layouts.print')

@section('title', 'Avoir fournisseur '.$creditNote->credit_note_number.' - Nema ERP')

@section('content')
    <section class="doc-header">
        <div>
            <div class="doc-chip">Avoir fournisseur</div>
            <h1>{{ $creditNote->credit_note_number }}</h1>
            <div><strong>{{ $creditNote->company?->legal_name ?: $creditNote->company?->name }}</strong></div>
            <div class="meta">{{ $creditNote->company?->address }}</div>
            <div class="meta">Tel : {{ $creditNote->company?->phone ?: 'N/A' }}</div>
        </div>
        <div class="right">
            <div><strong>Date :</strong> {{ $creditNote->credit_note_date?->format('d/m/Y') }}</div>
            <div class="meta">Facture source : {{ $creditNote->bill?->bill_number }}</div>
            <div class="meta">Agence : {{ $creditNote->branch?->name }}</div>
            <div class="meta">Retour stock : {{ $creditNote->destock_items ? 'Oui' : 'Non' }}</div>
        </div>
    </section>

    <section class="grid grid-2">
        <div class="panel">
            <h2>Fournisseur</h2>
            <div><strong>{{ $creditNote->supplier?->name }}</strong></div>
            <div class="muted">Adresse : {{ $creditNote->supplier?->address ?: 'Non renseignee' }}</div>
            <div class="muted">Telephone : {{ $creditNote->supplier?->phone ?: 'Non renseigne' }}</div>
        </div>
        <div class="panel">
            <h2>Synthese</h2>
            <div><strong>Montant avoir :</strong> {{ number_format((float) $creditNote->total, 0, ',', ' ') }} XOF</div>
            <div><strong>Solde facture restant :</strong> {{ number_format((float) $creditNote->bill?->balance_due, 0, ',', ' ') }} XOF</div>
            <div><strong>Reference interne :</strong> {{ $creditNote->id }}</div>
        </div>
    </section>

    <table>
        <thead><tr><th>Produit</th><th>Description</th><th class="right">Quantite</th><th class="right">Cout</th><th class="right">TVA</th><th class="right">Montant</th></tr></thead>
        <tbody>
            @foreach ($creditNote->items as $item)
                <tr>
                    <td>@include('partials.product-inline', ['product' => $item->product, 'meta' => $item->product?->barcode ?: $item->product?->sku, 'size' => 34, 'link' => null])</td>
                    <td>{{ $item->description }}</td>
                    <td class="right">{{ number_format((float) $item->qty, 3, ',', ' ') }}</td>
                    <td class="right">{{ number_format((float) $item->unit_cost, 0, ',', ' ') }} XOF</td>
                    <td class="right">{{ number_format((float) $item->tax_amount, 0, ',', ' ') }} XOF</td>
                    <td class="right">{{ number_format((float) $item->line_total, 0, ',', ' ') }} XOF</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Total HT</td><td class="right">{{ number_format((float) $creditNote->net_total, 0, ',', ' ') }} XOF</td></tr>
        <tr><td>TVA</td><td class="right">{{ number_format((float) $creditNote->tax_total, 0, ',', ' ') }} XOF</td></tr>
        <tr class="grand-total"><td>Total avoir</td><td class="right">{{ number_format((float) $creditNote->total, 0, ',', ' ') }} XOF</td></tr>
    </table>

    @if ($creditNote->notes)
        <div class="footer"><strong>Motif :</strong> {{ $creditNote->notes }}</div>
    @endif
@endsection
