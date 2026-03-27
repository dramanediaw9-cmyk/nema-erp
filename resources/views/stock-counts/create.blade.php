@extends('layouts.app')

@section('title', 'Nouvel inventaire')
@section('page-title', 'Nouvel inventaire')

@section('content')
    <div class="page-head">
        <div>
            <h2 class="section-title">Nouvel inventaire</h2>
            <div class="muted">Compte les quantites physiques d un entrepot puis applique les ecarts de stock.</div>
        </div>
        <a href="{{ route('stock-counts.index') }}" class="button button-secondary">Retour</a>
    </div>

    <form method="GET" action="{{ route('stock-counts.create') }}" class="card" style="margin-bottom:20px;">
        <div class="form-grid">
            <div>
                <label for="warehouse_id_filter">Entrepot a compter</label>
                <select id="warehouse_id_filter" name="warehouse_id" onchange="this.form.submit()">
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected(optional($selectedWarehouse)->id === $warehouse->id)>{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </form>

    <form method="POST" action="{{ route('stock-counts.store') }}" class="card">
        @csrf
        <input type="hidden" name="warehouse_id" value="{{ $selectedWarehouse?->id }}">
        <div class="form-grid">
            <div>
                <label for="count_date">Date inventaire</label>
                <input id="count_date" name="count_date" type="date" value="{{ old('count_date', now()->toDateString()) }}" required>
                @error('count_date')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div class="full">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" placeholder="Equipe de comptage, observations, motif du comptage...">{{ old('notes') }}</textarea>
                @error('notes')<div class="field-error">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="table-wrap" style="margin-top:20px;">
            <table>
                <thead>
                <tr>
                    <th>Produit</th>
                    <th>Stock theorique</th>
                    <th>Quantite comptee</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($products as $index => $product)
                    <tr>
                        <td>
                            <strong>{{ $product['sku'] }}</strong> - {{ $product['name'] }}
                            <input type="hidden" name="items[{{ $index }}][product_id]" value="{{ $product['id'] }}">
                        </td>
                        <td>{{ number_format((float) $product['expected_qty'], 3, ',', ' ') }} {{ $product['unit'] }}</td>
                        <td><input type="number" min="0" step="0.001" name="items[{{ $index }}][counted_qty]" value="{{ old('items.'.$index.'.counted_qty') }}"></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @error('items')<div class="field-error" style="margin-top:10px;">{{ $message }}</div>@enderror

        <div class="actions">
            <a href="{{ route('stock-counts.index') }}" class="button button-secondary">Annuler</a>
            <button type="submit" class="button button-primary">Enregistrer l inventaire</button>
        </div>
    </form>
@endsection
