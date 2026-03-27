@extends('layouts.app')

@section('title', 'Stock - Nema ERP')
@section('page-title', 'Stock par agence')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">Situation stock - {{ $branch?->name }}</h2>
            <div class="muted">Le stock est calcule a partir des mouvements. Tu peux maintenant piloter un entrepot precis ou toute l'agence.</div>
        </div>
        <div style="display:flex; gap:12px; flex-wrap:wrap;">
            <a href="{{ route('stock.export', request()->query()) }}" class="button button-secondary">Exporter CSV</a>
            <a href="{{ route('stock.movements') }}" class="button button-secondary">Voir les mouvements</a>
            @allowed('warehouses.view')
                <a href="{{ route('warehouses.index') }}" class="button button-secondary">Entrepots</a>
            @endallowed
            @allowed('transfers.view')
                <a href="{{ route('transfers.index') }}" class="button button-secondary">Transferts</a>
            @endallowed
            @allowed('imports.manage')
                <a href="{{ route('imports.index') }}" class="button button-secondary">Importer stock initial</a>
            @endallowed
            @allowed('stock.manage')
                <a href="{{ route('stock.opening.create') }}" class="button button-secondary">Saisir stock initial</a>
                <a href="{{ route('stock.adjustments.create') }}" class="button button-primary">Ajuster le stock</a>
            @endallowed
        </div>
    </div>

    <section class="card" style="margin-bottom:18px;">
        <form method="GET" action="{{ route('stock.index') }}" class="form-grid" style="align-items:end; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));">
            <div style="grid-column:span 2; min-width:220px;">
                <label for="search">Recherche produit</label>
                <input type="text" id="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nom ou SKU...">
            </div>
            <div>
                <label for="category_id">Categorie</label>
                <select id="category_id" name="category_id">
                    <option value="">Toutes les categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((int) ($filters['category_id'] ?? 0) === $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="warehouse_id">Entrepot</label>
                <select id="warehouse_id" name="warehouse_id">
                    <option value="">Tous les entrepots</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected((int) ($filters['warehouse_id'] ?? 0) === $warehouse->id)>{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="stock_state">Etat de stock</label>
                <select id="stock_state" name="stock_state">
                    <option value="">Tous les etats</option>
                    <option value="low" @selected(($filters['stock_state'] ?? null) === 'low')>A surveiller</option>
                    <option value="positive" @selected(($filters['stock_state'] ?? null) === 'positive')>Stock positif</option>
                    <option value="zero" @selected(($filters['stock_state'] ?? null) === 'zero')>Rupture / zero</option>
                </select>
            </div>
            <div class="actions" style="margin-top:0; justify-content:flex-start; align-self:end;">
                <button type="submit" class="button button-primary">Filtrer</button>
                <a href="{{ route('stock.index') }}" class="button button-secondary">Reinitialiser</a>
            </div>
        </form>
    </section>

    @if ($selectedWarehouse)
        <div class="filter-pills">
            <span class="badge badge-success">Entrepot filtre : {{ $selectedWarehouse->name }}</span>
        </div>
    @endif

    <section class="card table-wrap">
        <table>
            <thead>
            <tr>
                <th>SKU</th>
                <th>Produit</th>
                <th>Categorie</th>
                <th>Stock actuel</th>
                <th>Stock minimum</th>
                <th>Valorisation</th>
                <th>Etat</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($products as $product)
                @php
                    $currentStock = (float) $product->current_stock;
                    $valuation = $currentStock * (float) $product->purchase_price;
                    $isAlert = $currentStock <= (float) $product->min_stock;
                @endphp
                <tr>
                    <td><strong>{{ $product->sku }}</strong></td>
                    <td>
                        @include('partials.product-inline', ['product' => $product, 'link' => route('stock.show', ['product' => $product->id, 'warehouse_id' => $filters['warehouse_id'] ?? null]), 'meta' => $product->unit, 'size' => 60])
                    </td>
                    <td>{{ $product->category_name ?: 'Sans categorie' }}</td>
                    <td>{{ number_format($currentStock, 3, ',', ' ') }}</td>
                    <td>{{ number_format((float) $product->min_stock, 3, ',', ' ') }}</td>
                    <td>{{ number_format($valuation, 0, ',', ' ') }} XOF</td>
                    <td>
                        <span class="badge {{ $isAlert ? 'badge-muted' : 'badge-success' }}">
                            {{ $isAlert ? 'A surveiller' : 'Correct' }}
                        </span>
                    </td>
                    <td><a href="{{ route('stock.show', ['product' => $product->id, 'warehouse_id' => $filters['warehouse_id'] ?? null]) }}">Voir</a></td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="muted">Aucun article ne correspond aux filtres selectionnes.</td>
                </tr>
            @endforelse
            </tbody>
        </table>

        @if (method_exists($products, 'links'))
            <div style="margin-top:18px;">{{ $products->links() }}</div>
        @endif
    </section>
@endsection


