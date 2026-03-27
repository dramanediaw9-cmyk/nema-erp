@extends('layouts.app')

@section('title', 'Nouvelle commande fournisseur - Nema ERP')
@section('page-title', 'Nouvelle commande fournisseur')

@section('content')
<form method="POST" action="{{ route('purchase-orders.store') }}">
    @csrf
    <div class="split">
        <section class="card">
            <h2 class="section-title">Entete</h2>
            <div class="form-grid">
                <div>
                    <label for="supplier_id">Fournisseur</label>
                    <select id="supplier_id" name="supplier_id" required>
                        <option value="">Selectionner</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}">{{ $supplier->code }} - {{ $supplier->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="warehouse_id">Depot de reception</label>
                    <select id="warehouse_id" name="warehouse_id" required>
                        <option value="">Selectionner</option>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected($warehouse->is_default)>{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="order_date">Date commande</label>
                    <input id="order_date" type="date" name="order_date" value="{{ now()->format('Y-m-d') }}" required>
                </div>
                <div>
                    <label for="expected_receipt_date">Reception attendue</label>
                    <input id="expected_receipt_date" type="date" name="expected_receipt_date">
                </div>
                <div class="full">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes"></textarea>
                </div>
            </div>
        </section>
        <aside class="card">
            <h2 class="section-title">Impact</h2>
            <div class="tip-card"><strong>Stock</strong><div class="muted">Aucun mouvement de stock n est genere tant qu une reception n est pas saisie.</div></div>
        </aside>
    </div>

    <section class="card" style="margin-top:18px;">
        <h2 class="section-title">Lignes commandees</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>#</th><th>Produit</th><th>Description</th><th>Quantite</th><th>Cout unitaire</th></tr></thead>
                <tbody>
                @foreach ($defaultRows as $index => $row)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>
                            <select name="items[{{ $index }}][product_id]">
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
            <a href="{{ route('purchase-orders.index') }}" class="button button-secondary">Annuler</a>
            <button type="submit" class="button button-primary">Enregistrer la commande</button>
        </div>
    </section>
</form>
@endsection
