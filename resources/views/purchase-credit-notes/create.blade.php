@extends('layouts.app')

@section('title', 'Nouvel avoir fournisseur - Nema ERP')
@section('page-title', 'Nouvel avoir fournisseur')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">Facture {{ $bill->bill_number }}</h2>
            <div class="muted">Fournisseur {{ $bill->supplier?->name }} · Solde restant {{ number_format((float) $bill->balance_due, 0, ',', ' ') }} XOF</div>
        </div>
        <div><a href="{{ route('purchases.show', $bill) }}" class="button button-secondary">Retour facture</a></div>
    </div>

    <form method="POST" action="{{ route('purchase-credit-notes.store', $bill) }}" class="card">
        @csrf
        <div class="form-grid">
            <div>
                <label for="credit_note_date">Date de l avoir</label>
                <input type="date" id="credit_note_date" name="credit_note_date" value="{{ old('credit_note_date', now()->format('Y-m-d')) }}" required>
                @error('credit_note_date')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="destock_items">Retour fournisseur</label>
                <select id="destock_items" name="destock_items">
                    <option value="0" @selected(! old('destock_items'))>Non</option>
                    <option value="1" @selected(old('destock_items'))>Oui, sortir les articles stockables</option>
                </select>
            </div>
            <div class="full">
                <label for="notes">Motif / notes</label>
                <textarea id="notes" name="notes" placeholder="Motif de l avoir fournisseur">{{ old('notes') }}</textarea>
                @error('notes')<div class="field-error">{{ $message }}</div>@enderror
                @error('items')<div class="field-error">{{ $message }}</div>@enderror
                @error('purchase')<div class="field-error">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="table-wrap" style="margin-top:20px;">
            <table>
                <thead>
                <tr>
                    <th>Produit</th>
                    <th>Description</th>
                    <th>Quantite facturee</th>
                    <th>Deja avoie</th>
                    <th>Disponible</th>
                    <th>Cout net</th>
                    <th>Total unitaire</th>
                    <th>Quantite a avoirer</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($creditableLines as $index => $line)
                    <tr>
                        <td>{{ $line['product_name'] ?: 'Article libre' }}<input type="hidden" name="items[{{ $index }}][purchase_bill_item_id]" value="{{ $line['purchase_bill_item_id'] }}"></td>
                        <td>{{ $line['description'] }}</td>
                        <td>{{ number_format((float) $line['original_qty'], 3, ',', ' ') }}</td>
                        <td>{{ number_format((float) $line['credited_qty'], 3, ',', ' ') }}</td>
                        <td>{{ number_format((float) $line['remaining_qty'], 3, ',', ' ') }}</td>
                        <td>{{ number_format((float) $line['unit_cost'], 0, ',', ' ') }} XOF</td>
                        <td>{{ number_format((float) $line['unit_total'], 0, ',', ' ') }} XOF</td>
                        <td><input type="number" step="0.001" min="0" max="{{ $line['remaining_qty'] }}" name="items[{{ $index }}][qty]" value="{{ old('items.'.$index.'.qty') }}"></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="muted">Aucune ligne n est encore disponible pour un avoir fournisseur.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="actions"><button type="submit" class="button button-primary">Enregistrer l avoir fournisseur</button></div>
    </form>
@endsection
