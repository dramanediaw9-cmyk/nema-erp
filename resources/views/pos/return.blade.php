@extends('layouts.app')

@section('title', 'Retour ticket POS - Nema ERP')
@section('page-title', 'Retour / echange ticket POS')

@section('content')
    <style>
        .return-layout { display:grid; gap:18px; }
        .return-grid { display:grid; grid-template-columns:minmax(0, 1.08fr) minmax(360px, .92fr); gap:18px; align-items:start; }
        .return-panel { background:#fff8ee; border:1px solid #ecd9b8; border-radius:20px; box-shadow:0 18px 32px rgba(120, 53, 15, 0.08); padding:18px; }
        .return-panel h3 { margin-top:0; }
        .exchange-browser { display:grid; gap:12px; }
        .exchange-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(170px, 1fr)); gap:10px; }
        .exchange-card { border:1px solid #eddcc0; border-radius:16px; background:#fff; padding:12px; cursor:pointer; text-align:left; }
        .exchange-card-top { display:flex; gap:10px; align-items:flex-start; margin-bottom:8px; }
        .exchange-thumb { width:48px; height:48px; border-radius:14px; display:flex; align-items:center; justify-content:center; overflow:hidden; background:linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); color:#17304f; font-weight:900; border:1px solid #d7deea; flex:0 0 48px; }
        .exchange-thumb img { width:100%; height:100%; object-fit:cover; display:block; }
        .exchange-card strong { display:block; margin-bottom:6px; }
        .exchange-list { display:grid; gap:12px; margin-top:14px; }
        .exchange-line { border:1px solid #ecd9b8; border-radius:16px; background:#fff; padding:12px; display:grid; gap:10px; }
        .exchange-line-grid { display:grid; grid-template-columns:120px 120px 130px 120px auto; gap:10px; align-items:end; }
        .exchange-remove { border:none; border-radius:10px; background:#fde2e2; color:#a12b2b; padding:0 10px; height:36px; font-weight:800; cursor:pointer; }
        @media (max-width: 1100px) { .return-grid { grid-template-columns:1fr; } }
        @media (max-width: 760px) { .exchange-line-grid { grid-template-columns:1fr; } }
    </style>

    <div class="page-head">
        <div>
            <h2 style="margin:0;">{{ $invoice->invoice_number }}</h2>
            <div class="muted">{{ $invoice->customer?->name }} · {{ number_format((float) $invoice->total, 0, ',', ' ') }} XOF · Session {{ $session->session_number }}</div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="{{ route('pos.show', $session) }}" class="button button-secondary">Retour session</a>
            <a href="{{ route('pos.receipt', $invoice) }}" class="button button-secondary">Voir ticket</a>
            <a href="{{ route('pos.receipt.thermal', $invoice) }}" class="button button-secondary">Ticket thermique</a>
        </div>
    </div>

    <form method="POST" action="{{ route('pos.returns.store', $invoice) }}" class="return-layout" id="pos-return-form">
        @csrf
        <input type="hidden" name="pos_session_id" value="{{ $session->id }}">
        <div class="form-grid card" style="margin:0;">
            <div>
                <label for="return_date">Date retour</label>
                <input id="return_date" name="return_date" type="date" value="{{ old('return_date', now()->format('Y-m-d')) }}" required>
            </div>
            <div>
                <label for="method">Mode de reglement / remboursement</label>
                <select id="method" name="method" required>
                    @foreach ($methods as $value => $label)
                        <option value="{{ $value }}" @selected(old('method', 'cash') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="reference">Reference</label>
                <input id="reference" name="reference" value="{{ old('reference') }}" placeholder="Reference retour ou echange">
            </div>
            <div class="full">
                <label for="notes">Motif / commentaire</label>
                <textarea id="notes" name="notes">{{ old('notes') }}</textarea>
            </div>
        </div>

        <div class="return-grid">
            <section class="return-panel">
                <h3>Articles retournes</h3>
                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>Article</th>
                            <th>Vendu</th>
                            <th>Deja retourne</th>
                            <th>Reste</th>
                            <th>Montant restant</th>
                            <th>Quantite retour</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($returnableItems as $index => $item)
                            <tr>
                                <td>
                                    <strong>{{ $item['invoice_item']->description }}</strong>
                                    <div class="muted">{{ $item['invoice_item']->product?->barcode ?: $item['invoice_item']->product?->sku }}</div>
                                    @if ((float) $item['invoice_item']->discount_total > 0)
                                        <div class="help">Remise ligne : {{ number_format((float) $item['invoice_item']->discount_total, 0, ',', ' ') }} XOF</div>
                                    @endif
                                    <input type="hidden" name="items[{{ $index }}][sales_invoice_item_id]" value="{{ $item['sales_invoice_item_id'] }}">
                                </td>
                                <td>{{ number_format((float) $item['invoice_item']->qty, 3, ',', ' ') }}</td>
                                <td>{{ number_format((float) $item['returned_qty'], 3, ',', ' ') }}</td>
                                <td>{{ number_format((float) $item['remaining_qty'], 3, ',', ' ') }}</td>
                                <td>{{ number_format((float) $item['remaining_total'], 0, ',', ' ') }} XOF</td>
                                <td><input type="number" class="return-qty" data-return-price="{{ $item['refund_unit_price'] }}" name="items[{{ $index }}][qty]" min="0" max="{{ $item['remaining_qty'] }}" step="0.001" value="{{ old('items.'.$index.'.qty') }}"></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @error('items')<div class="field-error">{{ $message }}</div>@enderror
            </section>

            <section class="return-panel exchange-browser">
                <div>
                    <h3 style="margin-bottom:6px;">Echange article contre article</h3>
                    <div class="muted">Ajoute les articles remis au client. Le systeme calculera le complement a encaisser ou a rendre.</div>
                </div>

                <div>
                    <input id="exchange-search" type="text" placeholder="Rechercher un article a echanger : code-barres, SKU, nom">
                </div>

                <div id="exchange-category-row" class="pos-chip-row" style="margin:0;">
                    <button type="button" class="pos-chip is-active" data-category="">Tous</button>
                    @foreach ($categories as $category)
                        <button type="button" class="pos-chip" data-category="{{ $category['id'] }}">{{ $category['name'] }}</button>
                    @endforeach
                </div>

                <div id="exchange-grid" class="exchange-grid"></div>
                <div id="exchange-lines" class="exchange-list"></div>
                <div id="exchange-hidden-inputs"></div>

                <div class="card" style="margin:0; padding:14px; border-radius:18px;">
                    <div class="summary-stack">
                        <div class="summary-box"><strong>Total retour</strong><div class="value" id="return-total-box">0 XOF</div></div>
                        <div class="summary-box"><strong>Total echange</strong><div class="value" id="exchange-total-box">0 XOF</div></div>
                        <div class="summary-box"><strong>Solde net</strong><div class="value" id="return-net-box">0 XOF</div><div class="help" id="return-net-label">A rendre au client ou a encaisser selon le solde.</div></div>
                    </div>
                </div>
            </section>
        </div>

        <div class="actions">
            <button type="submit" name="return_mode" value="partial" class="button button-primary">Enregistrer le retour / echange</button>
            <button type="submit" name="return_mode" value="cancel_all" class="button button-danger">Annuler tout le ticket</button>
        </div>
    </form>

    <script>
        let exchangeCatalog = @json($exchangeCatalog);
        const exchangeSearchUrl = @json(route('pos.sales.products', [], false));
        const exchangeSessionId = @json($session->id);
        const initialExchangeItems = @json($initialExchangeItems);
        const exchangeGrid = document.getElementById('exchange-grid');
        const exchangeLines = document.getElementById('exchange-lines');
        const exchangeHiddenInputs = document.getElementById('exchange-hidden-inputs');
        const exchangeSearch = document.getElementById('exchange-search');
        const exchangeCategoryRow = document.getElementById('exchange-category-row');
        const returnInputs = Array.from(document.querySelectorAll('.return-qty'));
        const returnTotalBox = document.getElementById('return-total-box');
        const exchangeTotalBox = document.getElementById('exchange-total-box');
        const returnNetBox = document.getElementById('return-net-box');
        const returnNetLabel = document.getElementById('return-net-label');
        const catalogById = Object.fromEntries(exchangeCatalog.map((product) => [String(product.id), product]));
        let exchangeSearchSequence = 0;
        let exchangeSearchTimer = null;
        const state = {
            search: '',
            category: '',
            items: (Array.isArray(initialExchangeItems) ? initialExchangeItems : []).map((item, index) => ({
                uid: `${Date.now()}-${index}`,
                product_id: String(item.product_id || ''),
                description: item.description || catalogById[String(item.product_id || '')]?.name || '',
                qty: Number(item.qty || 1),
                unit_price: Number(item.unit_price || catalogById[String(item.product_id || '')]?.price || 0),
                discount_type: item.discount_type || 'none',
                discount_value: Number(item.discount_value || 0),
            })),
        };

        const normalize = (value) => (value || '').toString().trim().toLowerCase();
        const money = (value) => new Intl.NumberFormat('fr-FR').format(Math.round(Number(value) || 0)) + ' XOF';
        const esc = (value) => String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\"/g, '&quot;')
            .replace(/'/g, '&#039;');
        const initials = (name) => (String(name || '').trim().split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part.charAt(0).toUpperCase()).join('') || 'PR');
        const thumbHtml = (product) => product.image_url
            ? `<img src="${esc(product.image_url)}" alt="${esc(product.name)}">`
            : esc(initials(product.name));
        const lineSubtotal = (item) => Math.max(Number(item.qty || 0) * Number(item.unit_price || 0), 0);
        const lineDiscount = (item) => item.discount_type === 'fixed' ? Math.min(lineSubtotal(item), Number(item.discount_value || 0)) : item.discount_type === 'percent' ? lineSubtotal(item) * Math.min(Number(item.discount_value || 0), 100) / 100 : 0;
        const lineTotal = (item) => Math.max(lineSubtotal(item) - lineDiscount(item), 0);

        async function loadExchangeCatalog() {
            const sequence = ++exchangeSearchSequence;
            const url = new URL(exchangeSearchUrl, window.location.origin);
            url.searchParams.set('session', String(exchangeSessionId));
            url.searchParams.set('limit', '40');
            if (state.search.trim()) url.searchParams.set('q', state.search.trim());
            if (state.category) url.searchParams.set('category', `catalog:${state.category}`);
            exchangeGrid.innerHTML = '<div class="pos-empty" style="grid-column:1 / -1;">Chargement des articles...</div>';
            try {
                const response = await fetch(url.toString(), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                const payload = await response.json();
                if (sequence !== exchangeSearchSequence) return [];
                exchangeCatalog = Array.isArray(payload.products) ? payload.products : [];
                exchangeCatalog.forEach((product) => { catalogById[String(product.id)] = product; });
                renderExchangeGrid();
                return exchangeCatalog;
            } catch (error) {
                if (sequence === exchangeSearchSequence) {
                    exchangeGrid.innerHTML = '<div class="pos-empty" style="grid-column:1 / -1;">Catalogue indisponible. Verifie la connexion puis reessaie.</div>';
                }
                return [];
            }
        }

        function addExchangeProduct(product) {
            const existing = state.items.find((item) => item.product_id === String(product.id) && item.discount_type === 'none' && Number(item.discount_value || 0) === 0);
            if (existing) {
                existing.qty = Number(existing.qty || 0) + 1;
            } else {
                state.items.push({ uid: `${Date.now()}-${Math.random()}`, product_id: String(product.id), description: product.name, qty: 1, unit_price: Number(product.price || 0), discount_type: 'none', discount_value: 0 });
            }
            renderExchange();
            exchangeSearch.value = '';
            state.search = '';
            void loadExchangeCatalog();
            exchangeSearch.focus();
        }

        function renderExchangeGrid() {
            const term = normalize(state.search);
            const filtered = exchangeCatalog.filter((product) => {
                const categoryMatch = !state.category || String(product.category_id || '') === state.category;
                const searchMatch = !term || [product.name, product.sku, product.barcode, product.category_name].some((field) => normalize(field).includes(term));
                return categoryMatch && searchMatch;
            });

            if (!filtered.length) {
                exchangeGrid.innerHTML = '<div class="pos-empty" style="grid-column:1 / -1;">Aucun article pour cet echange.</div>';
                return;
            }

            exchangeGrid.innerHTML = filtered.map((product) => `
                <button type="button" class="exchange-card" data-product-id="${product.id}">
                    <div class="exchange-card-top">
                        <div class="exchange-thumb">${thumbHtml(product)}</div>
                        <div>
                            <strong>${esc(product.name)}</strong>
                            <div class="muted">${esc(product.category_name || 'Sans categorie')} · ${esc(product.barcode || product.sku || '')}</div>
                        </div>
                    </div>
                    <div class="price">${money(product.price)}</div>
                </button>
            `).join('');
        }

        function renderExchange() {
            exchangeLines.innerHTML = state.items.map((item, index) => {
                const product = catalogById[item.product_id] || {};
                return `
                    <div class="exchange-line">
                        <div style="display:flex; justify-content:space-between; gap:10px; align-items:flex-start;">
                            <div>
                                <strong>${item.description || product.name || 'Article'}</strong>
                                <div class="muted">${product.barcode || product.sku || ''}</div>
                            </div>
                            <button type="button" class="exchange-remove" data-remove="${item.uid}">Supprimer</button>
                        </div>
                        <div class="exchange-line-grid">
                            <div><label>Quantite</label><input type="number" min="0.001" step="0.001" value="${Number(item.qty || 0)}" data-line="${item.uid}" data-field="qty"></div>
                            <div><label>Prix unitaire</label><input type="number" min="0" step="0.01" value="${Number(item.unit_price || 0)}" data-line="${item.uid}" data-field="unit_price"></div>
                            <div><label>Type remise</label><select data-line="${item.uid}" data-field="discount_type"><option value="none" ${item.discount_type === 'none' ? 'selected' : ''}>Aucune</option><option value="fixed" ${item.discount_type === 'fixed' ? 'selected' : ''}>Montant</option><option value="percent" ${item.discount_type === 'percent' ? 'selected' : ''}>%</option></select></div>
                            <div><label>Valeur</label><input type="number" min="0" step="0.01" value="${Number(item.discount_value || 0)}" data-line="${item.uid}" data-field="discount_value"></div>
                            <div><label>Total</label><div class="summary-box" style="margin:0; min-height:44px; display:flex; align-items:center; justify-content:center;">${money(lineTotal(item))}</div></div>
                        </div>
                    </div>
                `;
            }).join('');

            exchangeHiddenInputs.innerHTML = state.items.map((item, index) => `
                <input type="hidden" name="exchange_items[${index}][product_id]" value="${item.product_id}">
                <input type="hidden" name="exchange_items[${index}][description]" value="${(item.description || '').replace(/"/g, '&quot;')}">
                <input type="hidden" name="exchange_items[${index}][qty]" value="${Number(item.qty || 0)}">
                <input type="hidden" name="exchange_items[${index}][unit_price]" value="${Number(item.unit_price || 0)}">
                <input type="hidden" name="exchange_items[${index}][discount_type]" value="${item.discount_type}">
                <input type="hidden" name="exchange_items[${index}][discount_value]" value="${Number(item.discount_value || 0)}">
            `).join('');

            updateTotals();
        }

        function updateTotals() {
            const returnTotal = returnInputs.reduce((carry, input) => carry + (Number(input.value || 0) * Number(input.dataset.returnPrice || 0)), 0);
            const exchangeTotal = state.items.reduce((carry, item) => carry + lineTotal(item), 0);
            const net = exchangeTotal - returnTotal;
            returnTotalBox.textContent = money(returnTotal);
            exchangeTotalBox.textContent = money(exchangeTotal);
            returnNetBox.textContent = money(Math.abs(net));
            returnNetLabel.textContent = net > 0 ? 'Complement a encaisser sur le nouvel article.' : net < 0 ? 'Montant a rembourser au client.' : 'Echange a solde nul.';
        }

        exchangeGrid.addEventListener('click', (event) => {
            const button = event.target.closest('[data-product-id]');
            if (!button) return;
            const product = catalogById[String(button.dataset.productId)];
            if (product) addExchangeProduct(product);
        });

        exchangeCategoryRow.addEventListener('click', (event) => {
            const button = event.target.closest('[data-category]');
            if (!button) return;
            state.category = String(button.dataset.category || '');
            exchangeCategoryRow.querySelectorAll('[data-category]').forEach((chip) => chip.classList.toggle('is-active', chip === button));
            void loadExchangeCatalog();
        });

        exchangeSearch.addEventListener('input', (event) => {
            state.search = event.target.value;
            if (exchangeSearchTimer) window.clearTimeout(exchangeSearchTimer);
            exchangeSearchTimer = window.setTimeout(() => void loadExchangeCatalog(), 120);
        });
        exchangeSearch.addEventListener('keydown', async (event) => {
            if (event.key !== 'Enter') return;
            event.preventDefault();
            const query = normalize(exchangeSearch.value);
            let match = exchangeCatalog.find((product) => [product.barcode, product.sku, product.name].some((field) => normalize(field).includes(query)));
            if (!match) {
                const results = await loadExchangeCatalog();
                match = results.find((product) => [product.barcode, product.sku, product.name].some((field) => normalize(field).includes(query)));
            }
            if (match) addExchangeProduct(match);
        });
        exchangeLines.addEventListener('input', (event) => {
            const uid = event.target.dataset.line;
            const field = event.target.dataset.field;
            if (!uid || !field) return;
            const line = state.items.find((item) => item.uid === uid);
            if (!line) return;
            line[field] = ['qty', 'unit_price', 'discount_value'].includes(field) ? Number(event.target.value || 0) : event.target.value;
            renderExchange();
        });
        exchangeLines.addEventListener('click', (event) => {
            const button = event.target.closest('[data-remove]');
            if (!button) return;
            state.items = state.items.filter((item) => item.uid !== button.dataset.remove);
            renderExchange();
        });
        returnInputs.forEach((input) => input.addEventListener('input', updateTotals));

        renderExchangeGrid();
        renderExchange();
    </script>
@endsection
