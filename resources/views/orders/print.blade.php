@extends('layouts.print')

@section('title', 'Commande '.$order->order_number)

@section('content')
    <section class="print-card">
        <div class="print-head">
            <div>
                <h1>Commande {{ $order->order_number }}</h1>
                <div class="muted">Date : {{ $order->order_date?->format('d/m/Y') }}</div>
                <div class="muted">Livraison souhaitee : {{ $order->requested_delivery_date?->format('d/m/Y') ?? 'Non renseignee' }}</div>
            </div>
            <div style="text-align:right;">
                <strong>{{ $order->company?->name }}</strong>
                <div class="muted">{{ $order->branch?->name }}</div>
                <div class="muted">{{ $order->company?->address }}</div>
                <div class="muted">{{ $order->company?->phone }}</div>
            </div>
        </div>

        <div class="print-meta" style="margin-top:18px;">
            <div>
                <div class="muted">Client</div>
                <strong>{{ $order->customer?->name }}</strong>
                <div class="muted">{{ $order->customer?->phone }}</div>
                <div class="muted">{{ $order->customer?->address }}</div>
            </div>
            <div>
                <div class="muted">Statut</div>
                <strong>{{ strtoupper($order->status) }}</strong>
                @if ($order->originQuote)
                    <div class="muted" style="margin-top:6px;">Devis source : {{ $order->originQuote->quote_number }}</div>
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
            @foreach ($order->items as $item)
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
                <th>{{ number_format((float) $order->total, 0, ',', ' ') }} XOF</th>
            </tr>
            </tfoot>
        </table>

        @if ($order->notes)
            <div style="margin-top:18px;">
                <div class="muted">Notes</div>
                <div>{{ $order->notes }}</div>
            </div>
        @endif
    </section>
@endsection

