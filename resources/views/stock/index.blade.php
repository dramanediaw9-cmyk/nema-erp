@extends('layouts.app')

@section('title', 'Stock - Nema ERP')
@section('page-title', 'Stock par agence')
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
            background: linear-gradient(135deg, rgba(247, 251, 252, 0.98) 0%, rgba(235, 244, 242, 0.96) 55%, rgba(255, 244, 227, 0.9) 100%);
            border-color: rgba(15, 118, 110, 0.14);
        }
        .premium-hero--merchant {
            background: linear-gradient(135deg, rgba(251, 252, 248, 0.98) 0%, rgba(239, 246, 244, 0.96) 50%, rgba(255, 242, 222, 0.94) 100%);
            border-color: rgba(17, 94, 89, 0.16);
        }
        .premium-hero::after {
            content: "";
            position: absolute;
            width: 220px;
            height: 220px;
            right: -60px;
            top: -40px;
            border-radius: 999px;
            background: rgba(15, 118, 110, 0.12);
            filter: blur(8px);
            pointer-events: none;
        }
        .premium-hero__grid {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: minmax(0, 1.35fr) minmax(280px, .95fr);
            gap: 20px;
            align-items: start;
        }
        .premium-hero__copy {
            display: grid;
            gap: 12px;
        }
        .premium-hero__copy h2 {
            margin: 0;
            font-size: clamp(28px, 4vw, 40px);
            line-height: 1.02;
            letter-spacing: -.04em;
        }
        .premium-panel {
            border: 1px solid rgba(102, 82, 56, 0.1);
            border-radius: 20px;
            padding: 16px 18px;
            background: rgba(255, 255, 255, 0.74);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.88);
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
            background: linear-gradient(180deg, rgba(255, 254, 250, 0.96) 0%, rgba(239, 246, 243, 0.94) 100%);
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
            background: linear-gradient(180deg, rgba(255, 252, 247, 0.96) 0%, rgba(241, 247, 245, 0.9) 100%);
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
        .inventory-table {
            --product-inline-size: 42px;
            --product-inline-radius: 12px;
            --product-inline-gap: 10px;
            --product-inline-title-size: 13px;
            --product-inline-meta-size: 11px;
            --product-inline-indicator-size: 10px;
            --table-cell-padding-y: 9px;
            --table-cell-padding-x: 8px;
        }
        .inventory-table.is-detailed {
            --product-inline-size: 52px;
            --product-inline-radius: 15px;
            --product-inline-gap: 12px;
            --product-inline-title-size: 14px;
            --product-inline-meta-size: 12px;
            --product-inline-indicator-size: 11px;
            --table-cell-padding-y: 11px;
            --table-cell-padding-x: 10px;
        }
        .inventory-table table th,
        .inventory-table table td {
            padding: var(--table-cell-padding-y) var(--table-cell-padding-x);
            vertical-align: middle;
        }
        .inventory-table tbody tr:hover {
            background: rgba(15, 118, 110, 0.04);
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
        .premium-pill-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .merchant-filter-pills .button {
            min-height: auto;
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 13px;
        }
        @media (max-width: 1180px) {
            .inventory-table .col-optional-lg {
                display: none;
            }
        }
        @media (max-width: 980px) {
            .premium-hero__grid {
                grid-template-columns: 1fr;
            }
            .inventory-table .col-optional-md {
                display: none;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $isMerchantMode = session('ui_mode') === 'merchant';
        $visibleProducts = collect(method_exists($products, 'items') ? $products->items() : $products);
        $visibleAlertCount = $visibleProducts->filter(fn ($product) => (float) $product->current_stock <= (float) $product->min_stock)->count();
        $visibleValuation = $visibleProducts->sum(fn ($product) => ((float) $product->current_stock) * ((float) $product->purchase_price));
        $visibleUnits = $visibleProducts->sum(fn ($product) => (float) $product->current_stock);
        $visibleReserved = $visibleProducts->sum(fn ($product) => (float) ($product->reserved_stock ?? 0));
        $visibleAvailableToPromise = $visibleProducts->sum(fn ($product) => (float) ($product->available_to_promise ?? 0));
        $visibleZeroCount = $visibleProducts->filter(fn ($product) => (float) $product->current_stock <= 0)->count();
        $visibleHealthyCount = $visibleProducts->filter(fn ($product) => (float) $product->current_stock > (float) $product->min_stock)->count();
        $hasActiveFilters = collect($filters)->filter(fn ($value) => filled($value))->isNotEmpty();
        $merchantFilterLinks = [
            ['label' => 'Tout', 'url' => route('stock.index'), 'active' => ! filled($filters['stock_state'] ?? null)],
            ['label' => 'A surveiller', 'url' => route('stock.index', ['stock_state' => 'low']), 'active' => ($filters['stock_state'] ?? null) === 'low'],
            ['label' => 'Rupture', 'url' => route('stock.index', ['stock_state' => 'zero']), 'active' => ($filters['stock_state'] ?? null) === 'zero'],
            ['label' => 'En stock', 'url' => route('stock.index', ['stock_state' => 'positive']), 'active' => ($filters['stock_state'] ?? null) === 'positive'],
        ];
    @endphp

    <div class="premium-page erp-work-page">
        <section class="erp-work-toolbar">
            <div class="erp-work-toolbar__context">
                <span class="badge {{ $isMerchantMode ? 'badge-success' : 'badge-muted' }}">{{ $isMerchantMode ? 'Stock boutique' : 'Stock' }}</span>
                <strong>{{ $branch?->name ?? 'Agence active' }}</strong>
            </div>
            <div class="erp-work-toolbar__actions">
                <a href="{{ route('stock.index', ['stock_state' => 'zero']) }}" class="button button-secondary">Ruptures</a>
                <a href="{{ route('stock.index', ['stock_state' => 'low']) }}" class="button button-secondary">A surveiller</a>
                <a href="{{ route('stock.movements') }}" class="button button-secondary">Mouvements</a>
                @unless ($isMerchantMode)
                    <a href="{{ route('stock.export', request()->query()) }}" class="button button-secondary">Exporter</a>
                    @allowed('transfers.view')
                        <a href="{{ route('transfers.index') }}" class="button button-secondary">Transferts</a>
                    @endallowed
                @endunless
                @allowed('stock.manage')
                    <a href="{{ route('stock.adjustments.create') }}" class="button button-primary">Ajuster</a>
                @endallowed
            </div>
        </section>

        <section class="premium-metric-grid erp-kpi-strip">
            @if ($isMerchantMode)
                <article class="premium-metric-card erp-kpi-card">
                    <div class="label">Articles visibles</div>
                    <div class="value">{{ number_format($visibleProducts->count(), 0, ',', ' ') }}</div>
                    <div class="hint">References affichees sur cette page.</div>
                </article>
                <article class="premium-metric-card erp-kpi-card">
                    <div class="label">Ruptures visibles</div>
                    <div class="value">{{ number_format($visibleZeroCount, 0, ',', ' ') }}</div>
                    <div class="hint">Articles a zero ou en dessous.</div>
                </article>
                <article class="premium-metric-card erp-kpi-card">
                    <div class="label">A surveiller</div>
                    <div class="value">{{ number_format($visibleAlertCount, 0, ',', ' ') }}</div>
                    <div class="hint">Articles au seuil mini ou en dessous.</div>
                </article>
                <article class="premium-metric-card erp-kpi-card">
                    <div class="label">Encore en stock</div>
                    <div class="value">{{ number_format($visibleHealthyCount, 0, ',', ' ') }}</div>
                    <div class="hint">Articles avec stock au-dessus du minimum.</div>
                </article>
            @else
                <article class="premium-metric-card erp-kpi-card">
                    <div class="label">Articles visibles</div>
                    <div class="value">{{ number_format($visibleProducts->count(), 0, ',', ' ') }}</div>
                    <div class="hint">Nombre de references affichees sur cette page.</div>
                </article>
                <article class="premium-metric-card erp-kpi-card">
                    <div class="label">Stock a surveiller</div>
                    <div class="value">{{ number_format($visibleAlertCount, 0, ',', ' ') }}</div>
                    <div class="hint">Articles au seuil mini ou en dessous.</div>
                </article>
                <article class="premium-metric-card erp-kpi-card">
                    <div class="label">Valorisation visible</div>
                    <div class="value">{{ number_format($visibleValuation, 0, ',', ' ') }}</div>
                    <div class="hint">Approximation sur les lignes affichees.</div>
                </article>
                <article class="premium-metric-card erp-kpi-card">
                    <div class="label">Quantite visible</div>
                    <div class="value">{{ number_format($visibleUnits, 3, ',', ' ') }}</div>
                    <div class="hint">Somme des quantites physiques de cette vue.</div>
                </article>
                <article class="premium-metric-card erp-kpi-card">
                    <div class="label">Stock reserve</div>
                    <div class="value">{{ number_format($visibleReserved, 3, ',', ' ') }}</div>
                    <div class="hint">Quantites deja promises sur commandes confirmees.</div>
                </article>
                <article class="premium-metric-card erp-kpi-card">
                    <div class="label">Disponible a promettre</div>
                    <div class="value">{{ number_format($visibleAvailableToPromise, 3, ',', ' ') }}</div>
                    <div class="hint">Stock vendable restant apres reservations.</div>
                </article>
            @endif
        </section>

        <details class="card premium-filter-card erp-filter-panel" @if ($hasActiveFilters) open @endif>
            <summary>
                <span>{{ $isMerchantMode ? 'Recherche et filtres' : 'Filtres stock' }}</span>
                <span class="muted">{{ $hasActiveFilters ? 'Filtres actifs' : 'Toutes les references' }}</span>
            </summary>
            <div class="erp-filter-panel__body">
                @if ($isMerchantMode)
                    <div class="premium-pill-row merchant-filter-pills" style="margin-bottom:10px;">
                        @foreach ($merchantFilterLinks as $link)
                            <a href="{{ $link['url'] }}" class="button {{ $link['active'] ? 'button-primary' : 'button-secondary' }}">{{ $link['label'] }}</a>
                        @endforeach
                    </div>
                @endif

                <form method="GET" action="{{ route('stock.index') }}" class="form-grid" style="align-items:end; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));">
                <div style="grid-column:span 2; min-width:220px;">
                    <label for="search">{{ $isMerchantMode ? 'Recherche article' : 'Recherche produit' }}</label>
                    <input type="text" id="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="{{ $isMerchantMode ? 'Nom, code ou code-barres...' : 'Nom ou SKU...' }}">
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
                    <label for="tracking_type">Tracabilite</label>
                    <select id="tracking_type" name="tracking_type">
                        <option value="">Toutes</option>
                        <option value="tracked" @selected(($filters['tracking_type'] ?? null) === 'tracked')>Produits traces</option>
                        <option value="lot" @selected(($filters['tracking_type'] ?? null) === 'lot')>Suivi par lot</option>
                        <option value="serial" @selected(($filters['tracking_type'] ?? null) === 'serial')>Suivi par serie</option>
                        <option value="none" @selected(($filters['tracking_type'] ?? null) === 'none')>Non trace</option>
                    </select>
                </div>
                @if (! $isMerchantMode)
                    <div>
                        <label for="warehouse_id">Entrepot</label>
                        <select id="warehouse_id" name="warehouse_id">
                            <option value="">Tous les entrepots</option>
                            @foreach ($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}" @selected((int) ($filters['warehouse_id'] ?? 0) === $warehouse->id)>{{ $warehouse->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div>
                    <label for="stock_state">Etat de stock</label>
                    <select id="stock_state" name="stock_state">
                        <option value="">Tous les etats</option>
                        <option value="low" @selected(($filters['stock_state'] ?? null) === 'low')>A surveiller</option>
                        <option value="positive" @selected(($filters['stock_state'] ?? null) === 'positive')>En stock</option>
                        <option value="zero" @selected(($filters['stock_state'] ?? null) === 'zero')>Rupture / zero</option>
                    </select>
                </div>
                <div>
                    <label for="saleability_state">Stock vendable</label>
                    <select id="saleability_state" name="saleability_state">
                        <option value="">Tous</option>
                        <option value="low" @selected(($filters['saleability_state'] ?? null) === 'low')>Vendable a surveiller</option>
                        <option value="critical" @selected(($filters['saleability_state'] ?? null) === 'critical')>Vendable critique</option>
                        <option value="zero" @selected(($filters['saleability_state'] ?? null) === 'zero')>Vendable nul</option>
                    </select>
                </div>
                <div class="actions" style="margin-top:0; justify-content:flex-start; align-self:end;">
                    <button type="submit" class="button button-primary">{{ $isMerchantMode ? 'Appliquer' : 'Filtrer' }}</button>
                    <a href="{{ route('stock.index') }}" class="button button-secondary">Reinitialiser</a>
                </div>
                </form>
            </div>
        </details>

        @if ($selectedWarehouse)
            <div class="premium-pill-row">
                <span class="badge badge-success">Entrepot filtre : {{ $selectedWarehouse->name }}</span>
            </div>
        @endif

        <div class="table-tools">
            <div class="table-note">
                <strong>{{ number_format($visibleProducts->count(), 0, ',', ' ') }}</strong>
                <span>{{ $isMerchantMode ? 'reference(s) visibles.' : 'ligne(s) visibles.' }}</span>
                <span>{{ $isMerchantMode ? 'Clique sur Voir pour ouvrir la fiche stock d un article.' : 'Affichage stock memorise dans le navigateur.' }}</span>
            </div>
            @unless ($isMerchantMode)
                <div class="mode-switch" data-display-controls="stock">
                    <button type="button" class="button button-secondary is-active" data-mode="compact">Compact</button>
                    <button type="button" class="button button-secondary" data-mode="detailed">Detaille</button>
                </div>
            @endunless
        </div>

        <section class="card table-wrap inventory-table is-compact" data-display-table="stock">
            <table>
                <thead>
                <tr>
                    @unless ($isMerchantMode)
                        <th>SKU</th>
                    @endunless
                    <th>Produit</th>
                    @unless ($isMerchantMode)
                        <th class="col-optional-md">Categorie</th>
                    @endunless
                    <th>{{ $isMerchantMode ? 'Stock' : 'Stock actuel' }}</th>
                    @unless ($isMerchantMode)
                        <th class="col-optional-md">Reserve</th>
                        <th>Disponible</th>
                    @endunless
                    <th>{{ $isMerchantMode ? 'Seuil mini' : 'Stock minimum' }}</th>
                    @unless ($isMerchantMode)
                        <th class="col-optional-lg">Valorisation</th>
                    @endunless
                    <th>Etat</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($products as $product)
                    @php
                        $currentStock = (float) $product->current_stock;
                        $saleableStock = (float) ($product->saleable_stock ?? $currentStock);
                        $valuation = $currentStock * (float) $product->purchase_price;
                        $isTracked = in_array((string) $product->tracking_type, ['lot', 'serial'], true);
                        $riskBasis = $isTracked ? $saleableStock : $currentStock;
                        $isAlert = $riskBasis <= (float) $product->min_stock;
                        $isZero = $riskBasis <= 0;
                        $hasBlockedPhysicalStock = $isTracked && $currentStock > 0 && $saleableStock <= 0;
                        $statusLabel = $hasBlockedPhysicalStock
                            ? 'Stock non vendable'
                            : ($isZero ? 'Rupture' : ($isAlert ? ($isTracked ? 'Vendable critique' : 'A surveiller') : 'En stock'));
                        $statusTone = ($isZero || $hasBlockedPhysicalStock) ? 'danger' : ($isAlert ? 'warning' : 'success');
                    @endphp
                    <tr>
                        @unless ($isMerchantMode)
                            <td><strong>{{ $product->sku }}</strong></td>
                        @endunless
                        <td>
                            @include('partials.product-inline', [
                                'product' => $product,
                                'link' => route('stock.show', ['product' => $product->id, 'warehouse_id' => $filters['warehouse_id'] ?? null]),
                                'meta' => $isMerchantMode
                                    ? collect([$product->sku, $product->unit, $product->category_name])->filter()->implode(' | ')
                                    : collect([$product->unit, $product->category_name])->filter()->implode(' | '),
                                'size' => $isMerchantMode ? 40 : 44,
                            ])
                        </td>
                        @unless ($isMerchantMode)
                            <td class="col-optional-md">{{ $product->category_name ?: 'Sans categorie' }}</td>
                        @endunless
                        <td>{{ number_format($currentStock, 3, ',', ' ') }}</td>
                        @unless ($isMerchantMode)
                            <td class="col-optional-md">{{ number_format((float) ($product->reserved_stock ?? 0), 3, ',', ' ') }}</td>
                            <td>{{ number_format((float) ($product->available_to_promise ?? 0), 3, ',', ' ') }}</td>
                        @endunless
                        <td>{{ number_format((float) $product->min_stock, 3, ',', ' ') }}</td>
                        @unless ($isMerchantMode)
                            <td class="col-optional-lg">{{ number_format($valuation, 0, ',', ' ') }} XOF</td>
                        @endunless
                        <td>
                            @include('partials.erp-status-badge', [
                                'label' => $statusLabel,
                                'tone' => $statusTone,
                            ])
                        </td>
                        <td><a href="{{ route('stock.show', ['product' => $product->id, 'warehouse_id' => $filters['warehouse_id'] ?? null]) }}" class="button button-secondary">Voir</a></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $isMerchantMode ? 5 : 10 }}" class="muted">Aucun article ne correspond aux filtres selectionnes.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>

            @if (method_exists($products, 'links'))
                <div style="margin-top:18px;">{{ $products->links() }}</div>
            @endif
        </section>
    </div>

    <script>
        (() => {
            const storageKey = 'nema.stock.display_mode';
            const table = document.querySelector('[data-display-table="stock"]');
            const controls = document.querySelector('[data-display-controls="stock"]');
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
@endsection
