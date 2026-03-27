@extends('layouts.app')

@section('title', 'Nouvelle reception fournisseur - Nema ERP')
@section('page-title', 'Nouvelle reception fournisseur')

@section('content')
<form method="POST" action="{{ route('goods-receipts.store') }}">
    @csrf
    <div class="split">
        <section class="card">
            <h2 class="section-title">Entete</h2>
            <div class="form-grid">
                <div class="full">
                    <label for="order_id">Commande fournisseur</label>
                    <select id="order_id" name="order_id" required>
                        <option value="">Selectionner</option>
                        @foreach ($orders as $order)
                            <option value="{{ $order->id }}" @selected((string) old('order_id', $selectedOrder?->id) === (string) $order->id)>{{ $order->order_number }} - {{ $order->supplier?->name }} - {{ $order->warehouse?->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="receipt_date">Date reception</label>
                    <input id="receipt_date" type="date" name="receipt_date" value="{{ now()->format('Y-m-d') }}" required>
                </div>
                <div>
                    <label>Agence active</label>
                    <input type="text" value="{{ $branch?->name }}" disabled>
                </div>
                <div class="full">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes"></textarea>
                </div>
            </div>
        </section>
        <aside class="card">
            <h2 class="section-title">Impact</h2>
            <div class="tip-card"><strong>Stock</strong><div class="muted">La reception augmente le stock du depot de la commande fournisseur.</div></div>
        </aside>
    </div>

    @if ($selectedOrder)
        <section class="card" style="margin-top:18px;">
            <h2 class="section-title">Lignes a recevoir</h2>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Produit</th><th>Commande</th><th>Deja recu</th><th>Reste</th><th>A recevoir</th></tr></thead>
                    <tbody>
                    @foreach ($selectedOrder->items as $item)
                        <tr>
                            <td>@include('partials.product-inline', ['product' => $item->product, 'meta' => $item->product?->barcode ?: $item->product?->sku, 'size' => 42])</td>
                            <td>{{ number_format((float) $item->qty, 3, ',', ' ') }}</td>
                            <td>{{ number_format((float) $item->received_qty, 3, ',', ' ') }}</td>
                            <td>{{ number_format($item->remainingQty(), 3, ',', ' ') }}</td>
                            <td>
                                <input type="hidden" name="items[{{ $loop->index }}][purchase_order_item_id]" value="{{ $item->id }}">
                                <input type="number" step="0.001" min="0" max="{{ $item->remainingQty() }}" name="items[{{ $loop->index }}][qty]" value="{{ $item->remainingQty() > 0 ? $item->remainingQty() : '' }}">
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <div class="actions" style="margin-top:18px;">
        <a href="{{ route('goods-receipts.index') }}" class="button button-secondary">Annuler</a>
        <button type="submit" class="button button-primary">Enregistrer la reception</button>
    </div>
</form>
@endsection

