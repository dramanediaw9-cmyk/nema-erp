@extends('layouts.app')

@section('title', 'Nouveau transfert - Nema ERP')
@section('page-title', 'Nouveau transfert de stock')

@section('content')
<form method="POST" action="{{ route('transfers.store') }}">
    @csrf
    <div class="split">
        <section class="card">
            <h2 class="section-title">Entete</h2>
            <div class="form-grid">
                <div>
                    <label for="source_warehouse_id">Depot source</label>
                    <select id="source_warehouse_id" name="source_warehouse_id" required>
                        <option value="">Selectionner</option>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="destination_warehouse_id">Depot destination</label>
                    <select id="destination_warehouse_id" name="destination_warehouse_id" required>
                        <option value="">Selectionner</option>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="transfer_date">Date</label>
                    <input id="transfer_date" type="date" name="transfer_date" value="{{ now()->format('Y-m-d') }}" required>
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
            <div class="tip-card"><strong>Stock</strong><div class="muted">Le transfert sort du depot source et entre dans le depot destination en une seule operation.</div></div>
        </aside>
    </div>

    <section class="card" style="margin-top:18px;">
        <h2 class="section-title">Lignes du transfert</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>#</th><th>Produit</th><th>Description</th><th>Quantite</th><th>Cout unitaire</th></tr></thead>
                <tbody>
                @foreach ($defaultRows as $index => $row)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>
                            <select name="items[{{ $index }}][product_id]" data-product-picker data-product-mode="stockable">
                                <option value="">Choisir</option>
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}">{{ $product->sku }} - {{ $product->name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td><input type="text" name="items[{{ $index }}][description]" value="{{ $row['description'] ?? '' }}"></td>
                        <td><input type="number" step="0.001" min="0" name="items[{{ $index }}][qty]" value="{{ $row['qty'] ?? '' }}"></td>
                        <td><input type="number" step="0.01" min="0" name="items[{{ $index }}][unit_cost]" value="{{ $row['unit_cost'] ?? '' }}"></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="actions">
            <a href="{{ route('transfers.index') }}" class="button button-secondary">Annuler</a>
            <button type="submit" class="button button-primary">Enregistrer le transfert</button>
        </div>
    </section>
</form>
@endsection
