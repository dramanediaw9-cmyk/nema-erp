@extends('layouts.app')

@section('title', 'Inventaire rapide')
@section('page-title', 'Inventaire rapide')
@section('layout-mode', 'compact')

@section('content')
    <style>
        .quick-count-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }
        .quick-count-stat {
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 14px;
            background: #fff;
        }
        .quick-count-stat .label {
            color: var(--muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .08em;
        }
        .quick-count-stat .value {
            margin-top: 8px;
            font-size: 24px;
            font-weight: 800;
        }
        .quick-count-stat.is-warning .value { color: #a15c00; }
        .quick-count-stat.is-danger .value { color: #b42318; }
        .quick-count-stat.is-success .value { color: #176b4d; }
    </style>

    <div class="page-head">
        <div>
            <h2 style="margin:0;">Inventaire rapide</h2>
            <div class="muted">Scanne ou recherche un produit, saisis la quantite comptee, Nema calcule l ecart et l applique au stock.</div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="{{ route('stock-counts.index') }}" class="button button-secondary">Inventaires</a>
            <a href="{{ route('stock.index') }}" class="button button-secondary">Stock</a>
        </div>
    </div>

    <section class="card">
        <div class="quick-count-grid">
            <div class="quick-count-stat">
                <div class="label">Produit</div>
                <div class="value" id="quick-product-name">Aucun</div>
            </div>
            <div class="quick-count-stat">
                <div class="label">Stock attendu</div>
                <div class="value" id="quick-expected">0</div>
            </div>
            <div class="quick-count-stat" id="quick-gap-card">
                <div class="label">Ecart</div>
                <div class="value" id="quick-gap">0</div>
            </div>
            <div class="quick-count-stat">
                <div class="label">Valeur correction</div>
                <div class="value" id="quick-value">0 XOF</div>
            </div>
        </div>

        <form id="quick-count-form" method="POST" action="{{ route('stock-counts.quick.store') }}" class="form-grid" style="align-items:end;">
            @csrf
            <div>
                <label for="warehouse_id">Entrepot</label>
                <select id="warehouse_id" name="warehouse_id" required>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected((int) old('warehouse_id', $selectedWarehouse?->id) === $warehouse->id)>{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="full">
                <label for="quick-search">Recherche ou scan</label>
                <input id="quick-search" type="search" placeholder="Scanner code-barres, SKU ou nom produit" autocomplete="off" autofocus>
                <div class="help" id="quick-feedback" style="margin-top:6px;">Le scan selectionne le produit si le code correspond.</div>
            </div>
            <div class="full">
                <label for="product_id">Produit</label>
                <select id="product_id" name="product_id" required>
                    <option value="">Choisir un produit</option>
                    @foreach ($products as $product)
                        <option value="{{ $product['id'] }}" data-label="{{ $product['name'] }} {{ $product['sku'] }} {{ $product['barcode'] }}" @selected((int) old('product_id') === $product['id'])>
                            {{ $product['name'] }} · {{ $product['sku'] }}{{ $product['barcode'] ? ' · '.$product['barcode'] : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="counted_qty">Quantite comptee</label>
                <input id="counted_qty" name="counted_qty" type="number" step="0.001" min="0" value="{{ old('counted_qty') }}" required autofocus>
            </div>
            <div class="full">
                <label for="notes">Note</label>
                <textarea id="notes" name="notes" placeholder="Ex: comptage rayon, correction boutique...">{{ old('notes') }}</textarea>
            </div>
            <div class="actions" style="justify-content:flex-start;">
                <button type="submit" class="button button-primary">Valider et appliquer l ecart</button>
                <button type="submit" name="continue" value="1" class="button button-secondary">Valider puis continuer</button>
            </div>
        </form>
    </section>

    <script>
        let quickProducts = @json($products->values());
        let quickById = Object.fromEntries(quickProducts.map((product) => [String(product.id), product]));
        const warehouseInput = document.getElementById('warehouse_id');
        const searchInput = document.getElementById('quick-search');
        const productInput = document.getElementById('product_id');
        const countedInput = document.getElementById('counted_qty');
        const feedback = document.getElementById('quick-feedback');
        const productName = document.getElementById('quick-product-name');
        const expectedOutput = document.getElementById('quick-expected');
        const gapOutput = document.getElementById('quick-gap');
        const gapCard = document.getElementById('quick-gap-card');
        const valueOutput = document.getElementById('quick-value');
        const money = (value) => new Intl.NumberFormat('fr-FR').format(Math.round(Number(value) || 0)) + ' XOF';
        const qty = (value) => new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 3 }).format(Number(value) || 0);
        const norm = (value) => String(value || '').trim().toLowerCase();
        const productSearchUrl = @json(route('products.options'));
        let searchTimer = null;
        let searchController = null;

        const activeProduct = () => quickById[String(productInput.value || '')] || null;
        const refreshPreview = () => {
            const product = activeProduct();
            const counted = countedInput.value === '' ? null : Number(countedInput.value || 0);
            const expected = Number(product?.expected_qty || 0);
            const gap = counted === null ? 0 : counted - expected;
            const value = gap * Number(product?.purchase_price || 0);

            productName.textContent = product ? product.name : 'Aucun';
            expectedOutput.textContent = `${qty(expected)} ${product?.unit || ''}`.trim();
            gapOutput.textContent = `${gap > 0 ? '+' : ''}${qty(gap)} ${product?.unit || ''}`.trim();
            valueOutput.textContent = money(value);
            gapCard.classList.toggle('is-success', gap > 0);
            gapCard.classList.toggle('is-danger', gap < 0);
            gapCard.classList.toggle('is-warning', counted === null);
        };

        const selectProduct = (product) => {
            if (!product) {
                return false;
            }
            productInput.value = String(product.id);
            feedback.textContent = `${product.name} selectionne. Saisis la quantite comptee.`;
            countedInput.focus();
            countedInput.select();
            refreshPreview();
            return true;
        };

        const renderProducts = (products) => {
            quickProducts = products;
            quickById = Object.fromEntries(quickProducts.map((product) => [String(product.id), product]));
            const selected = String(productInput.value || '');
            productInput.replaceChildren(new Option('Choisir un produit', ''));
            quickProducts.forEach((product) => {
                const option = new Option(`${product.name} · ${product.sku || ''}${product.barcode ? ` · ${product.barcode}` : ''}`, String(product.id));
                option.dataset.label = `${product.name} ${product.sku || ''} ${product.barcode || ''}`;
                option.selected = selected === String(product.id);
                productInput.add(option);
            });
            refreshPreview();
        };

        const fetchProducts = async (term) => {
            searchController?.abort();
            searchController = new AbortController();
            const url = new URL(productSearchUrl, window.location.origin);
            url.searchParams.set('q', term);
            url.searchParams.set('mode', 'stockable');
            url.searchParams.set('limit', '40');
            url.searchParams.set('warehouse_id', warehouseInput.value);
            feedback.textContent = 'Recherche en cours…';

            try {
                const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: searchController.signal });
                if (response.redirected && new URL(response.url).pathname === '/login') {
                    window.location.assign(response.url);
                    return [];
                }
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                const payload = await response.json();
                const products = (payload.results || []).map((item) => ({
                    id: item.id,
                    sku: item.sku,
                    barcode: item.barcode,
                    name: item.name,
                    unit: item.unit,
                    purchase_price: item.cost,
                    expected_qty: item.expected_qty || 0,
                }));
                renderProducts(products);
                feedback.textContent = products.length ? `${products.length} resultat(s).` : 'Produit introuvable pour ce scan ou cette recherche.';
                return products;
            } catch (error) {
                if (error.name !== 'AbortError') feedback.textContent = 'Recherche indisponible. Reessayez.';
                return [];
            }
        };

        warehouseInput.addEventListener('change', () => {
            const url = new URL(@json(route('stock-counts.quick')), window.location.origin);
            url.searchParams.set('warehouse_id', warehouseInput.value);
            window.location.href = url.toString();
        });

        searchInput.addEventListener('input', () => {
            const term = norm(searchInput.value);
            window.clearTimeout(searchTimer);
            if (term.length < 2) {
                feedback.textContent = term ? 'Saisis au moins 2 caracteres.' : 'Le scan selectionne le produit si le code correspond.';
                return;
            }
            searchTimer = window.setTimeout(() => fetchProducts(term), 220);
        });

        searchInput.addEventListener('keydown', async (event) => {
            if (event.key !== 'Enter') {
                return;
            }
            event.preventDefault();
            window.clearTimeout(searchTimer);
            const term = norm(searchInput.value);
            if (!term) {
                productInput.focus();
                return;
            }
            const results = await fetchProducts(term);
            const exact = results.find((product) => [product.barcode, product.sku].some((field) => norm(field) === term));
            if (selectProduct(exact)) {
                return;
            }
            const match = results.find((product) => norm(product.name).includes(term) || norm(product.sku).includes(term) || norm(product.barcode).includes(term));
            if (!selectProduct(match)) {
                feedback.textContent = 'Produit introuvable pour ce scan ou cette recherche.';
            }
        });

        productInput.addEventListener('change', refreshPreview);
        countedInput.addEventListener('input', refreshPreview);
        refreshPreview();
    </script>
@endsection
