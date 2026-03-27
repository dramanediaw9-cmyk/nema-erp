@extends('layouts.print')

@section('title', 'Devis '.$quote->quote_number)

@section('content')
    <section class="print-card">
        <div class="print-head">
            <div>
                <h1>Devis {{ $quote->quote_number }}</h1>
                <div class="muted">Date : {{ $quote->quote_date?->format('d/m/Y') }}</div>
                <div class="muted">Validite : {{ $quote->valid_until?->format('d/m/Y') ?? 'Non renseignee' }}</div>
            </div>
            <div style="text-align:right;">
                <strong>{{ $quote->company?->name }}</strong>
                <div class="muted">{{ $quote->branch?->name }}</div>
                <div class="muted">{{ $quote->company?->address }}</div>
                <div class="muted">{{ $quote->company?->phone }}</div>
            </div>
        </div>

        <div class="print-meta" style="margin-top:18px;">
            <div>
                <div class="muted">Client</div>
                <strong>{{ $quote->customer?->name }}</strong>
                <div class="muted">{{ $quote->customer?->phone }}</div>
                <div class="muted">{{ $quote->customer?->address }}</div>
            </div>
            <div>
                <div class="muted">Statut</div>
                <strong>{{ strtoupper($quote->status) }}</strong>
                @if ($quote->convertedOrder)
                    <div class="muted" style="margin-top:6px;">Commande : {{ $quote->convertedOrder->order_number }}</div>
                @elseif ($quote->convertedInvoice)
                    <div class="muted" style="margin-top:6px;">Facture : {{ $quote->convertedInvoice->invoice_number }}</div>
                @endif
            </div>
        </div>

        <table class="print-table" style="margin-top:18px;">
            <thead>
            <tr>
                <th>Produit</th>
                <th>Description</th>
                <th>Quantite</th>
                <th>PU</th>
                <th>Total</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($quote->items as $item)
                <tr>
                    <td>@include('partials.product-inline', ['product' => $item->product, 'meta' => $item->product?->barcode ?: $item->product?->sku, 'size' => 34, 'link' => null])</td>
                    <td>{{ $item->description }}</td>
                    <td>{{ number_format((float) $item->qty, 3, ',', ' ') }}</td>
                    <td>{{ number_format((float) $item->unit_price, 0, ',', ' ') }} XOF</td>
                    <td>{{ number_format((float) $item->line_total, 0, ',', ' ') }} XOF</td>
                </tr>
            @endforeach
            </tbody>
            <tfoot>
            <tr>
                <th colspan="4" style="text-align:right;">Total</th>
                <th>{{ number_format((float) $quote->total, 0, ',', ' ') }} XOF</th>
            </tr>
            </tfoot>
        </table>

        @if ($quote->notes)
            <div style="margin-top:18px;">
                <div class="muted">Notes</div>
                <div>{{ $quote->notes }}</div>
            </div>
        @endif
    </section>
@endsection

