@extends('layouts.app')

@section('title', 'Reception '.$receipt->receipt_number)
@section('page-title', 'Detail reception fournisseur')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">{{ $receipt->receipt_number }}</h2>
            <div class="muted">{{ $receipt->supplier?->name }} · {{ $receipt->warehouse?->name }}</div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="{{ route('goods-receipts.index') }}" class="button button-secondary">Retour liste</a>
            @if ($receipt->purchaseBill)
                <a href="{{ route('purchases.show', $receipt->purchaseBill) }}" class="button button-primary">Ouvrir la facture fournisseur</a>
            @else
                <a href="{{ route('purchases.create', ['receipt' => $receipt->id]) }}" class="button button-primary">Creer la facture fournisseur</a>
            @endif
        </div>
    </div>

    <div class="split">
        <section class="card">
            <div class="grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                <div><div class="muted">Date</div><strong>{{ $receipt->receipt_date?->format('d/m/Y') }}</strong></div>
                <div><div class="muted">Commande source</div><a href="{{ route('purchase-orders.show', $receipt->purchaseOrder) }}"><strong>{{ $receipt->purchaseOrder?->order_number }}</strong></a></div>
                <div><div class="muted">Depot</div><strong>{{ $receipt->warehouse?->name }}</strong></div>
                <div><div class="muted">Statut</div><span class="badge badge-success">{{ ucfirst($receipt->status) }}</span></div>
            </div>
            @if ($receipt->notes)
                <div class="card" style="margin-top:18px; padding:16px;"><div class="muted">Notes</div><div>{{ $receipt->notes }}</div></div>
            @endif
        </section>
        <aside class="card">
            <h2 class="section-title">Suite du flux</h2>
            @if ($receipt->purchaseBill)
                <div class="tip-card">
                    <strong>Facture deja creee</strong>
                    <div class="muted">{{ $receipt->purchaseBill->bill_number }} · {{ $receipt->purchaseBill->status === 'validated' ? 'Approuvee' : 'En attente d approbation' }}</div>
                </div>
                <div style="margin-top:12px;">
                    <a href="{{ route('purchases.show', $receipt->purchaseBill) }}" class="button button-secondary" style="width:100%;">Voir la facture</a>
                </div>
            @else
                <div class="tip-card">
                    <strong>Etape suivante</strong>
                    <div class="muted">Cette reception peut maintenant etre transformee en facture fournisseur sans ressaisie des lignes.</div>
                </div>
                <div style="margin-top:12px;">
                    <a href="{{ route('purchases.create', ['receipt' => $receipt->id]) }}" class="button button-primary" style="width:100%;">Facturer cette reception</a>
                </div>
            @endif
        </aside>
    </div>

    <section class="card" style="margin-top:18px;">
        <h2 class="section-title">Lignes recues</h2>
        @foreach ($receipt->items as $item)
            <div style="padding-bottom:12px; border-bottom:1px solid #efe4d3; margin-bottom:12px;">
                @include('partials.product-inline', ['product' => $item->product, 'meta' => number_format((float) $item->qty, 3, ',', ' ').' · '.number_format((float) $item->unit_cost, 0, ',', ' ').' XOF', 'size' => 42])
                @if ($item->lot_number || ! empty($item->serial_numbers) || $item->productLots->isNotEmpty())
                    <div class="chip-row" style="margin-top:10px;">
                        @if ($item->lot_number)
                            <span class="badge badge-warning">Lot {{ $item->lot_number }}</span>
                        @endif
                        @if ($item->expires_at)
                            <span class="badge badge-muted">Peremption {{ $item->expires_at->format('d/m/Y') }}</span>
                        @endif
                        @foreach ($item->serial_numbers ?? [] as $serialNumber)
                            <span class="badge badge-muted">Serie {{ $serialNumber }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </section>

    <section class="card" style="margin-top:18px;">
        <h2 class="section-title">Mouvements de stock lies</h2>
        @forelse ($stockMovements as $movement)
            <div style="padding-bottom:12px; border-bottom:1px solid #efe4d3; margin-bottom:12px;">
                @include('partials.product-inline', ['product' => $movement->product, 'meta' => $movement->warehouse?->name.' · '.$movement->movement_date?->format('d/m/Y H:i'), 'size' => 40])
                <div>Entree : {{ number_format((float) $movement->quantity_in, 3, ',', ' ') }}</div>
                @if ($movement->productLot)
                    <div class="muted" style="margin-top:6px;">Suivi : {{ $movement->productLot->displayCode() }}@if ($movement->productLot->expires_at) · Exp {{ $movement->productLot->expires_at->format('d/m/Y') }}@endif</div>
                @endif
            </div>
        @empty
            <p class="muted">Aucun mouvement de stock trouve pour cette reception.</p>
        @endforelse
    </section>
@endsection
