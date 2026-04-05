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
                    <input id="receipt_date" type="date" name="receipt_date" value="{{ old('receipt_date', now()->format('Y-m-d')) }}" required>
                </div>
                <div>
                    <label>Agence active</label>
                    <input type="text" value="{{ $branch?->name }}" disabled>
                </div>
                <div class="full">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes">{{ old('notes') }}</textarea>
                </div>
            </div>
            @error('items')<div class="field-error" style="margin-top:12px;">{{ $message }}</div>@enderror
        </section>
        <aside class="card">
            <h2 class="section-title">Impact</h2>
            <div class="tip-card"><strong>Stock</strong><div class="muted">La reception augmente le stock du depot de la commande fournisseur.</div></div>
            <div class="tip-card" style="margin-top:12px;"><strong>Traçabilite</strong><div class="muted">Les produits suivis par lot ou numero de serie peuvent maintenant enregistrer leur reference et leur date de peremption a la reception.</div></div>
        </aside>
    </div>

    @if ($selectedOrder)
        <section class="card" style="margin-top:18px;">
            <h2 class="section-title">Lignes a recevoir</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Produit</th>
                        <th>Commande</th>
                        <th>Deja recu</th>
                        <th>Reste</th>
                        <th>A recevoir</th>
                        <th>Suivi</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($selectedOrder->items as $item)
                        @php
                            $trackingType = $item->product?->tracking_type ?? 'none';
                            $oldLine = old('items.'.$loop->index, []);
                        @endphp
                        <tr>
                            <td>@include('partials.product-inline', ['product' => $item->product, 'meta' => $item->product?->barcode ?: $item->product?->sku, 'size' => 42])</td>
                            <td>{{ number_format((float) $item->qty, 3, ',', ' ') }}</td>
                            <td>{{ number_format((float) $item->received_qty, 3, ',', ' ') }}</td>
                            <td>{{ number_format($item->remainingQty(), 3, ',', ' ') }}</td>
                            <td>
                                <input type="hidden" name="items[{{ $loop->index }}][purchase_order_item_id]" value="{{ $item->id }}">
                                <input type="number" step="0.001" min="0" max="{{ $item->remainingQty() }}" name="items[{{ $loop->index }}][qty]" value="{{ $oldLine['qty'] ?? ($item->remainingQty() > 0 ? $item->remainingQty() : '') }}">
                            </td>
                            <td style="min-width:260px;">
                                @if ($trackingType === 'lot')
                                    <div class="badge badge-warning">Lot requis</div>
                                    <div style="display:grid; gap:8px; margin-top:10px;">
                                        <input type="text" name="items[{{ $loop->index }}][lot_number]" value="{{ $oldLine['lot_number'] ?? '' }}" placeholder="Numero de lot">
                                        <input type="date" name="items[{{ $loop->index }}][expires_at]" value="{{ $oldLine['expires_at'] ?? '' }}">
                                    </div>
                                @elseif ($trackingType === 'serial')
                                    <div class="badge badge-warning">Numeros de serie requis</div>
                                    <div style="display:grid; gap:8px; margin-top:10px;">
                                        <textarea name="items[{{ $loop->index }}][serial_numbers_text]" rows="4" placeholder="Un numero par ligne ou separes par virgule">{{ $oldLine['serial_numbers_text'] ?? '' }}</textarea>
                                        <input type="date" name="items[{{ $loop->index }}][expires_at]" value="{{ $oldLine['expires_at'] ?? '' }}">
                                    </div>
                                    <div class="help" style="margin-top:8px;">Le nombre de series doit correspondre exactement a la quantite recue.</div>
                                @else
                                    <span class="muted">Aucun suivi specifique</span>
                                @endif
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
