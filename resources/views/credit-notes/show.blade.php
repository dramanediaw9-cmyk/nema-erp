@extends('layouts.app')

@section('title', 'Avoir client '.$creditNote->credit_note_number)
@section('page-title', 'Avoir '.$creditNote->credit_note_number)

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">{{ $creditNote->customer?->name }}</h2>
            <div class="muted">Avoir du {{ $creditNote->credit_note_date?->format('d/m/Y') }} · Facture {{ $creditNote->invoice?->invoice_number }}</div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            @allowed('payments.validate')
                @if ((float) $creditNote->invoice?->balance_due < 0)
                    <a href="{{ route('payments.create', ['type' => 'customer_refund', 'invoice' => $creditNote->invoice?->id, 'amount' => abs((float) $creditNote->invoice?->balance_due)]) }}" class="button button-primary">Rembourser le client</a>
                @endif
            @endallowed
            <a href="{{ route('credit-notes.print', $creditNote) }}" target="_blank" class="button button-secondary">PDF</a>
            <a href="{{ route('sales.show', $creditNote->invoice) }}" class="button button-secondary">Voir la facture</a>
        </div>
    </div>

    <div class="grid stats-grid" style="margin-bottom:20px;">
        <div class="card"><div class="muted">Montant avoir</div><div class="stat-value">{{ number_format((float) $creditNote->total, 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Retour stock</div><div class="stat-value" style="font-size:24px;">{{ $creditNote->restock_items ? 'Oui' : 'Non' }}</div></div>
        <div class="card"><div class="muted">Facture source</div><div class="stat-value" style="font-size:24px;">{{ $creditNote->invoice?->invoice_number }}</div></div>
        <div class="card"><div class="muted">Solde facture apres avoir</div><div class="stat-value">{{ number_format((float) $creditNote->invoice?->balance_due, 0, ',', ' ') }}</div></div>
    </div>

    <div class="split" style="margin-bottom:20px;">
        <section class="card">
            <h2 style="margin-top:0;">Lignes de l avoir</h2>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Produit</th><th>Description</th><th>Quantite</th><th>PU</th><th>Total</th></tr></thead>
                    <tbody>
                    @foreach ($creditNote->items as $item)
                        <tr>
                            <td>@include('partials.product-inline', ['product' => $item->product, 'meta' => $item->product?->barcode ?: $item->product?->sku, 'size' => 42])</td>
                            <td>{{ $item->description }}</td>
                            <td>{{ number_format((float) $item->qty, 3, ',', ' ') }}</td>
                            <td>{{ number_format((float) $item->unit_price, 0, ',', ' ') }} XOF</td>
                            <td>{{ number_format((float) $item->line_total, 0, ',', ' ') }} XOF</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card">
            <h2 style="margin-top:0;">Impacts</h2>
            <div class="grid">
                <div><strong>Statut</strong><div class="muted">Valide</div></div>
                <div><strong>Cree par</strong><div class="muted">{{ $creditNote->creator?->name ?? 'Systeme' }}</div></div>
                <div><strong>Stock reintegre</strong><div class="muted">{{ $stockMovements->count() }} mouvement(s)</div></div>
                <div><strong>Ecritures comptables</strong><div class="muted">{{ $journalEntries->count() }} ecriture(s)</div></div>
                @if ($creditNote->notes)
                    <div><strong>Notes</strong><div class="muted">{{ $creditNote->notes }}</div></div>
                @endif
            </div>
        </section>
    </div>

    <div class="split">
        <section class="card">
            <h2 style="margin-top:0;">Mouvements de stock lies</h2>
            @forelse ($stockMovements as $movement)
                <div style="padding-bottom:14px; border-bottom:1px solid #efe4d3; margin-bottom:14px;">
                    @include('partials.product-inline', ['product' => $movement->product, 'meta' => $movement->warehouse?->name ?? 'Entrepot', 'size' => 40])
                    <div class="muted" style="margin-top:6px;">{{ $movement->movement_date?->format('d/m/Y H:i') }} · {{ $movement->warehouse?->name ?? 'Entrepot' }}</div>
                    <div style="margin-top:6px;">Entree : {{ number_format((float) $movement->quantity_in, 3, ',', ' ') }}</div>
                </div>
            @empty
                <p class="muted">Aucun retour stock enregistre sur cet avoir.</p>
            @endforelse
        </section>

        <section class="card">
            <h2 style="margin-top:0;">Ecritures comptables liees</h2>
            @forelse ($journalEntries as $entry)
                <div style="padding-bottom:14px; border-bottom:1px solid #efe4d3; margin-bottom:14px;">
                    <div style="font-weight:600;">{{ $entry->journal_number }}</div>
                    <div class="muted" style="margin-top:6px;">{{ $entry->entry_date?->format('d/m/Y') }} · {{ $entry->description }}</div>
                    <div style="margin-top:6px;">Debit {{ number_format((float) $entry->total_debit, 0, ',', ' ') }} XOF · Credit {{ number_format((float) $entry->total_credit, 0, ',', ' ') }} XOF</div>
                </div>
            @empty
                <p class="muted">Aucune ecriture comptable disponible.</p>
            @endforelse
        </section>
    </div>
@endsection


