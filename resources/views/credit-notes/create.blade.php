@extends('layouts.app')

@section('title', 'Nouvel avoir client - Nema ERP')
@section('page-title', 'Nouvel avoir client')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">Facture {{ $invoice->invoice_number }}</h2>
            <div class="muted">Client {{ $invoice->customer?->name }} · Solde restant {{ number_format((float) $invoice->balance_due, 0, ',', ' ') }} XOF</div>
        </div>
        <div><a href="{{ route('sales.show', $invoice) }}" class="button button-secondary">Retour facture</a></div>
    </div>

    <form method="POST" action="{{ route('credit-notes.store', $invoice) }}" class="card">
        @csrf
        <div class="form-grid">
            <div>
                <label for="credit_note_date">Date de l avoir</label>
                <input type="date" id="credit_note_date" name="credit_note_date" value="{{ old('credit_note_date', now()->format('Y-m-d')) }}" required>
                @error('credit_note_date')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="restock_items">Retour en stock</label>
                <select id="restock_items" name="restock_items">
                    <option value="0" @selected(! old('restock_items'))>Non</option>
                    <option value="1" @selected(old('restock_items'))>Oui, reintegrer les articles stockables</option>
                </select>
            </div>
            <div class="full">
                <label for="notes">Motif / notes</label>
                <textarea id="notes" name="notes" placeholder="Motif commercial de l avoir">{{ old('notes') }}</textarea>
                @error('notes')<div class="field-error">{{ $message }}</div>@enderror
                @error('items')<div class="field-error">{{ $message }}</div>@enderror
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
                    <th>PU net</th>
                    <th>Quantite a avoirer</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($creditableLines as $index => $line)
                    <tr>
                        <td>{{ $line['product_name'] ?: 'Article libre' }}<input type="hidden" name="items[{{ $index }}][sales_invoice_item_id]" value="{{ $line['invoice_item_id'] }}"></td>
                        <td>{{ $line['description'] }}</td>
                        <td>{{ number_format((float) $line['original_qty'], 3, ',', ' ') }}</td>
                        <td>{{ number_format((float) $line['credited_qty'], 3, ',', ' ') }}</td>
                        <td>{{ number_format((float) $line['remaining_qty'], 3, ',', ' ') }}</td>
                        <td>{{ number_format((float) $line['unit_price'], 0, ',', ' ') }} XOF</td>
                        <td><input type="number" step="0.001" min="0" max="{{ $line['remaining_qty'] }}" name="items[{{ $index }}][qty]" value="{{ old('items.'.$index.'.qty') }}"></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div class="actions"><button type="submit" class="button button-primary">Enregistrer l avoir</button></div>
    </form>
@endsection
