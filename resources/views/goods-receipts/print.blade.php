@extends('layouts.print')

@section('title', 'Reception fournisseur '.$receipt->receipt_number.' - Nema ERP')

@section('content')
    <section class="doc-header">
        <div>
            <div class="doc-chip">Reception fournisseur</div>
            <h1>{{ $receipt->receipt_number }}</h1>
            <div><strong>{{ $company?->legal_name ?: $company?->name }}</strong></div>
            <div class="meta">{{ $company?->address ?: 'Adresse non renseignee' }}</div>
            <div class="meta">Tel : {{ $company?->phone ?: 'N/A' }} @if($company?->email)· {{ $company?->email }} @endif</div>
            <div class="meta">NIF : {{ $company?->nif ?: 'N/A' }} · RCCM : {{ $company?->rccm ?: 'N/A' }}</div>
        </div>
        <div class="right">
            <div><strong>Date reception :</strong> {{ $receipt->receipt_date?->format('d/m/Y') }}</div>
            <div class="meta">Agence : {{ $receipt->branch?->name ?: 'N/A' }}</div>
            <div class="meta">Depot : {{ $receipt->warehouse?->name ?: 'N/A' }}</div>
            <div class="meta">Statut : {{ ucfirst((string) $receipt->status) }}</div>
            <div class="meta">Imprime le : {{ now()->format('d/m/Y H:i') }}</div>
        </div>
    </section>

    <section class="grid grid-2">
        <div class="panel">
            <h2>Fournisseur</h2>
            <div><strong>{{ $receipt->supplier?->name ?: 'Non renseigne' }}</strong></div>
            <div class="muted">Code : {{ $receipt->supplier?->code ?: 'N/A' }}</div>
            <div class="muted">Telephone : {{ $receipt->supplier?->phone ?: 'Non renseigne' }}</div>
            <div class="muted">Email : {{ $receipt->supplier?->email ?: 'Non renseigne' }}</div>
            <div class="muted">Adresse : {{ $receipt->supplier?->address ?: 'Non renseignee' }}</div>
        </div>
        <div class="panel">
            <h2>Suivi achat</h2>
            <div><strong>Commande :</strong> {{ $receipt->purchaseOrder?->order_number ?: 'N/A' }}</div>
            <div><strong>Facture fournisseur :</strong> {{ $receipt->purchaseBill?->bill_number ?: 'Pas encore creee' }}</div>
            <div><strong>Enregistre par :</strong> {{ $receipt->creator?->name ?: 'N/A' }}</div>
            <div><strong>Reception effective :</strong> {{ $receipt->received_at?->format('d/m/Y H:i') ?: 'N/A' }}</div>
        </div>
    </section>

    <table>
        <thead>
            <tr>
                <th>Produit</th>
                <th>Description</th>
                <th class="right">Quantite recue</th>
                <th>Lot / serie</th>
                <th class="right">Cout unitaire</th>
                <th class="right">Montant</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($receipt->items as $item)
                <tr>
                    <td>@include('partials.product-inline', ['product' => $item->product, 'meta' => $item->product?->barcode ?: $item->product?->sku, 'size' => 34, 'link' => null])</td>
                    <td>{{ $item->description ?: $item->product?->display_name }}</td>
                    <td class="right">{{ number_format((float) $item->qty, 3, ',', ' ') }}</td>
                    <td>
                        @if ($item->lot_number)
                            Lot {{ $item->lot_number }}
                        @elseif (! empty($item->serial_numbers))
                            {{ implode(', ', $item->serial_numbers) }}
                        @elseif ($item->productLots->isNotEmpty())
                            {{ $item->productLots->map(fn ($lot) => $lot->displayCode())->implode(', ') }}
                        @else
                            <span class="muted">N/A</span>
                        @endif
                        @if ($item->expires_at)
                            <div class="muted">Peremption : {{ $item->expires_at->format('d/m/Y') }}</div>
                        @endif
                    </td>
                    <td class="right">{{ number_format((float) $item->unit_cost, 0, ',', ' ') }} XOF</td>
                    <td class="right">{{ number_format((float) $item->line_total, 0, ',', ' ') }} XOF</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>Total recu</td>
            <td class="right">{{ number_format((float) $receipt->total, 0, ',', ' ') }} XOF</td>
        </tr>
        <tr class="grand-total">
            <td>Lignes</td>
            <td class="right">{{ number_format($receipt->items->count(), 0, ',', ' ') }}</td>
        </tr>
    </table>

    @if ($stockMovements->isNotEmpty())
        <div class="panel" style="margin-top:24px;">
            <h2>Mouvements de stock crees</h2>
            <table>
                <thead>
                    <tr>
                        <th>Produit</th>
                        <th>Depot</th>
                        <th>Date</th>
                        <th>Suivi</th>
                        <th class="right">Entree</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($stockMovements as $movement)
                        <tr>
                            <td>{{ $movement->product?->display_name }}</td>
                            <td>{{ $movement->warehouse?->name }}</td>
                            <td>{{ $movement->movement_date?->format('d/m/Y H:i') }}</td>
                            <td>{{ $movement->productLot?->displayCode() ?: 'N/A' }}</td>
                            <td class="right">{{ number_format((float) $movement->quantity_in, 3, ',', ' ') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($receipt->notes)
        <div class="footer">
            <strong>Notes :</strong> {{ $receipt->notes }}
        </div>
    @endif

    <div class="signatures">
        <div class="signature-box">Receptionnaire / magasinier</div>
        <div class="signature-box">Fournisseur / livreur</div>
    </div>
@endsection
