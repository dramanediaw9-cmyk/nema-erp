<div class="split">
    <section class="card">
        <h2 class="section-title">Entete de la livraison</h2>
        <div class="muted" style="margin-bottom:16px;">Le bon de livraison confirme la sortie physique des articles. Tu peux maintenant livrer partiellement une commande sur plusieurs passages.</div>

        <div class="form-grid">
            <div class="full">
                <label for="order_id">Commande client</label>
                <select id="order_id" name="order_id" required>
                    <option value="">Selectionner une commande confirmée</option>
                    @foreach ($orders as $order)
                        <option value="{{ $order->id }}" @selected((string) old('order_id', $selectedOrder?->id) === (string) $order->id)>
                            {{ $order->order_number }} - {{ $order->customer?->name }} - {{ number_format((float) $order->total, 0, ',', ' ') }} XOF
                        </option>
                    @endforeach
                </select>
                <div class="help">Pour un pre-remplissage plus confortable, ouvre ce formulaire directement depuis la fiche commande.</div>
                @error('order_id')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="warehouse_id">Entrepot de sortie</label>
                <select id="warehouse_id" name="warehouse_id">
                    <option value="">Entrepot par defaut de l'agence</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected((string) old('warehouse_id') === (string) $warehouse->id || (! old('warehouse_id') && $warehouse->is_default))>{{ $warehouse->code }} - {{ $warehouse->name }}</option>
                    @endforeach
                </select>
                <div class="help">Le stock sera sorti de cet entrepot au moment de la validation du bon.</div>
                @error('warehouse_id')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="delivery_date">Date de livraison</label>
                <input id="delivery_date" type="date" name="delivery_date" value="{{ old('delivery_date', now()->format('Y-m-d')) }}" required>
                @error('delivery_date')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div>
                <label>Agence active</label>
                <input type="text" value="{{ $branch?->name }}" disabled>
                <div class="help">Le bon de livraison sera rattache a cette agence.</div>
            </div>
            <div class="full">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" placeholder="Observations de livraison, contact terrain, details logistiques...">{{ old('notes') }}</textarea>
                @error('notes')<div class="field-error">{{ $message }}</div>@enderror
            </div>
        </div>
    </section>

    <aside class="card">
        <h2 class="section-title">Impact attendu</h2>
        <div class="summary-stack">
            <div class="tip-card">
                <strong>Stock</strong>
                <div class="muted">Chaque validation retire seulement les quantites effectivement livrees.</div>
            </div>
            <div class="tip-card">
                <strong>Comptabilite</strong>
                <div class="muted">Aucune ecriture comptable n est generee a ce stade. La facture s en chargera.</div>
            </div>
            <div class="tip-card">
                <strong>Facturation</strong>
                <div class="muted">Chaque bon emis pourra etre converti en facture sans double sortie de stock.</div>
            </div>
        </div>
    </aside>
</div>

@if ($selectedOrder)
    <section class="card" style="margin-top:20px;">
        <div style="display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap; align-items:flex-start;">
            <div>
                <h2 class="section-title">Commande selectionnee</h2>
                <div class="muted">{{ $selectedOrder->order_number }} · {{ $selectedOrder->customer?->name }}</div>
            </div>
            <a href="{{ route('orders.show', $selectedOrder) }}" class="button button-secondary">Voir la commande</a>
        </div>

        <div class="kpi-row" style="margin-top:16px;">
            <div class="kpi"><div class="label">Montant</div><div class="value">{{ number_format((float) $selectedOrder->total, 0, ',', ' ') }}</div></div>
            <div class="kpi"><div class="label">Livraison souhaitee</div><div class="value" style="font-size:20px;">{{ $selectedOrder->requested_delivery_date?->format('d/m/Y') ?? 'Non renseignee' }}</div></div>
        </div>

        <div class="table-wrap" style="margin-top:18px;">
            <table>
                <thead>
                <tr>
                    <th>Produit</th>
                    <th>Description</th>
                    <th>Commande</th>
                    <th>Deja livre</th>
                    <th>Reste</th>
                    <th>A livrer maintenant</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($selectedOrder->items as $item)
                    @php($remainingQty = $item->remainingQty())
                    <tr>
                        <td>@include('partials.product-inline', ['product' => $item->product, 'meta' => $item->product?->barcode ?: $item->product?->sku, 'size' => 42])</td>
                        <td>{{ $item->description }}</td>
                        <td>{{ number_format((float) $item->qty, 3, ',', ' ') }}</td>
                        <td>{{ number_format((float) $item->delivered_qty, 3, ',', ' ') }}</td>
                        <td>{{ number_format($remainingQty, 3, ',', ' ') }}</td>
                        <td>
                            <input type="hidden" name="items[{{ $loop->index }}][sales_order_item_id]" value="{{ $item->id }}">
                            <input type="number" step="0.001" min="0" max="{{ $remainingQty }}" name="items[{{ $loop->index }}][qty]" value="{{ old('items.'.$loop->index.'.qty', $remainingQty > 0 ? $remainingQty : '') }}">
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @error('items')<div class="field-error" style="margin-top:12px;">{{ $message }}</div>@enderror
    </section>
@endif

<div class="actions">
    <a href="{{ route('delivery-notes.index') }}" class="button button-secondary">Annuler</a>
    <button type="submit" class="button button-primary">Generer le bon de livraison</button>
</div>

