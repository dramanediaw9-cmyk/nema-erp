@extends('layouts.app')

@section('title', 'Nouvelle demande d achat')
@section('page-title', 'Nouvelle demande d achat')

@section('content')
    <div class="page-head">
        <div>
            <h2 class="section-title">Nouvelle demande d achat</h2>
            <div class="muted">Formalise un besoin interne avant validation et conversion en commande fournisseur.</div>
        </div>
        <a href="{{ route('purchase-requests.index') }}" class="button button-secondary">Retour</a>
    </div>

    <form method="POST" action="{{ route('purchase-requests.store') }}" class="card">
        @csrf
        <div class="form-grid">
            <div>
                <label for="warehouse_id">Entrepot cible</label>
                <select id="warehouse_id" name="warehouse_id" required>
                    <option value="">Choisir</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected(old('warehouse_id') == $warehouse->id)>{{ $warehouse->name }}</option>
                    @endforeach
                </select>
                @error('warehouse_id')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="request_date">Date de demande</label>
                <input id="request_date" name="request_date" type="date" value="{{ old('request_date', now()->toDateString()) }}" required>
                @error('request_date')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="needed_by_date">Besoin pour le</label>
                <input id="needed_by_date" name="needed_by_date" type="date" value="{{ old('needed_by_date') }}">
                @error('needed_by_date')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="priority">Priorite</label>
                <select id="priority" name="priority" required>
                    @foreach (['low' => 'Basse', 'normal' => 'Normale', 'high' => 'Haute', 'urgent' => 'Urgente'] as $key => $label)
                        <option value="{{ $key }}" @selected(old('priority', 'normal') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('priority')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div class="full">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" placeholder="Motif du besoin, contexte, contraintes logistiques...">{{ old('notes') }}</textarea>
                @error('notes')<div class="field-error">{{ $message }}</div>@enderror
            </div>
        </div>

        <div style="margin-top:24px;" class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Produit</th>
                    <th>Description</th>
                    <th>Quantite</th>
                    <th>Coût estime</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($defaultRows as $index => $row)
                    <tr>
                        <td>
                            <select name="items[{{ $index }}][product_id]" data-product-picker data-product-mode="purchasable">
                                <option value="">Choisir</option>
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}" @selected(($row['product_id'] ?? '') == $product->id)>{{ $product->sku }} - {{ $product->name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td><input name="items[{{ $index }}][description]" value="{{ $row['description'] ?? '' }}"></td>
                        <td><input name="items[{{ $index }}][qty]" type="number" min="0" step="0.001" value="{{ $row['qty'] ?? '' }}"></td>
                        <td><input name="items[{{ $index }}][estimated_unit_cost]" type="number" min="0" step="0.01" value="{{ $row['estimated_unit_cost'] ?? '' }}"></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @error('items')<div class="field-error" style="margin-top:10px;">{{ $message }}</div>@enderror

        <div class="actions">
            <a href="{{ route('purchase-requests.index') }}" class="button button-secondary">Annuler</a>
            <button type="submit" class="button button-primary">Enregistrer</button>
        </div>
    </form>
@endsection
