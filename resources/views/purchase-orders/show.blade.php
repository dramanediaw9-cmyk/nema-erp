@extends('layouts.app')

@section('title', 'Commande fournisseur '.$order->order_number)
@section('page-title', 'Detail commande fournisseur')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">{{ $order->order_number }}</h2>
            <div class="muted">{{ $order->supplier?->name }} · {{ $order->warehouse?->name }}</div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="{{ route('purchase-orders.index') }}" class="button button-secondary">Retour liste</a>
            <a href="{{ route('purchase-orders.print', $order) }}" class="button button-secondary">Imprimer</a>
            @if ($order->status === 'confirmed' || $order->status === 'partial_received')
                <a href="{{ route('goods-receipts.create', ['order' => $order->id]) }}" class="button button-primary">Nouvelle reception</a>
            @endif
        </div>
    </div>

    <div class="split">
        <section class="card">
            <div class="grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                <div><div class="muted">Date</div><strong>{{ $order->order_date?->format('d/m/Y') }}</strong></div>
                <div><div class="muted">Statut</div><span class="badge {{ in_array($order->status, ['confirmed', 'received'], true) ? 'badge-success' : 'badge-muted' }}">{{ $order->status }}</span></div>
                <div><div class="muted">Depot</div><strong>{{ $order->warehouse?->name }}</strong></div>
                <div><div class="muted">Reception attendue</div><strong>{{ $order->expected_receipt_date?->format('d/m/Y') ?? 'Non renseignee' }}</strong></div>
                @if ($order->sourcePurchaseRequest)
                    <div><div class="muted">Demande source</div><strong><a href="{{ route('purchase-requests.show', $order->sourcePurchaseRequest) }}">{{ $order->sourcePurchaseRequest->request_number }}</a></strong></div>
                    <div><div class="muted">Origine</div><strong>Allocation fournisseur / demande d achat</strong></div>
                @endif
            </div>
            @if ($order->notes)
                <div class="card" style="margin-top:18px; padding:16px;">
                    <div class="muted">Notes</div><div>{{ $order->notes }}</div>
                </div>
            @endif
        </section>
        <aside class="card">
            <h2 class="section-title">Actions</h2>
            <a href="{{ route('purchase-orders.print', $order) }}" class="button button-secondary" style="width:100%; text-align:center;">Imprimer le bon</a>
            @if ($order->status === 'draft')
                <form method="POST" action="{{ route('purchase-orders.confirm', $order) }}" style="margin-top:12px;">
                    @csrf
                    <button type="submit" class="button button-primary" style="width:100%;">Confirmer la commande</button>
                </form>
            @endif
            @if (in_array($order->status, ['draft', 'confirmed'], true))
                <form method="POST" action="{{ route('purchase-orders.cancel', $order) }}" style="margin-top:12px;">
                    @csrf
                    <button type="submit" class="button button-secondary" style="width:100%;">Annuler</button>
                </form>
            @endif
            <div class="tip-card" style="margin-top:12px;"><strong>Receptions</strong><div class="muted">{{ $order->goodsReceipts->count() }} reception(s) deja enregistree(s).</div></div>
            <div class="tip-card" style="margin-top:12px;"><strong>Factures</strong><div class="muted">{{ $order->goodsReceipts->filter(fn ($receipt) => $receipt->purchaseBill)->count() }} facture(s) fournisseur deja creee(s).</div></div>
        </aside>
    </div>

    <section class="card" style="margin-top:18px;">
        <h2 class="section-title">Lignes</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Produit</th><th>Commande</th><th>Recu</th><th>Reste</th><th>Cout</th></tr></thead>
                <tbody>
                @foreach ($order->items as $item)
                    <tr>
                        <td>@include('partials.product-inline', ['product' => $item->product, 'meta' => $item->product?->barcode ?: $item->product?->sku, 'size' => 42])</td>
                        <td>{{ number_format((float) $item->qty, 3, ',', ' ') }}</td>
                        <td>{{ number_format((float) $item->received_qty, 3, ',', ' ') }}</td>
                        <td>{{ number_format($item->remainingQty(), 3, ',', ' ') }}</td>
                        <td>{{ number_format((float) $item->unit_cost, 0, ',', ' ') }} XOF</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @if ($order->goodsReceipts->isNotEmpty())
        <section class="card" style="margin-top:18px;">
            <h2 class="section-title">Receptions deja enregistrees</h2>
            @foreach ($order->goodsReceipts as $receipt)
                <div style="padding-bottom:12px; border-bottom:1px solid #efe4d3; margin-bottom:12px; display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:flex-start;">
                    <div>
                        <strong>{{ $receipt->receipt_number }}</strong>
                        <div class="muted">{{ $receipt->receipt_date?->format('d/m/Y') }}</div>
                        @if ($receipt->purchaseBill)
                            <div class="muted" style="margin-top:6px;">Facture liee : {{ $receipt->purchaseBill->bill_number }}</div>
                        @else
                            <div class="muted" style="margin-top:6px;">Reception a facturer</div>
                        @endif
                    </div>
                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                        <a href="{{ route('goods-receipts.show', $receipt) }}" class="button button-secondary">Voir</a>
                        @if ($receipt->purchaseBill)
                            <a href="{{ route('purchases.show', $receipt->purchaseBill) }}" class="button button-secondary">Facture</a>
                        @else
                            <a href="{{ route('purchases.create', ['receipt' => $receipt->id]) }}" class="button button-primary">Facturer</a>
                        @endif
                    </div>
                </div>
            @endforeach
        </section>
    @endif
@endsection
