@extends('layouts.app')

@section('title', 'Demande d achat')
@section('page-title', 'Demande d achat')

@section('content')
    <div class="page-head">
        <div>
            <h2 class="section-title">{{ $purchaseRequest->request_number }}</h2>
            <div class="muted">Entrepot {{ $purchaseRequest->warehouse?->name }} · Priorite {{ ucfirst($purchaseRequest->priority) }}</div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            @if ($purchaseRequest->status === 'pending_approval')
                @allowed('purchase_requests.approve')
                    <form method="POST" action="{{ route('purchase-requests.approve', $purchaseRequest) }}" class="inline-form">@csrf<button class="button button-primary" type="submit">Approuver</button></form>
                    <form method="POST" action="{{ route('purchase-requests.reject', $purchaseRequest) }}" class="inline-form">@csrf<button class="button button-danger" type="submit">Rejeter</button></form>
                @endallowed
            @endif
            <a href="{{ route('purchase-requests.index') }}" class="button button-secondary">Retour</a>
        </div>
    </div>

    <div class="split">
        <div class="card summary-stack">
            <div class="summary-box"><strong>Statut</strong><div class="value">{{ str_replace('_', ' ', $purchaseRequest->status) }}</div></div>
            <div class="summary-box"><strong>Date demande</strong><div class="value" style="font-size:22px;">{{ $purchaseRequest->request_date?->format('d/m/Y') }}</div></div>
            <div class="summary-box"><strong>Total estime</strong><div class="value">{{ number_format((float) $purchaseRequest->total, 0, ',', ' ') }} XOF</div></div>
            @if ($purchaseRequest->notes)
                <div class="summary-box"><strong>Notes</strong><div style="margin-top:8px;">{{ $purchaseRequest->notes }}</div></div>
            @endif
        </div>

        <div class="card">
            <h3 class="section-title">Lignes</h3>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Produit</th>
                        <th>Quantite</th>
                        <th>Coût estime</th>
                        <th>Total</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($purchaseRequest->items as $item)
                        <tr>
                            <td>@include('partials.product-inline', ['product' => $item->product, 'meta' => $item->product?->barcode ?: $item->product?->sku, 'size' => 42])</td>
                            <td>{{ number_format((float) $item->qty, 3, ',', ' ') }}</td>
                            <td>{{ number_format((float) $item->estimated_unit_cost, 0, ',', ' ') }} XOF</td>
                            <td>{{ number_format((float) $item->line_total, 0, ',', ' ') }} XOF</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if ($purchaseRequest->status === 'approved' && ! $purchaseRequest->converted_purchase_order_id)
        <form method="POST" action="{{ route('purchase-requests.convert', $purchaseRequest) }}" class="card" style="margin-top:20px;">
            @csrf
            <h3 class="section-title">Convertir en commande fournisseur</h3>
            <div class="form-grid">
                <div>
                    <label for="supplier_id">Fournisseur</label>
                    <select id="supplier_id" name="supplier_id" required>
                        <option value="">Choisir</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                    @error('supplier_id')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="actions">
                <button type="submit" class="button button-primary">Creer la commande fournisseur</button>
            </div>
        </form>
    @endif

    @if ($purchaseRequest->convertedPurchaseOrder)
        <div class="card" style="margin-top:20px;">
            <strong>Commande creee</strong>
            <div class="muted" style="margin-top:8px;">{{ $purchaseRequest->convertedPurchaseOrder->order_number }}</div>
            <div style="margin-top:14px;"><a href="{{ route('purchase-orders.show', $purchaseRequest->convertedPurchaseOrder) }}" class="button button-secondary">Ouvrir la commande</a></div>
        </div>
    @endif
@endsection

