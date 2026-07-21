<div class="split">
    <section class="card">
        <h2 class="section-title">Entete du devis</h2>
        <div class="muted" style="margin-bottom:16px;">Prepare une proposition commerciale sans impact sur le stock ni sur la comptabilite. La conversion en facture se fera plus tard.</div>

        <div class="form-grid">
            <div>
                <label for="customer_id">Client</label>
                <select id="customer_id" name="customer_id" required>
                    <option value="">Selectionner un client</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}" data-price-list-id="{{ $customer->price_list_id ?? '' }}" data-price-list-name="{{ $customer->priceList?->name ?? '' }}" @selected((string) old('customer_id') === (string) $customer->id)>{{ $customer->code }} - {{ $customer->name }}</option>
                    @endforeach
                </select>
                @error('customer_id')<div class="field-error">{{ $message }}</div>@enderror
                <div class="help" id="quote-customer-pricing">Aucune liste de prix specifique: le prix catalogue sera propose.</div>
            </div>
            <div>
                <label for="quote_date">Date du devis</label>
                <input id="quote_date" type="date" name="quote_date" value="{{ old('quote_date', now()->format('Y-m-d')) }}" required>
                @error('quote_date')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="valid_until">Valable jusqu au</label>
                <input id="valid_until" type="date" name="valid_until" value="{{ old('valid_until') }}">
                <div class="chip-row">
                    <button type="button" class="chip" data-validity-days="7">+7 jours</button>
                    <button type="button" class="chip" data-validity-days="15">+15 jours</button>
                    <button type="button" class="chip" data-validity-days="30">+30 jours</button>
                </div>
                <div class="help">Cette date aide a suivre les devis encore convertibles.</div>
                @error('valid_until')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div>
                <label>Agence active</label>
                <input type="text" value="{{ $branch?->name }}" disabled>
                <div class="help">Le devis sera rattache a cette agence commerciale.</div>
            </div>
            <div class="full">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" placeholder="Objet du devis, remarque commerciale, delai propose...">{{ old('notes') }}</textarea>
                @error('notes')<div class="field-error">{{ $message }}</div>@enderror
            </div>
        </div>
    </section>

    <aside class="card">
        <h2 class="section-title">Resume du devis</h2>
        <div class="summary-stack">
            <div class="summary-box">
                <div class="muted">Montant propose</div>
                <div class="value" id="quote-grand-total">0 XOF</div>
            </div>
            <div class="kpi-row">
                <div class="kpi">
                    <div class="label">Lignes actives</div>
                    <div class="value" id="quote-lines-count">0</div>
                </div>
                <div class="kpi">
                    <div class="label">Quantite totale</div>
                    <div class="value" id="quote-total-qty">0</div>
                </div>
            </div>
            <div class="tip-grid">
                <div class="tip-card">
                    <strong>Statut initial</strong>
                    <div class="muted">Le devis est cree en brouillon puis peut etre envoye, accepte et converti.</div>
                </div>
                <div class="tip-card">
                    <strong>Bon reflexe</strong>
                    <div class="muted">Renseigne la validite pour voir rapidement les devis encore exploitables.</div>
                </div>
            </div>
        </div>
    </aside>
</div>

<div class="card" style="margin-top:18px;">
    <div style="display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap; margin-bottom:14px; align-items:flex-start;">
        <div>
            <h2 class="section-title">Lignes du devis</h2>
            <div class="muted">Choisis les produits et ajuste les quantites et prix proposes. Les lignes vides ne seront pas retenues.</div>
        </div>
        <div class="tip-card" style="min-width:260px;">
            <strong>Conseil commercial</strong>
            <div class="muted">Le prix catalogue se propose automatiquement, mais tu peux le modifier librement pour le devis.</div>
        </div>
    </div>

    <div class="table-wrap">
        <table id="quote-lines-table">
            <thead>
            <tr>
                <th>#</th>
                <th>Produit</th>
                <th>Description</th>
                <th>Quantite</th>
                <th>PU</th>
                <th>Total ligne</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($defaultRows as $index => $row)
                <tr data-line-index="{{ $index + 1 }}">
                    <td>{{ $index + 1 }}</td>
                    <td>
                        <select name="items[{{ $index }}][product_id]" class="line-product" data-product-picker data-product-mode="saleable">
                            <option value="">Choisir</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}" data-name="{{ $product->display_name }}" data-price="{{ $product->sale_price }}" data-sale-description="{{ $product->sales_description ?: ($product->description ?: $product->display_name) }}" data-unit-summary="{{ $product->salesUnitSummary() ?: $product->unit }}" @selected((string) ($row['product_id'] ?? '') === (string) $product->id)>
                                    {{ $product->sku }} - {{ $product->display_name }}{{ $product->salesUnitSummary() ? ' · '.$product->salesUnitSummary() : '' }}
                                </option>
                            @endforeach
                        </select>
                    </td>
                    <td><input type="text" name="items[{{ $index }}][description]" value="{{ $row['description'] ?? '' }}" class="line-description" placeholder="Description visible sur le devis"></td>
                    <td><input type="number" step="0.001" min="0" name="items[{{ $index }}][qty]" value="{{ $row['qty'] ?? '' }}" class="line-qty" inputmode="decimal"></td>
                    <td><input type="number" step="0.01" min="0" name="items[{{ $index }}][unit_price]" value="{{ $row['unit_price'] ?? '' }}" class="line-price" inputmode="decimal"></td>
                    <td><input type="text" value="0" class="line-total" disabled></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    @php
        $hasItemErrors = $errors->has('items') || collect($errors->keys())->contains(fn ($key) => str_starts_with($key, 'items.'));
    @endphp
    @if ($hasItemErrors)
        <div class="field-error" style="margin-top:12px;">Certaines lignes du devis doivent etre corrigees avant enregistrement.</div>
    @endif

    <div class="table-foot-note">
        <div class="help">Les lignes vides sont ignorees automatiquement.</div>
        <div class="help">Le total se met a jour en direct pour valider rapidement l'offre avant envoi.</div>
    </div>

    <div class="actions">
        <a href="{{ route('quotes.index') }}" class="button button-secondary">Annuler</a>
        <button type="submit" class="button button-primary">Enregistrer le devis</button>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const rows = Array.from(document.querySelectorAll('#quote-lines-table tbody tr'));
        const quoteDateInput = document.getElementById('quote_date');
        const validUntilInput = document.getElementById('valid_until');
        const linesCount = document.getElementById('quote-lines-count');
        const totalQty = document.getElementById('quote-total-qty');
        const grandTotal = document.getElementById('quote-grand-total');
        const validityButtons = document.querySelectorAll('[data-validity-days]');
        const moneyFormatter = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 });
        const qtyFormatter = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 3 });

        const formatMoney = (value) => moneyFormatter.format(value || 0) + ' XOF';

        const setValidity = (days) => {
            if (!quoteDateInput.value) {
                return;
            }

            const baseDate = new Date(quoteDateInput.value + 'T00:00:00');
            baseDate.setDate(baseDate.getDate() + days);
            validUntilInput.value = baseDate.toISOString().slice(0, 10);
        };

        const compute = () => {
            let activeLines = 0;
            let totalQuantity = 0;
            let totalAmount = 0;

            rows.forEach((row) => {
                const productSelect = row.querySelector('.line-product');
                const descriptionInput = row.querySelector('.line-description');
                const qtyInput = row.querySelector('.line-qty');
                const priceInput = row.querySelector('.line-price');
                const totalInput = row.querySelector('.line-total');
                const selectedOption = productSelect.options[productSelect.selectedIndex];
                const qty = parseFloat(qtyInput.value || '0');
                const price = parseFloat(priceInput.value || '0');
                const lineTotal = qty * price;

                if (selectedOption && selectedOption.value && !descriptionInput.value.trim()) {
                    descriptionInput.value = selectedOption.dataset.saleDescription || selectedOption.dataset.name || '';
                }

                if (selectedOption && selectedOption.value && !priceInput.value) {
                    priceInput.value = selectedOption.dataset.price || '0';
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

        validityButtons.forEach((button) => {
            button.addEventListener('click', function () {
                setValidity(parseInt(this.dataset.validityDays || '0', 10));
            });
        });

        compute();
    });
</script>

@include('partials.document-line-pricing', [
    'tableId' => 'quote-lines-table',
    'partnerSelectId' => 'customer_id',
    'pricingHintId' => 'quote-customer-pricing',
    'lineAmountClass' => 'line-price',
    'priceDataKey' => 'price',
    'partnerKind' => 'client',
    'defaultPricingText' => 'Aucune liste de prix specifique: le prix catalogue sera propose.',
])



