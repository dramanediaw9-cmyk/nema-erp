<div class="split">
    <section class="card">
        <h2 class="section-title">Entete de la facture</h2>
        <div class="muted" style="margin-bottom:16px;">Renseigne le client, l'entrepot de sortie, la date et l'echeance de recouvrement. Les lignes vides sont ignorees a l'enregistrement.</div>

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
                <label for="warehouse_id">Entrepot de sortie</label>
                <select id="warehouse_id" name="warehouse_id">
                    <option value="">Entrepot par defaut de l'agence</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected((string) old('warehouse_id') === (string) $warehouse->id || (! old('warehouse_id') && $warehouse->is_default))>{{ $warehouse->code }} - {{ $warehouse->name }}</option>
                    @endforeach
                </select>
                <div class="help">Le stock sera reserve et sorti depuis cet entrepot au moment de l'approbation finale.</div>
                @error('warehouse_id')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="invoice_date">Date de facture</label>
                <input id="invoice_date" type="date" name="invoice_date" value="{{ old('invoice_date', now()->format('Y-m-d')) }}" required>
                @error('invoice_date')<div class="field-error">{{ $message }}</div>@enderror
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
                <div class="help">L'echeance alimente le suivi des relances sur la liste des ventes.</div>
                @error('due_date')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div>
                <label>Agence active</label>
                <input type="text" value="{{ $branch?->name }}" disabled>
                <div class="help">La facture sera rattachee a cette agence.</div>
            </div>
            <div class="full">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" placeholder="Commentaire interne, reference client, remarque de livraison...">{{ old('notes') }}</textarea>
                @error('notes')<div class="field-error">{{ $message }}</div>@enderror
            </div>
        </div>
    </section>

    <aside class="card">
        <h2 class="section-title">Resume de la facture</h2>
        <div class="summary-stack">
            <div class="summary-box">
                <div class="muted">Montant estime</div>
                <div class="value" id="sales-grand-total">0 XOF</div>
            </div>
            <div class="kpi-row">
                <div class="kpi">
                    <div class="label">Lignes actives</div>
                    <div class="value" id="sales-lines-count">0</div>
                </div>
                <div class="kpi">
                    <div class="label">Quantite totale</div>
                    <div class="value" id="sales-total-qty">0</div>
                </div>
            </div>
            <div class="tip-grid">
                <div class="tip-card">
                    <strong>Impact a l'approbation finale</strong>
                    <div class="muted">Le stock de l'entrepot choisi est decremente et la creance client est ouverte.</div>
                </div>
                <div class="tip-card">
                    <strong>Bon reflexe</strong>
                    <div class="muted">Renseigne une echeance si tu veux suivre les retards de paiement dans la liste.</div>
                </div>
            </div>
        </div>
    </aside>
</div>

<div class="card" style="margin-top:18px;">
    <div style="display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap; margin-bottom:14px; align-items:flex-start;">
        <div>
            <h2 class="section-title">Lignes de facture</h2>
            <div class="muted">Choisis les produits, ajuste les quantites et les prix si besoin. Les lignes vides ne seront pas prises en compte.</div>
        </div>
        <div class="tip-card" style="min-width:260px;">
            <strong>Conseil operateur</strong>
            <div class="muted">Le prix de vente et la description se remplissent automatiquement des qu'un produit est choisi.</div>
        </div>
    </div>

    <div class="table-wrap">
        <table id="sales-lines-table">
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
                    <td><input type="text" name="items[{{ $index }}][description]" value="{{ $row['description'] ?? '' }}" class="line-description" placeholder="Description visible sur la facture"></td>
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
        <div class="field-error" style="margin-top:12px;">Certaines lignes de facture doivent etre corrigees avant enregistrement.</div>
    @endif

    <div class="table-foot-note">
        <div class="help">Astuce : laisse les lignes inutiles vides, elles seront ignorees automatiquement.</div>
        <div class="help">Le total se met a jour en direct pour faciliter le controle avant soumission.</div>
    </div>

    <div class="actions">
        <a href="{{ route('sales.index') }}" class="button button-secondary">Annuler</a>
        <button type="submit" class="button button-primary">Enregistrer la facture</button>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const rows = Array.from(document.querySelectorAll('#sales-lines-table tbody tr'));
        const invoiceDateInput = document.getElementById('invoice_date');
        const dueDateInput = document.getElementById('due_date');
        const linesCount = document.getElementById('sales-lines-count');
        const totalQty = document.getElementById('sales-total-qty');
        const grandTotal = document.getElementById('sales-grand-total');
        const dueButtons = document.querySelectorAll('[data-due-days]');
        const moneyFormatter = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 });
        const qtyFormatter = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 3 });

        const formatMoney = (value) => moneyFormatter.format(value || 0) + ' XOF';

        const setDueDate = (days) => {
            if (!invoiceDateInput.value) {
                return;
            }

            const baseDate = new Date(invoiceDateInput.value + 'T00:00:00');
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

        dueButtons.forEach((button) => {
            button.addEventListener('click', function () {
                setDueDate(parseInt(this.dataset.dueDays || '0', 10));
            });
        });

        compute();
    });
</script>
