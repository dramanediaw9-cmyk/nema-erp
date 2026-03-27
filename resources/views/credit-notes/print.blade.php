@extends('layouts.print')

@section('title', 'Avoir '.$creditNote->credit_note_number.' - Nema ERP')

@section('content')
    <section class="doc-header">
        <div>
            <div class="doc-chip">Avoir client</div>
            <h1>{{ $creditNote->credit_note_number }}</h1>
            <div><strong>{{ $creditNote->company?->legal_name ?: $creditNote->company?->name }}</strong></div>
            <div class="meta">{{ $creditNote->company?->address }}</div>
            <div class="meta">Tel : {{ $creditNote->company?->phone ?: 'N/A' }}</div>
        </div>
        <div class="right">
            <div><strong>Date :</strong> {{ $creditNote->credit_note_date?->format('d/m/Y') }}</div>
            <div class="meta">Facture source : {{ $creditNote->invoice?->invoice_number }}</div>
            <div class="meta">Agence : {{ $creditNote->branch?->name }}</div>
            <div class="meta">Retour stock : {{ $creditNote->restock_items ? 'Oui' : 'Non' }}</div>
        </div>
    </section>

    <section class="grid grid-2">
        <div class="panel">
            <h2>Client</h2>
            <div><strong>{{ $creditNote->customer?->name }}</strong></div>
            <div class="muted">Adresse : {{ $creditNote->customer?->address ?: 'Non renseignee' }}</div>
            <div class="muted">Telephone : {{ $creditNote->customer?->phone ?: 'Non renseigne' }}</div>
        </div>
        <div class="panel">
            <h2>Synthese</h2>
            <div><strong>Montant avoir :</strong> {{ number_format((float) $creditNote->total, 0, ',', ' ') }} XOF</div>
            <div><strong>Solde facture restant :</strong> {{ number_format((float) $creditNote->invoice?->balance_due, 0, ',', ' ') }} XOF</div>
            <div><strong>Reference interne :</strong> {{ $creditNote->id }}</div>
        </div>
    </section>

    <table>
        <thead><tr><th>Produit</th><th>Description</th><th class="right">Quantite</th><th class="right">PU</th><th class="right">Montant</th></tr></thead>
        <tbody>
            @foreach ($creditNote->items as $item)
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

    <table class="totals"><tr class="grand-total"><td>Total avoir</td><td class="right">{{ number_format((float) $creditNote->total, 0, ',', ' ') }} XOF</td></tr></table>

    @if ($creditNote->notes)
        <div class="footer"><strong>Motif :</strong> {{ $creditNote->notes }}</div>
    @endif
@endsection

