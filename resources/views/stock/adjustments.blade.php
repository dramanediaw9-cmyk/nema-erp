@extends('layouts.app')

@section('title', ($modeTitle ?? 'Ajustement stock').' - Nema ERP')
@section('page-title', $modeTitle ?? 'Ajuster le stock')

@section('content')
    <form method="POST" action="{{ route('stock.adjustments.store') }}">
        @csrf
        <div class="card">
            <div class="form-grid">
                <div class="full">
                    <div class="help">Agence active : <strong>{{ $branch?->name }}</strong></div>
                    @if (! empty($modeHelp))
                        <div class="help" style="margin-top:6px;">{{ $modeHelp }}</div>
                    @endif
                </div>
                <div>
                    <label for="warehouse_id">Entrepot</label>
                    <select id="warehouse_id" name="warehouse_id">
                        <option value="">Entrepot par defaut de l'agence</option>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected((string) old('warehouse_id') === (string) $warehouse->id || (! old('warehouse_id') && $warehouse->is_default))>{{ $warehouse->code }} - {{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                    @error('warehouse_id')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="product_id">Produit</label>
                    <select id="product_id" name="product_id" required>
                        <option value="">Selectionner un produit</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}" @selected((string) old('product_id') === (string) $product->id)>{{ $product->sku }} - {{ $product->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="movement_date">Date de mouvement</label>
                    <input id="movement_date" type="date" name="movement_date" value="{{ old('movement_date', now()->toDateString()) }}" required>
                </div>
                <div>
                    <label for="direction">Direction</label>
                    @php($selectedDirection = old('direction', $defaultDirection ?? null))
                    <select id="direction" name="direction" required>
                        <option value="in" @selected($selectedDirection === 'in')>Entree</option>
                        <option value="out" @selected($selectedDirection === 'out')>Sortie</option>
                    </select>
                </div>
                <div>
                    <label for="quantity">Quantite</label>
                    <input id="quantity" type="number" step="0.001" min="0.001" name="quantity" value="{{ old('quantity') }}" required>
                </div>
                <div>
                    <label for="unit_cost">Cout unitaire</label>
                    <input id="unit_cost" type="number" step="0.01" min="0" name="unit_cost" value="{{ old('unit_cost') }}">
                </div>
                <div class="full">
                    <label for="reason">Motif</label>
                    <input id="reason" type="text" name="reason" value="{{ old('reason', $defaultReason ?? '') }}" required>
                </div>
                <div class="full">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes">{{ old('notes') }}</textarea>
                </div>
            </div>
            <div class="actions">
                <a href="{{ route('stock.index') }}" class="button button-secondary">Annuler</a>
                <button type="submit" class="button button-primary">{{ $submitLabel ?? "Enregistrer l'ajustement" }}</button>
            </div>
        </div>
    </form>
@endsection
