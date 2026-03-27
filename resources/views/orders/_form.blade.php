<div class="split">
    <section class="card">
        <h2 class="section-title">Entete de la commande</h2>
        <div class="muted" style="margin-bottom:16px;">La commande formalise l engagement client sans impact immediat sur le stock ni la comptabilite. La facture viendra ensuite.</div>

        <div class="form-grid">
            <div>
                <label for="customer_id">Client</label>
                <select id="customer_id" name="customer_id" required>
                    <option value="">Selectionner un client</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}" @selected((string) old('customer_id') === (string) $customer->id)>{{ $customer->code }} - {{ $customer->name }}</option>
                    @endforeach
                </select>
                @error('customer_id')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="order_date">Date de commande</label>
                <input id="order_date" type="date" name="order_date" value="{{ old('order_date', now()->format('Y-m-d')) }}" required>
                @error('order_date')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="requested_delivery_date">Livraison souhaitee</label>
                <input id="requested_delivery_date" type="date" name="requested_delivery_date" value="{{ old('requested_delivery_date') }}">
                <div class="chip-row">
                    <button type="button" class="chip" data-delivery-days="3">+3 jours</button>
                    <button type="button" class="chip" data-delivery-days="7">+7 jours</button>
                    <button type="button" class="chip" data-delivery-days="14">+14 jours</button>
                </div>
                <div class="help">Cette date aide a prioriser la preparation et la facturation.</div>
                @error('requested_delivery_date')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div>
                <label>Agence active</label>
                <input type="text" value="{{ $branch?->name }}" disabled>
                <div class="help">La commande sera rattachee a cette agence commerciale.</div>
            </div>
            <div class="full">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" placeholder="Objet de la commande, details de livraison, contact terrain...">{{ old('notes') }}</textarea>
                @error('notes')<div class="field-error">{{ $message }}</div>@enderror
            </div>
        </div>
    </section>

    <aside class="card">
        <h2 class="section-title">Resume de la commande</h2>
        <div class="summary-stack">
            <div class="summary-box">
                <div class="muted">Montant engage</div>
                <div class="value" id="order-grand-total">0 XOF</div>
            </div>
            <div class="kpi-row">
                <div class="kpi">
                    <div class="label">Lignes actives</div>
                    <div class="value" id="order-lines-count">0</div>
                </div>
                <div class="kpi">
                    <div class="label">Quantite totale</div>
                    <div class="value" id="order-total-qty">0</div>
                </div>
            </div>
            <div class="tip-grid">
                <div class="tip-card">
                    <strong>Statut initial</strong>
                    <div class="muted">La commande est creee en brouillon puis confirmee avant conversion en facture.</div>
                </div>
                <div class="tip-card">
                    <strong>Bon reflexe</strong>
                    <div class="muted">Renseigne la livraison souhaitee pour piloter le carnet de commandes.</div>
                </div>
            </div>
        </div>
    </aside>
</div>

<div class="card" style="margin-top:18px;">
    <div style="display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap; margin-bottom:14px; align-items:flex-start;">
        <div>
            <h2 class="section-title">Lignes de commande</h2>
            <div class="muted">Choisis les produits commandes. Les lignes vides ne seront pas retenues.</div>
        </div>
        <div class="tip-card" style="min-width:260px;">
            <strong>Conseil operationnel</strong>
            <div class="muted">La commande n impacte pas encore le stock. Le controle stock sera refait a la conversion en facture.</div>
        </div>
    </div>

    <div class="table-wrap">
        <table id="order-lines-table">
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
                        <select name="items[{{ $index }}][product_id]" class="line-product">
                            <option value="">Choisir</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}" data-name="{{ $product->name }}" data-price="{{ $product->sale_price }}" @selected((string) ($row['product_id'] ?? '') === (string) $product->id)>
                                    {{ $product->sku }} - {{ $product->name }}
                                </option>
                            @endforeach
                        </select>
                    </td>
                    <td><input type="text" name="items[{{ $index }}][description]" value="{{ $row['description'] ?? '' }}" class="line-description" placeholder="Description visible sur la commande"></td>
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
        <div class="field-error" style="margin-top:12px;">Certaines lignes de commande doivent etre corrigees avant enregistrement.</div>
    @endif

    <div class="table-foot-note">
        <div class="help">Les lignes vides sont ignorees automatiquement.</div>
        <div class="help">Le total se met a jour en direct pour confirmer rapidement la commande.</div>
    </div>

    <div class="actions">
        <a href="{{ route('orders.index') }}" class="button button-secondary">Annuler</a>
        <button type="submit" class="button button-primary">Enregistrer la commande</button>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const rows = Array.from(document.querySelectorAll('#order-lines-table tbody tr'));
        const orderDateInput = document.getElementById('order_date');
        const deliveryDateInput = document.getElementById('requested_delivery_date');
        const linesCount = document.getElementById('order-lines-count');
        const totalQty = document.getElementById('order-total-qty');
        const grandTotal = document.getElementById('order-grand-total');
        const deliveryButtons = document.querySelectorAll('[data-delivery-days]');
        const moneyFormatter = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 });
        const qtyFormatter = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 3 });

        const formatMoney = (value) => moneyFormatter.format(value || 0) + ' XOF';

        const setDelivery = (days) => {
            if (!orderDateInput.value) {
                return;
            }

            const baseDate = new Date(orderDateInput.value + 'T00:00:00');
            baseDate.setDate(baseDate.getDate() + days);
            deliveryDateInput.value = baseDate.toISOString().slice(0, 10);
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
                    descriptionInput.value = selectedOption.dataset.name || '';
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

        deliveryButtons.forEach((button) => {
            button.addEventListener('click', function () {
                setDelivery(parseInt(this.dataset.deliveryDays || '0', 10));
            });
        });

        compute();
    });
</script>
