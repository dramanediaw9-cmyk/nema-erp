<?php
    $supplierLabel = $businessVocabulary['supplier'] ?? 'Fournisseur';
    $purchaseLabel = $businessVocabulary['purchase'] ?? 'Achat';
    $purchasesLabel = $businessVocabulary['purchases'] ?? 'Achats';
    $productLabel = $businessVocabulary['product'] ?? 'Produit';
    $productsLabel = $businessVocabulary['products'] ?? 'Produits';
    $isReceiptSourced = isset($selectedReceipt) && $selectedReceipt;
    $selectedSupplierId = old('supplier_id', $isReceiptSourced ? $selectedReceipt->supplier_id : null);
    $selectedWarehouseId = old('warehouse_id', $isReceiptSourced ? $selectedReceipt->warehouse_id : null);
?>

@if ($isReceiptSourced)
    <div class="card" style="margin-bottom:18px; border-left:4px solid #14532d;">
        <h2 class="section-title" style="margin-top:0;">Facturation depuis reception</h2>
        <div class="grid" style="grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:14px;">
            <div>
                <div class="muted">Reception source</div>
                <div style="font-weight:600; margin-top:6px;"><a href="{{ route('goods-receipts.show', $selectedReceipt) }}">{{ $selectedReceipt->receipt_number }}</a></div>
            </div>
            <div>
                <div class="muted">Commande source</div>
                <div style="font-weight:600; margin-top:6px;"><a href="{{ route('purchase-orders.show', $selectedReceipt->purchaseOrder) }}">{{ $selectedReceipt->purchaseOrder?->order_number }}</a></div>
            </div>
            <div>
                <div class="muted">{{ $supplierLabel }}</div>
                <div style="font-weight:600; margin-top:6px;">{{ $selectedReceipt->supplier?->name }}</div>
            </div>
            <div>
                <div class="muted">Depot receptionne</div>
                <div style="font-weight:600; margin-top:6px;">{{ $selectedReceipt->warehouse?->name }}</div>
            </div>
        </div>
        <div class="muted" style="margin-top:14px;">Les quantites et {{ strtolower($productsLabel) }} suivent la reception source. Tu peux encore ajuster le libelle et le cout facture si le document differe.</div>
        <input type="hidden" name="goods_receipt_id" value="{{ old('goods_receipt_id', $selectedReceipt->id) }}">
        @error('goods_receipt_id')<div class="field-error" style="margin-top:8px;">{{ $message }}</div>@enderror
    </div>
@endif

<div class="split">
    <section class="card">
        <h2 class="section-title">Entete du {{ strtolower($purchaseLabel) }}</h2>
        <div class="muted" style="margin-bottom:16px;">Prepare le document, l'entrepot de reception et son echeance de reglement. Les lignes vides seront ignorees.</div>

        <div class="form-grid">
            <div>
                <label for="supplier_id">{{ $supplierLabel }}</label>
                @if ($isReceiptSourced)
                    <input type="hidden" name="supplier_id" value="{{ $selectedSupplierId }}">
                @endif
                <select id="supplier_id" name="supplier_id" @disabled($isReceiptSourced) required>
                    <option value="">Selectionner</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" data-price-list-id="{{ $supplier->price_list_id ?? '' }}" data-price-list-name="{{ $supplier->priceList?->name ?? '' }}" @selected((string) $selectedSupplierId === (string) $supplier->id)>{{ $supplier->code }} - {{ $supplier->name }}</option>
                    @endforeach
                </select>
                @if ($isReceiptSourced)
                    <div class="help">Le {{ strtolower($supplierLabel) }} est fixe par la reception source.</div>
                @endif
                @error('supplier_id')<div class="field-error">{{ $message }}</div>@enderror
                <div class="help" id="purchase-supplier-pricing">Aucune liste de prix {{ strtolower($supplierLabel) }} specifique: le cout catalogue sera propose.</div>
            </div>
            <div>
                <label for="warehouse_id">Entrepot de reception</label>
                @if ($isReceiptSourced)
                    <input type="hidden" name="warehouse_id" value="{{ $selectedWarehouseId }}">
                @endif
                <select id="warehouse_id" name="warehouse_id" @disabled($isReceiptSourced)>
                    <option value="">Entrepot par defaut de l'agence</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected((string) $selectedWarehouseId === (string) $warehouse->id || (! $selectedWarehouseId && ! $isReceiptSourced && $warehouse->is_default))>{{ $warehouse->code }} - {{ $warehouse->name }}</option>
                    @endforeach
                </select>
                <div class="help">{{ $isReceiptSourced ? 'Le depot suit la reception deja enregistree.' : 'Le stock sera augmente dans cet entrepot a l approbation finale.' }}</div>
                @error('warehouse_id')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="bill_date">Date de facture</label>
                <input id="bill_date" type="date" name="bill_date" value="{{ old('bill_date', now()->format('Y-m-d')) }}" required>
                @error('bill_date')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="due_date">Date d'echeance</label>
                <input id="due_date" type="date" name="due_date" value="{{ old('due_date') }}">
                <div class="chip-row">
                    <button type="button" class="chip" data-due-days="0">Meme jour</button>
                    <button type="button" class="chip" data-due-days="7">+7 jours</button>
                    <button type="button" class="chip" data-due-days="15">+15 jours</button>
                    <button type="button" class="chip" data-due-days="30">+30 jours</button>
                </div>
                <div class="help">Cette date pilote les alertes de reglement sur la liste des {{ strtolower($purchasesLabel) }}.</div>
                @error('due_date')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div>
                <label>Agence active</label>
                <input type="text" value="{{ $branch?->name }}" disabled>
                <div class="help">Les mouvements de stock seront enregistres sur cette agence.</div>
            </div>
            <div class="full">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" placeholder="Reference {{ strtolower($supplierLabel) }}, commentaire, precision logistique...">{{ old('notes', $isReceiptSourced ? $selectedReceipt->notes : null) }}</textarea>
                @error('notes')<div class="field-error">{{ $message }}</div>@enderror
            </div>
        </div>
    </section>

    <aside class="card">
        <h2 class="section-title">Resume du {{ strtolower($purchaseLabel) }}</h2>
        <div class="summary-stack">
            <div class="summary-box">
                <div class="muted">Montant estime</div>
                <div class="value" id="purchase-grand-total">0 XOF</div>
            </div>
            <div class="kpi-row">
                <div class="kpi">
                    <div class="label">Lignes actives</div>
                    <div class="value" id="purchase-lines-count">0</div>
                </div>
                <div class="kpi">
                    <div class="label">Quantite totale</div>
                    <div class="value" id="purchase-total-qty">0</div>
                </div>
            </div>
            <div class="tip-grid">
                <div class="tip-card">
                    <strong>Impact a l'approbation finale</strong>
                    <div class="muted">{{ $isReceiptSourced ? "La dette et la comptabilite seront mises a jour sans doubler le stock deja receptionne." : "Le stock augmente dans l'entrepot choisi et la dette est enregistree." }}</div>
                </div>
                <div class="tip-card">
                    <strong>Bon reflexe</strong>
                    <div class="muted">Renseigne une echeance si tu veux visualiser les reglements prioritaires dans {{ strtolower($purchasesLabel) }}.</div>
                </div>
            </div>
        </div>
    </aside>
</div>

<div class="card" style="margin-top:18px;">
    <div style="display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap; margin-bottom:14px; align-items:flex-start;">
        <div>
            <h2 class="section-title">Lignes du {{ strtolower($purchaseLabel) }}</h2>
            <div class="muted">{{ $isReceiptSourced ? 'Les lignes viennent de la reception source. Ajuste seulement le libelle et le cout facture si besoin.' : 'Choisis les '.$productsLabel.', ajuste les quantites et les couts reels. Les lignes vides ne seront pas traitees.' }}</div>
        </div>
        <div class="tip-card" style="min-width:260px;">
            <strong>Conseil operateur</strong>
            <div class="muted">{{ $isReceiptSourced ? 'Chaque ligne reste rattachee a la reception pour garder une piste d audit claire.' : 'Le cout historique se propose automatiquement a la selection.' }}</div>
        </div>
    </div>

    <div class="table-wrap">
        <table id="purchase-lines-table">
            <thead>
            <tr>
                <th>#</th>
                <th>{{ $productLabel }}</th>
                <th>Description</th>
                <th>Quantite</th>
                <th>Cout unitaire</th>
                <th>Total ligne</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($defaultRows as $index => $row)
                <?php $isReceiptRow = $isReceiptSourced && filled($row['goods_receipt_item_id'] ?? null); ?>
                <tr data-line-index="{{ $index + 1 }}">
                    <td>{{ $index + 1 }}</td>
                    <td>
                        @if ($isReceiptRow)
                            <input type="hidden" name="items[{{ $index }}][goods_receipt_item_id]" value="{{ $row['goods_receipt_item_id'] }}">
                            <input type="hidden" name="items[{{ $index }}][product_id]" value="{{ $row['product_id'] ?? '' }}">
                        @endif
                        <select name="items[{{ $index }}][product_id]" class="line-product" data-product-picker data-product-mode="purchasable" @disabled($isReceiptRow)>
                            <option value="">Choisir</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}" data-name="{{ $product->display_name }}" data-cost="{{ $product->purchase_price }}" data-purchase-description="{{ $product->purchase_description ?: ($product->description ?: $product->display_name) }}" data-unit-summary="{{ $product->purchaseUnitSummary() ?: $product->unit }}" @selected((string) ($row['product_id'] ?? '') === (string) $product->id)>
                                    {{ $product->sku }} - {{ $product->display_name }}{{ $product->purchaseUnitSummary() ? ' · '.$product->purchaseUnitSummary() : '' }}
                                </option>
                            @endforeach
                        </select>
                    </td>
                    <td><input type="text" name="items[{{ $index }}][description]" value="{{ $row['description'] ?? '' }}" class="line-description" placeholder="Description interne ou libelle {{ strtolower($supplierLabel) }}"></td>
                    <td><input type="number" step="0.001" min="0" name="items[{{ $index }}][qty]" value="{{ $row['qty'] ?? '' }}" class="line-qty" inputmode="decimal" @readonly($isReceiptRow)></td>
                    <td><input type="number" step="0.01" min="0" name="items[{{ $index }}][unit_cost]" value="{{ $row['unit_cost'] ?? '' }}" class="line-cost" inputmode="decimal"></td>
                    <td><input type="text" value="0" class="line-total" disabled></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    @if ($errors->has('items') || collect($errors->keys())->contains(fn ($key) => str_starts_with($key, 'items.')))
        <div class="field-error" style="margin-top:12px;">Certaines lignes doivent etre corrigees avant enregistrement.</div>
    @endif

    <div class="table-foot-note">
        <div class="help">Astuce : laisse les lignes inutiles vides, elles seront ignorees automatiquement.</div>
        <div class="help">Controle le total en direct avant de soumettre le document pour approbation.</div>
    </div>

    <div class="actions">
        <a href="{{ route('purchases.index') }}" class="button button-secondary">Annuler</a>
        <button type="submit" class="button button-primary">Enregistrer</button>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const rows = Array.from(document.querySelectorAll('#purchase-lines-table tbody tr'));
        const billDateInput = document.getElementById('bill_date');
        const dueDateInput = document.getElementById('due_date');
        const linesCount = document.getElementById('purchase-lines-count');
        const totalQty = document.getElementById('purchase-total-qty');
        const grandTotal = document.getElementById('purchase-grand-total');
        const dueButtons = document.querySelectorAll('[data-due-days]');
        const moneyFormatter = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 });
        const qtyFormatter = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 3 });

        const formatMoney = (value) => moneyFormatter.format(value || 0) + ' XOF';

        const setDueDate = (days) => {
            if (!billDateInput.value) {
                return;
            }

            const baseDate = new Date(billDateInput.value + 'T00:00:00');
            baseDate.setDate(baseDate.getDate() + days);
            dueDateInput.value = baseDate.toISOString().slice(0, 10);
        };

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

                if (selectedOption && selectedOption.value && !costInput.value) {
                    costInput.value = selectedOption.dataset.cost || '0';
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

        dueButtons.forEach((button) => {
            button.addEventListener('click', function () {
                setDueDate(parseInt(this.dataset.dueDays || '0', 10));
            });
        });

        compute();
    });
</script>

@include('partials.document-line-pricing', [
    'tableId' => 'purchase-lines-table',
    'partnerSelectId' => 'supplier_id',
    'pricingHintId' => 'purchase-supplier-pricing',
    'lineAmountClass' => 'line-cost',
    'priceDataKey' => 'cost',
    'partnerKind' => strtolower($supplierLabel),
    'defaultPricingText' => 'Aucune liste de prix '.strtolower($supplierLabel).' specifique: le cout catalogue sera propose.',
])

