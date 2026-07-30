@extends('layouts.app')

@php
    $productLabel = $businessVocabulary['product'] ?? 'Produit';
    $productsLabel = $businessVocabulary['products'] ?? 'Produits';
@endphp

@section('title', $productsLabel.' - Nema ERP')
@section('page-title', 'Catalogue '.$productsLabel)
@section('layout-mode', 'compact')

@push('page-styles')
    <style>
        .premium-page {
            display: grid;
            gap: 20px;
        }
        .premium-hero {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, rgba(255, 249, 240, 0.98) 0%, rgba(240, 248, 246, 0.96) 58%, rgba(255, 241, 221, 0.92) 100%);
            border-color: rgba(11, 79, 86, 0.12);
        }
        .premium-hero::after {
            content: "";
            position: absolute;
            width: 220px;
            height: 220px;
            right: -56px;
            top: -48px;
            border-radius: 999px;
            background: rgba(197, 106, 24, 0.12);
            filter: blur(6px);
            pointer-events: none;
        }
        .premium-hero__grid {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: minmax(0, 1.35fr) minmax(280px, .9fr);
            gap: 20px;
            align-items: start;
        }
        .premium-hero__copy {
            display: grid;
            gap: 12px;
        }
        .premium-hero__copy h2 {
            margin: 0;
            font-size: clamp(28px, 4vw, 42px);
            line-height: 1.02;
            letter-spacing: -.04em;
        }
        .premium-hero__copy p {
            margin: 0;
            max-width: 780px;
        }
        .premium-panel {
            border: 1px solid rgba(102, 82, 56, 0.1);
            border-radius: 20px;
            padding: 16px 18px;
            background: rgba(255, 255, 255, 0.72);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.85);
        }
        .premium-panel strong {
            display: block;
            margin-bottom: 8px;
            font-size: 16px;
        }
        .premium-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .premium-metric-grid {
            display: grid;
            gap: 16px;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        }
        .premium-metric-card {
            border: 1px solid rgba(102, 82, 56, 0.1);
            border-radius: 22px;
            padding: 18px;
            background: linear-gradient(180deg, rgba(255, 254, 251, 0.98) 0%, rgba(247, 239, 228, 0.94) 100%);
            box-shadow: var(--shadow-soft);
        }
        .premium-metric-card .label {
            color: var(--muted);
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: .12em;
            font-weight: 800;
        }
        .premium-metric-card .value {
            margin-top: 10px;
            font-size: 34px;
            font-weight: 800;
            letter-spacing: -.04em;
        }
        .premium-metric-card .hint {
            margin-top: 8px;
            color: var(--muted);
            font-size: 13px;
        }
        .premium-filter-card {
            background: linear-gradient(180deg, rgba(255, 252, 247, 0.96) 0%, rgba(245, 237, 225, 0.88) 100%);
        }
        .premium-section-head {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: flex-start;
            margin-bottom: 14px;
        }
        .premium-section-head h3 {
            margin: 0;
            font-size: 22px;
            letter-spacing: -.03em;
        }
        .premium-section-head p {
            margin: 6px 0 0;
        }
        .catalog-table {
            --product-inline-size: 44px;
            --product-inline-radius: 14px;
            --product-inline-gap: 10px;
            --product-inline-title-size: 13px;
            --product-inline-meta-size: 11px;
            --product-inline-indicator-size: 10px;
            --table-cell-padding-y: 9px;
            --table-cell-padding-x: 8px;
            --product-action-padding-y: 6px;
            --product-action-padding-x: 8px;
        }
        .catalog-table.is-detailed {
            --product-inline-size: 58px;
            --product-inline-radius: 18px;
            --product-inline-gap: 12px;
            --product-inline-title-size: 14px;
            --product-inline-meta-size: 12px;
            --product-inline-indicator-size: 12px;
            --table-cell-padding-y: 12px;
            --table-cell-padding-x: 10px;
            --product-action-padding-y: 7px;
            --product-action-padding-x: 10px;
        }
        .catalog-table table th,
        .catalog-table table td {
            padding: var(--table-cell-padding-y) var(--table-cell-padding-x);
            vertical-align: middle;
        }
        .catalog-table tbody tr {
            transition: background .18s ease, transform .18s ease;
        }
        .catalog-table tbody tr:hover {
            background: rgba(15, 118, 110, 0.04);
        }
        .catalog-table .product-actions {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        .catalog-table .product-actions .button {
            padding: var(--product-action-padding-y) var(--product-action-padding-x);
            border-radius: 10px;
            font-size: 13px;
        }
        .table-tools {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }
        .table-note {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
            color: var(--muted);
        }
        .table-note strong {
            color: var(--text);
        }
        .mode-switch {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px;
            border-radius: 16px;
            border: 1px solid rgba(102, 82, 56, 0.12);
            background: rgba(255, 255, 255, 0.82);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.92);
        }
        .mode-switch .button {
            padding: 7px 10px;
            border-radius: 10px;
            font-size: 13px;
        }
        .mode-switch .button.is-active {
            background: var(--brand);
            color: #fff;
        }
        .premium-table-card {
            overflow: hidden;
        }
        @media (max-width: 1280px) {
            .catalog-table .col-optional-lg {
                display: none;
            }
        }
        @media (max-width: 1080px) {
            .premium-hero__grid {
                grid-template-columns: 1fr;
            }
            .catalog-table .col-optional-md {
                display: none;
            }
        }
        @media (max-width: 860px) {
            .catalog-table .col-optional-sm {
                display: none;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $canViewProductCosts = auth()->user()?->hasPermission('products.cost.view');
        $currentView = $filters['view'] ?? 'list';
        $visibleProducts = collect(method_exists($products, 'items') ? $products->items() : $products);
        $visibleStockable = $visibleProducts->where('type', 'stockable');
        $visibleServices = $visibleProducts->where('type', 'service')->count();
        $visibleAlerts = $visibleStockable->filter(fn ($product) => ((float) ($product->current_stock ?? 0)) <= (float) $product->min_stock)->count();
        $visibleActive = $visibleProducts->where('is_active', true)->count();
        $resultTotal = method_exists($products, 'total') ? $products->total() : $visibleProducts->count();
        $hasActiveFilters = collect($filters)->except('view')->filter(fn ($value) => filled($value))->isNotEmpty();
    @endphp

    <div class="premium-page erp-work-page">
        <section class="erp-work-toolbar">
            <div class="erp-work-toolbar__context">
                <span class="badge badge-muted">Catalogue</span>
                <strong>{{ number_format($resultTotal, 0, ',', ' ') }} reference(s)</strong>
            </div>
            <div class="erp-work-toolbar__actions">
                @allowed('imports.manage')
                    <a href="{{ route('imports.index') }}" class="button button-secondary">Importer Excel</a>
                @endallowed
                @allowed('products.manage')
                    <form method="POST" action="{{ route('products.cleanup-invalid') }}" onsubmit="return confirm('Supprimer les produits inutilises sans nom reel ou sans prix, et archiver ceux deja utilises ?');">
                        @csrf
                        <button type="submit" class="button button-secondary">Nettoyer le catalogue</button>
                    </form>
                    <a href="{{ route('products.create') }}" class="button button-primary">Nouveau {{ $productLabel }}</a>
                @endallowed
            </div>
        </section>

        <section class="premium-metric-grid erp-kpi-strip">
            <article class="premium-metric-card erp-kpi-card">
                <div class="label">References</div>
                <div class="value">{{ number_format($resultTotal, 0, ',', ' ') }}</div>
                <div class="hint">Catalogue trouve avec les filtres courants.</div>
            </article>
            <article class="premium-metric-card erp-kpi-card">
                <div class="label">Actifs visibles</div>
                <div class="value">{{ number_format($visibleActive, 0, ',', ' ') }}</div>
                <div class="hint">{{ $productsLabel }} actifs dans cette vue.</div>
            </article>
            <article class="premium-metric-card erp-kpi-card">
                <div class="label">Services visibles</div>
                <div class="value">{{ number_format($visibleServices, 0, ',', ' ') }}</div>
                <div class="hint">Sans impact stock.</div>
            </article>
            <article class="premium-metric-card erp-kpi-card">
                <div class="label">Stock a surveiller</div>
                <div class="value">{{ number_format($visibleAlerts, 0, ',', ' ') }}</div>
                <div class="hint">Articles sous ou au niveau mini dans cette page.</div>
            </article>
        </section>

        <details class="card premium-filter-card erp-filter-panel" @if ($hasActiveFilters) open @endif>
            <summary>
                <span>Filtres catalogue</span>
                <span class="muted">{{ $hasActiveFilters ? 'Filtres actifs' : 'Tous les '.$productsLabel }}</span>
            </summary>
            <div class="erp-filter-panel__body">
                <form method="GET" action="{{ route('products.index') }}" class="form-grid" style="align-items:end; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));">
                <input type="hidden" name="view" value="{{ $currentView }}">
                <div style="grid-column:span 2; min-width:220px;">
                    <label for="search">Recherche</label>
                    <input type="text" id="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nom, SKU, code-barres ou categorie...">
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
                    <label for="type">Type</label>
                    <select id="type" name="type">
                        <option value="">Tous les types</option>
                        <option value="stockable" @selected(($filters['type'] ?? null) === 'stockable')>Article stockable</option>
                        <option value="service" @selected(($filters['type'] ?? null) === 'service')>Service</option>
                    </select>
                </div>
                <div>
                    <label for="status">Statut</label>
                    <select id="status" name="status">
                        <option value="">Tous les statuts</option>
                        <option value="active" @selected(($filters['status'] ?? null) === 'active')>Actif</option>
                        <option value="inactive" @selected(($filters['status'] ?? null) === 'inactive')>Archive</option>
                    </select>
                </div>
                <div>
                    <label for="stock_state">Etat de stock</label>
                    <select id="stock_state" name="stock_state">
                        <option value="">Tous les etats</option>
                        <option value="low" @selected(($filters['stock_state'] ?? null) === 'low')>A surveiller</option>
                        <option value="positive" @selected(($filters['stock_state'] ?? null) === 'positive')>Disponible</option>
                        <option value="zero" @selected(($filters['stock_state'] ?? null) === 'zero')>Rupture / zero</option>
                    </select>
                </div>
                <div class="actions" style="margin-top:0; justify-content:flex-start; align-self:end;">
                    <button type="submit" class="button button-primary">Filtrer</button>
                    <a href="{{ route('products.index', ['view' => $currentView]) }}" class="button button-secondary">Reinitialiser</a>
                </div>
                </form>
            </div>
        </details>

        <div class="table-tools">
            <div class="table-note">
                <strong>{{ number_format($visibleProducts->count(), 0, ',', ' ') }}</strong>
                <span>ligne(s) visibles sur cette page.</span>
                @if ($currentView === 'list')
                    <span>Mode d affichage memorise dans le navigateur.</span>
                @else
                    <span>Lecture par cartes pour un scan plus rapide.</span>
                @endif
            </div>
            <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                @include('partials.erp-view-switcher', [
                    'view' => $currentView,
                    'label' => 'Vue catalogue',
                    'listUrl' => route('products.index', array_merge(request()->query(), ['view' => 'list'])),
                    'kanbanUrl' => route('products.index', array_merge(request()->query(), ['view' => 'kanban'])),
                ])
                @if ($currentView === 'list')
                    <div class="mode-switch" data-display-controls="products">
                        <button type="button" class="button button-secondary is-active" data-mode="compact">Compact</button>
                        <button type="button" class="button button-secondary" data-mode="detailed">Detaille</button>
                    </div>
                @endif
            </div>
        </div>

        @if ($currentView === 'kanban')
            <div class="erp-kanban-grid">
                @forelse ($products as $product)
                    @php
                        $currentStock = (float) ($product->current_stock ?? 0);
                        $isStockable = $product->type === 'stockable';
                        $isLowStock = $isStockable && $currentStock <= (float) $product->min_stock;
                        $stockLabel = ! $isStockable
                            ? 'Service'
                            : ($currentStock <= 0 ? 'Rupture' : ($isLowStock ? 'A surveiller' : 'Disponible'));
                        $stockTone = ! $isStockable
                            ? 'muted'
                            : ($currentStock <= 0 ? 'danger' : ($isLowStock ? 'warning' : 'success'));
                        $cardTone = $stockTone === 'danger' ? 'danger' : ($stockTone === 'warning' ? 'warning' : 'success');
                    @endphp
                    <section class="card erp-kanban-card erp-kanban-card--{{ $cardTone }}">
                        <div class="erp-kanban-head">
                            <div class="erp-kanban-copy">
                                <div class="erp-kanban-code">{{ $product->sku }}</div>
                                @include('partials.product-inline', [
                                    'product' => $product,
                                    'meta' => collect([$product->unit, $product->category?->name])->filter()->implode(' | '),
                                    'size' => 52,
                                ])
                            </div>
                            <div style="display:grid; gap:8px; justify-items:end;">
                                @include('partials.erp-status-badge', [
                                    'label' => $stockLabel,
                                    'tone' => $stockTone,
                                ])
                                @include('partials.erp-status-badge', [
                                    'label' => $product->is_active ? 'Actif' : 'Archive',
                                    'tone' => $product->is_active ? 'success' : 'muted',
                                ])
                            </div>
                        </div>
                        <div class="erp-kanban-stats">
                            <div class="erp-kanban-stat">
                                <div class="label">Prix vente</div>
                                <div class="value">{{ number_format((float) $product->sale_price, 0, ',', ' ') }}</div>
                            </div>
                            <div class="erp-kanban-stat">
                                <div class="label">Stock</div>
                                <div class="value">{{ $isStockable ? number_format($currentStock, 0, ',', ' ') : 'N/A' }}</div>
                            </div>
                            <div class="erp-kanban-stat">
                                <div class="label">Minimum</div>
                                <div class="value">{{ $isStockable ? number_format((float) $product->min_stock, 0, ',', ' ') : 'N/A' }}</div>
                            </div>
                        </div>
                        <div class="erp-kanban-copy">
                            <p class="muted">{{ $product->barcode ?: 'Code-barres non renseigne' }}</p>
                            <p class="muted">{{ $isStockable ? 'Article stockable' : 'Service' }} · {{ $product->category?->name ?? 'Sans categorie' }}</p>
                            @if ($product->variant_label)
                                <p class="muted">{{ $product->variant_label }}</p>
                            @endif
                        </div>
                        <div class="erp-kanban-actions">
                            <a href="{{ route('products.show', $product) }}" class="button button-secondary">Ouvrir fiche</a>
                            @allowed('products.manage')
                                <a href="{{ route('products.edit', $product) }}" class="button button-secondary">Modifier</a>
                            @endallowed
                        </div>
                    </section>
                @empty
                    <section class="card empty-state" style="grid-column:1 / -1;">
                        <h3>Aucun produit ne correspond aux filtres selectionnes.</h3>
                        <p class="muted">Essaie une autre categorie, un autre statut ou un autre etat de stock.</p>
                    </section>
                @endforelse
            </div>
            @if (method_exists($products, 'links'))
                <div style="margin-top:18px;">{{ $products->links() }}</div>
            @endif
        @else
            <section class="card table-wrap catalog-table is-compact premium-table-card" data-display-table="products">
                <table>
                    <thead>
                    <tr>
                        <th>SKU</th>
                        <th class="col-optional-lg">Code-barres</th>
                        <th>Produit</th>
                        <th class="col-optional-sm">Type</th>
                        <th class="col-optional-md">Categorie</th>
                        <th>Stock actuel</th>
                        <th>PU vente</th>
                        @if ($canViewProductCosts)
                            <th class="col-optional-lg">PU achat</th>
                        @endif
                        <th class="col-optional-md">Stock mini</th>
                        <th>Etat stock</th>
                        <th>Statut</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($products as $product)
                        @php
                            $currentStock = (float) ($product->current_stock ?? 0);
                            $isStockable = $product->type === 'stockable';
                            $isLowStock = $isStockable && $currentStock <= (float) $product->min_stock;
                            $stockLabel = ! $isStockable
                                ? 'Service'
                                : ($currentStock <= 0 ? 'Rupture' : ($isLowStock ? 'A surveiller' : 'Disponible'));
                            $stockTone = ! $isStockable
                                ? 'muted'
                                : ($currentStock <= 0 ? 'danger' : ($isLowStock ? 'warning' : 'success'));
                        @endphp
                        <tr>
                            <td><strong>{{ $product->sku }}</strong></td>
                            <td class="col-optional-lg">{{ $product->barcode ?: 'Non renseigne' }}</td>
                            <td>
                                @include('partials.product-inline', [
                                    'product' => $product,
                                    'meta' => collect([$product->unit, $product->category?->name])->filter()->implode(' | '),
                                    'size' => 48,
                                ])
                            </td>
                            <td class="col-optional-sm">{{ $isStockable ? 'Stockable' : 'Service' }}</td>
                            <td class="col-optional-md">{{ $product->category?->name ?? 'Sans categorie' }}</td>
                            <td>{{ $isStockable ? number_format($currentStock, 3, ',', ' ') : 'Non gere' }}</td>
                            <td>{{ number_format((float) $product->sale_price, 0, ',', ' ') }} XOF</td>
                            @if ($canViewProductCosts)
                                <td class="col-optional-lg">{{ number_format((float) $product->purchase_price, 0, ',', ' ') }} XOF</td>
                            @endif
                            <td class="col-optional-md">{{ $isStockable ? number_format((float) $product->min_stock, 3, ',', ' ') : 'Non gere' }}</td>
                            <td>
                                @include('partials.erp-status-badge', [
                                    'label' => $stockLabel,
                                    'tone' => $stockTone,
                                ])
                            </td>
                            <td>
                                @include('partials.erp-status-badge', [
                                    'label' => $product->is_active ? 'Actif' : 'Archive',
                                    'tone' => $product->is_active ? 'success' : 'muted',
                                ])
                            </td>
                            <td>
                                <div class="product-actions">
                                    <a href="{{ route('products.show', $product) }}" class="button button-secondary">Voir</a>
                                    @allowed('products.manage')
                                        <a href="{{ route('products.edit', $product) }}" class="button button-secondary">Modifier</a>
                                    @endallowed
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="muted">Aucun produit ne correspond aux filtres selectionnes.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>

                @if (method_exists($products, 'links'))
                    <div style="margin-top:18px;">{{ $products->links() }}</div>
                @endif
            </section>
        @endif
    </div>

    @if ($currentView === 'list')
        <script>
            (() => {
                const storageKey = 'nema.products.display_mode';
                const table = document.querySelector('[data-display-table="products"]');
                const controls = document.querySelector('[data-display-controls="products"]');
                const buttons = controls ? Array.from(controls.querySelectorAll('[data-mode]')) : [];

                if (!table || !buttons.length) {
                    return;
                }

                const applyMode = (mode) => {
                    const nextMode = mode === 'detailed' ? 'detailed' : 'compact';
                    table.classList.remove('is-compact', 'is-detailed');
                    table.classList.add(nextMode === 'detailed' ? 'is-detailed' : 'is-compact');
                    buttons.forEach((button) => {
                        button.classList.toggle('is-active', button.dataset.mode === nextMode);
                    });
                };

                applyMode(localStorage.getItem(storageKey) || 'compact');

                buttons.forEach((button) => {
                    button.addEventListener('click', () => {
                        const mode = button.dataset.mode === 'detailed' ? 'detailed' : 'compact';
                        localStorage.setItem(storageKey, mode);
                        applyMode(mode);
                    });
                });
            })();
        </script>
    @endif
@endsection
