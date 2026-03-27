@extends('layouts.print')

@section('title', 'Bon de livraison '.$deliveryNote->delivery_number)

@section('content')
    <section class="print-card">
        <div class="print-head">
            <div>
                <h1>Bon de livraison {{ $deliveryNote->delivery_number }}</h1>
                <div class="muted">Date : {{ $deliveryNote->delivery_date?->format('d/m/Y') }}</div>
                <div class="muted">Commande : {{ $deliveryNote->salesOrder?->order_number ?? 'Aucune' }}</div>
            </div>
            <div style="text-align:right;">
                <strong>{{ $deliveryNote->company?->name }}</strong>
                <div class="muted">{{ $deliveryNote->branch?->name }}</div>
                <div class="muted">{{ $deliveryNote->company?->address }}</div>
                <div class="muted">{{ $deliveryNote->company?->phone }}</div>
            </div>
        </div>

        <div class="print-meta" style="margin-top:18px;">
            <div>
                <div class="muted">Client</div>
                <strong>{{ $deliveryNote->customer?->name }}</strong>
                <div class="muted">{{ $deliveryNote->customer?->phone }}</div>
                <div class="muted">{{ $deliveryNote->customer?->address }}</div>
            </div>
            <div>
                <div class="muted">Statut</div>
                <strong>{{ strtoupper($deliveryNote->status) }}</strong>
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
            @foreach ($deliveryNote->items as $item)
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
                <th>{{ number_format((float) $deliveryNote->total, 0, ',', ' ') }} XOF</th>
            </tr>
            </tfoot>
        </table>

        @if ($deliveryNote->notes)
            <div style="margin-top:18px;">
                <div class="muted">Notes</div>
                <div>{{ $deliveryNote->notes }}</div>
            </div>
        @endif
    </section>
@endsection

