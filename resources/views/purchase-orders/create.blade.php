@extends('layouts.app')

@section('title', 'Nouvelle commande fournisseur - Nema ERP')
@section('page-title', 'Nouvelle commande fournisseur')

@section('content')
<form method="POST" action="{{ route('purchase-orders.store') }}">
    @csrf
    <div class="split">
        <section class="card">
            <h2 class="section-title">Entete</h2>
            <div class="muted" style="margin-bottom:16px;">Prepare une commande fournisseur avec tarif source, unite d achat et date de reception attendue.</div>
            <div class="form-grid">
                <div>
                    <label for="supplier_id">Fournisseur</label>
                    <select id="supplier_id" name="supplier_id" required>
                        <option value="">Selectionner</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" data-price-list-id="{{ $supplier->price_list_id ?? '' }}" data-price-list-name="{{ $supplier->priceList?->name ?? '' }}" @selected((string) old('supplier_id') === (string) $supplier->id)>{{ $supplier->code }} - {{ $supplier->name }}</option>
                        @endforeach
                    </select>
                    @error('supplier_id')<div class="field-error">{{ $message }}</div>@enderror
                    <div class="help" id="purchase-order-supplier-pricing">Aucune liste de prix fournisseur specifique: le cout catalogue sera propose.</div>
                </div>
                <div>
                    <label for="warehouse_id">Depot de reception</label>
                    <select id="warehouse_id" name="warehouse_id" required>
                        <option value="">Selectionner</option>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected((string) old('warehouse_id') === (string) $warehouse->id || (! old('warehouse_id') && $warehouse->is_default))>{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                    @error('warehouse_id')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="order_date">Date commande</label>
                    <input id="order_date" type="date" name="order_date" value="{{ old('order_date', now()->format('Y-m-d')) }}" required>
                    @error('order_date')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="expected_receipt_date">Reception attendue</label>
                    <input id="expected_receipt_date" type="date" name="expected_receipt_date" value="{{ old('expected_receipt_date') }}">
                    @error('expected_receipt_date')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label>Agence active</label>
                    <input type="text" value="{{ $branch?->name }}" disabled>
                </div>
                <div class="full">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes">{{ old('notes') }}</textarea>
                    @error('notes')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            </div>
        </section>
        <aside class="card">
            <h2 class="section-title">Resume</h2>
            <div class="summary-stack">
                <div class="summary-box">
                    <div class="muted">Montant estime</div>
                    <div class="value" id="purchase-order-grand-total">0 XOF</div>
                </div>
                <div class="kpi-row">
                    <div class="kpi">
                        <div class="label">Lignes actives</div>
                        <div class="value" id="purchase-order-lines-count">0</div>
                    </div>
                    <div class="kpi">
                        <div class="label">Quantite totale</div>
                        <div class="value" id="purchase-order-total-qty">0</div>
                    </div>
                </div>
                <div class="tip-card"><strong>Stock</strong><div class="muted">Aucun mouvement n est genere tant qu une reception fournisseur n est pas enregistree.</div></div>
            </div>
        </aside>
    </div>

    <section class="card" style="margin-top:18px;">
        <div style="display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap; margin-bottom:14px; align-items:flex-start;">
            <div>
                <h2 class="section-title">Lignes commandees</h2>
                <div class="muted">Choisis les produits d achat. Les couts suivent la liste du fournisseur tant que tu ne saisis pas une valeur manuelle.</div>
            </div>
            <div class="tip-card" style="min-width:260px;">
                <strong>Unites d achat</strong>
                <div class="muted">Les unites commerciales produit apparaissent directement dans les choix pour faciliter les commandes carton, pack ou sac.</div>
            </div>
        </div>

        <div class="table-wrap">
            <table id="purchase-order-lines-table">
                <thead><tr><th>#</th><th>Produit</th><th>Description</th><th>Quantite</th><th>Cout unitaire</th><th>Total ligne</th></tr></thead>
                <tbody>
                @foreach ($defaultRows as $index => $row)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>
                            <select name="items[{{ $index }}][product_id]" class="line-product" data-product-picker data-product-mode="purchasable">
                                <option value="">Choisir</option>
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}" data-name="{{ $product->display_name }}" data-cost="{{ $product->purchase_price }}" data-purchase-description="{{ $product->purchase_description ?: ($product->description ?: $product->display_name) }}" data-unit-summary="{{ $product->purchaseUnitSummary() ?: $product->unit }}" @selected((string) ($row['product_id'] ?? '') === (string) $product->id)>{{ $product->sku }} - {{ $product->display_name }}{{ $product->purchaseUnitSummary() ? ' · '.$product->purchaseUnitSummary() : '' }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td><input type="text" name="items[{{ $index }}][description]" value="{{ $row['description'] ?? '' }}" class="line-description"></td>
                        <td><input type="number" step="0.001" min="0" name="items[{{ $index }}][qty]" value="{{ $row['qty'] ?? '' }}" class="line-qty" inputmode="decimal"></td>
                        <td><input type="number" step="0.01" min="0" name="items[{{ $index }}][unit_cost]" value="{{ $row['unit_cost'] ?? '' }}" class="line-cost" inputmode="decimal"></td>
                        <td><input type="text" value="0" class="line-total" disabled></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        @if ($errors->has('items') || collect($errors->keys())->contains(fn ($key) => str_starts_with($key, 'items.')))
            <div class="field-error" style="margin-top:12px;">Certaines lignes de commande fournisseur doivent etre corrigees avant enregistrement.</div>
        @endif

        <div class="actions">
            <a href="{{ route('purchase-orders.index') }}" class="button button-secondary">Annuler</a>
            <button type="submit" class="button button-primary">Enregistrer la commande</button>
        </div>
    </section>
</form>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const rows = Array.from(document.querySelectorAll('#purchase-order-lines-table tbody tr'));
        const linesCount = document.getElementById('purchase-order-lines-count');
        const totalQty = document.getElementById('purchase-order-total-qty');
        const grandTotal = document.getElementById('purchase-order-grand-total');
        const moneyFormatter = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 });
        const qtyFormatter = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 3 });

        const formatMoney = (value) => moneyFormatter.format(value || 0) + ' XOF';

        const compute = () => {
            let activeLines = 0;
            let totalQuantity = 0;
            let totalAmount = 0;

            rows.forEach((row) => {
                const productSelect = row.querySelector('.line-product');
                const descriptionInput = row.querySelector('.line-description');
                const qtyInput = row.querySelector('.line-qty');
                const costInput = row.querySelector('.line-cost');
                const totalInput = row.querySelector('.line-total');
                const selectedOption = productSelect.options[productSelect.selectedIndex];
                const qty = parseFloat(qtyInput.value || '0');
                const cost = parseFloat(costInput.value || '0');
                const lineTotal = qty * cost;

                if (selectedOption && selectedOption.value && !descriptionInput.value.trim()) {
                    descriptionInput.value = selectedOption.dataset.purchaseDescription || selectedOption.dataset.name || '';
                }

                totalInput.value = lineTotal > 0 ? lineTotal.toFixed(2) : '0';

                if (selectedOption && selectedOption.value && qty > 0) {
                    activeLines += 1;
                    totalQuantity += qty;
                    totalAmount += lineTotal;
                }
            });

            linesCount.textContent = String(activeLines);
            totalQty.textContent = qtyFormatter.format(totalQuantity || 0);
            grandTotal.textContent = formatMoney(totalAmount);
        };

        rows.forEach((row) => {
            row.querySelectorAll('select, input').forEach((input) => {
                input.addEventListener('change', compute);
                input.addEventListener('input', compute);
            });
        });

        compute();
    });
</script>

@include('partials.document-line-pricing', [
    'tableId' => 'purchase-order-lines-table',
    'partnerSelectId' => 'supplier_id',
    'pricingHintId' => 'purchase-order-supplier-pricing',
    'lineAmountClass' => 'line-cost',
    'priceDataKey' => 'cost',
    'partnerKind' => 'fournisseur',
    'defaultPricingText' => 'Aucune liste de prix fournisseur specifique: le cout catalogue sera propose.',
])
@endsection

