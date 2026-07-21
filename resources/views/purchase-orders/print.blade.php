@extends('layouts.print')

@section('title', 'Bon de commande fournisseur - Nema ERP')

@section('content')
    <section class="doc-header">
        <div>
            <div class="doc-chip">Bon de commande fournisseur</div>
            <h1>{{ $order->order_number }}</h1>
            @include('partials.print-company-block', ['company' => $company])
        </div>
        <div class="right">
            <div><strong>Date :</strong> {{ $order->order_date?->format('d/m/Y') }}</div>
            <div><strong>Statut :</strong> {{ ucfirst(str_replace('_', ' ', $order->status)) }}</div>
            <div class="meta">Depot : {{ $order->warehouse?->name ?? 'n/a' }}</div>
            <div class="meta">Reception prevue : {{ $order->expected_receipt_date?->format('d/m/Y') ?? '-' }}</div>
            <div class="meta">Imprime le {{ now()->format('d/m/Y H:i') }}</div>
        </div>
    </section>

    <section class="grid grid-2">
        <div class="panel">
            <h2>Fournisseur</h2>
            <div><strong>{{ $order->supplier?->name ?? 'n/a' }}</strong></div>
            @if ($order->supplier?->code)
                <div class="meta">Code : {{ $order->supplier->code }}</div>
            @endif
            @if ($order->supplier?->phone)
                <div>Telephone : {{ $order->supplier->phone }}</div>
            @endif
            @if ($order->supplier?->email)
                <div>Email : {{ $order->supplier->email }}</div>
            @endif
            @if ($order->supplier?->address || $order->supplier?->city)
                <div>{{ $order->supplier?->address }} {{ $order->supplier?->city }}</div>
            @endif
            @if ($order->supplier?->nif)
                <div>NIF : {{ $order->supplier->nif }}</div>
            @endif
        </div>
        <div class="panel">
            <h2>Suivi achat</h2>
            <div>Agence : <strong>{{ $order->branch?->name ?? 'n/a' }}</strong></div>
            <div>Entrepot de reception : <strong>{{ $order->warehouse?->name ?? 'n/a' }}</strong></div>
            <div>Cree par : <strong>{{ $order->creator?->name ?? 'n/a' }}</strong></div>
            @if ($order->sourcePurchaseRequest)
                <div>Demande source : <strong>{{ $order->sourcePurchaseRequest->request_number }}</strong></div>
            @endif
            <div>Receptions deja faites : <strong>{{ $order->goodsReceipts->count() }}</strong></div>
        </div>
    </section>

    <table>
        <thead>
        <tr>
            <th>Produit</th>
            <th>Reference</th>
            <th>Description</th>
            <th class="right">Commande</th>
            <th class="right">Recu</th>
            <th class="right">Reste</th>
            <th class="right">Cout</th>
            <th class="right">Total</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($order->items as $item)
            <tr>
                <td><strong>{{ $item->product?->name ?? $item->description }}</strong></td>
                <td>{{ $item->product?->barcode ?: $item->product?->sku ?: '-' }}</td>
                <td>{{ $item->description ?: $item->product?->purchase_description ?: '-' }}</td>
                <td class="right">{{ number_format((float) $item->qty, 3, ',', ' ') }} {{ $item->product?->unit }}</td>
                <td class="right">{{ number_format((float) $item->received_qty, 3, ',', ' ') }}</td>
                <td class="right">{{ number_format((float) $item->remainingQty(), 3, ',', ' ') }}</td>
                <td class="right">{{ number_format((float) $item->unit_cost, 0, ',', ' ') }} XOF</td>
                <td class="right">{{ number_format((float) $item->line_total, 0, ',', ' ') }} XOF</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>Sous-total</td>
            <td class="right">{{ number_format((float) $order->subtotal, 0, ',', ' ') }} XOF</td>
        </tr>
        <tr class="grand-total">
            <td>Total commande</td>
            <td class="right">{{ number_format((float) $order->total, 0, ',', ' ') }} XOF</td>
        </tr>
    </table>

    @if ($order->notes)
        <section class="panel" style="margin-top:18px;">
            <h2>Notes</h2>
            <div>{{ $order->notes }}</div>
        </section>
    @endif

    @if ($order->goodsReceipts->isNotEmpty())
        <table>
            <thead>
            <tr>
                <th>Reception</th>
                <th>Date</th>
                <th>Facture fournisseur</th>
                <th>Statut</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($order->goodsReceipts as $receipt)
                <tr>
                    <td>{{ $receipt->receipt_number }}</td>
                    <td>{{ $receipt->receipt_date?->format('d/m/Y') }}</td>
                    <td>{{ $receipt->purchaseBill?->bill_number ?? '-' }}</td>
                    <td>{{ $receipt->purchaseBill ? 'Facturee' : 'A facturer' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    <div class="signatures">
        <div class="signature-box">Responsable achats</div>
        <div class="signature-box">Fournisseur</div>
    </div>

    <div class="footer">
        Bon de commande genere par Nema ERP. Les quantites recues et restantes servent au controle de reception.
    </div>
@endsection
