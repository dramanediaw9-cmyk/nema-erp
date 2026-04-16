@extends('layouts.app')

@section('title', 'Bon de livraison '.$deliveryNote->delivery_number)
@section('page-title', 'Detail bon de livraison')

@section('content')
    @php
        $statusLabel = $deliveryNote->status === 'invoiced' ? 'Facture' : 'Emis';
        $statusClass = $deliveryNote->status === 'invoiced' ? 'badge-success' : 'badge-warning';
    @endphp

    <div class="page-head">
        <div>
            <h2 style="margin:0;">{{ $deliveryNote->delivery_number }}</h2>
            <div class="muted">Client {{ $deliveryNote->customer?->name }} · Agence {{ $deliveryNote->branch?->name }} · {{ $deliveryNote->warehouse?->name ?? 'Entrepot par defaut' }}</div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="{{ route('delivery-notes.print', $deliveryNote) }}" class="button button-secondary" target="_blank">PDF</a>
            <a href="{{ route('delivery-notes.index') }}" class="button button-secondary">Retour liste</a>
        </div>
    </div>

    <div class="split">
        <section class="card">
            <div style="display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap; align-items:flex-start;">
                <div>
                    <div class="muted">Statut</div>
                    <span class="badge {{ $statusClass }}" style="margin-top:8px;">{{ $statusLabel }}</span>
                </div>
                <div class="summary-box" style="min-width:220px;">
                    <div class="muted">Montant livre</div>
                    <div class="value">{{ number_format((float) $deliveryNote->total, 0, ',', ' ') }} XOF</div>
                </div>
            </div>

            <div class="grid" style="grid-template-columns: repeat(2, minmax(0, 1fr)); margin-top:18px;">
                <div>
                    <div class="muted">Date de livraison</div>
                    <strong>{{ $deliveryNote->delivery_date?->format('d/m/Y') }}</strong>
                </div>
                <div>
                    <div class="muted">Commande source</div>
                    @if ($deliveryNote->salesOrder)
                        <a href="{{ route('orders.show', $deliveryNote->salesOrder) }}"><strong>{{ $deliveryNote->salesOrder->order_number }}</strong></a>
                    @else
                        <strong>Aucune</strong>
                    @endif
                </div>
                <div>
                    <div class="muted">Entrepot</div>
                    <strong>{{ $deliveryNote->warehouse?->name ?? 'Entrepot par defaut' }}</strong>
                </div>
                <div>
                    <div class="muted">Facture issue du bon</div>
                    @if ($deliveryNote->convertedInvoice)
                        <a href="{{ route('sales.show', $deliveryNote->convertedInvoice) }}"><strong>{{ $deliveryNote->convertedInvoice->invoice_number }}</strong></a>
                    @else
                        <strong>Aucune</strong>
                    @endif
                </div>
                <div>
                    <div class="muted">Cree par</div>
                    <strong>{{ $deliveryNote->creator?->name ?? 'Systeme' }}</strong>
                </div>
                <div>
                    <div class="muted">Nombre de lignes</div>
                    <strong>{{ $deliveryNote->items->count() }}</strong>
                </div>
            </div>

            @if ($deliveryNote->notes)
                <div class="card" style="margin-top:18px; padding:16px;">
                    <div class="muted">Notes</div>
                    <div style="margin-top:8px;">{{ $deliveryNote->notes }}</div>
                </div>
            @endif
        </section>

        <aside class="card">
            <h2 class="section-title">Actions</h2>
            <div class="summary-stack">
                @if ($deliveryNote->salesOrder)
                    <a href="{{ route('orders.show', $deliveryNote->salesOrder) }}" class="button button-secondary" style="text-align:center;">Voir la commande</a>
                @endif

                @if ($deliveryNote->isConvertible())
                    <div class="card" style="padding:16px; margin-top:6px;">
                        <strong>Convertir en facture</strong>
                        <div class="muted" style="margin:8px 0 14px;">La facture reprendra les lignes du bon sans nouvelle sortie de stock.</div>
                        <form method="POST" action="{{ route('delivery-notes.convert', $deliveryNote) }}">
                            @csrf
                            <div class="form-grid" style="grid-template-columns:1fr;">
                                <div>
                                    <label for="invoice_date">Date facture</label>
                                    <input id="invoice_date" type="date" name="invoice_date" value="{{ now()->format('Y-m-d') }}" required>
                                </div>
                                <div>
                                    <label for="due_date">Echeance facture</label>
                                    <input id="due_date" type="date" name="due_date" value="{{ now()->addDays(15)->format('Y-m-d') }}">
                                </div>
                                <div>
                                    <label for="notes">Notes facture</label>
                                    <textarea id="notes" name="notes" placeholder="Commentaire optionnel ajoute a la facture"></textarea>
                                </div>
                            </div>
                            <div class="actions" style="justify-content:flex-start; margin-top:14px;">
                                <button type="submit" class="button button-primary">Convertir maintenant</button>
                            </div>
                        </form>
                    </div>
                @else
                    <div class="tip-card">
                        <strong>Bon deja facture</strong>
                        <div class="muted">La facture issue de ce bon est deja disponible.</div>
                    </div>
                @endif
            </div>
        </aside>
    </div>

    <div class="split" style="margin-top:20px;">
        <section class="card">
            <h2 class="section-title">Lignes livrees</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Produit</th>
                        <th>Description</th>
                        <th>Commande</th>
                        <th>Quantite livree</th>
                        <th>PU</th>
                        <th>Total</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($deliveryNote->items as $item)
                        <tr>
                            <td>@include('partials.product-inline', ['product' => $item->product, 'meta' => $item->product?->barcode ?: $item->product?->sku, 'size' => 42])</td>
                            <td>{{ $item->description }}</td>
                            <td>{{ number_format((float) $item->orderItem?->qty, 3, ',', ' ') }}</td>
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
            <h2 class="section-title">Sorties de stock liees</h2>
            @forelse ($stockMovements as $movement)
                <div style="padding-bottom:14px; border-bottom:1px solid #efe4d3; margin-bottom:14px;">
                    @include('partials.product-inline', ['product' => $movement->product, 'meta' => $movement->warehouse?->name, 'size' => 40])
                    <div class="muted" style="margin-top:6px;">{{ $movement->movement_date?->format('d/m/Y H:i') }} · {{ $movement->warehouse?->name ?? 'Entrepot par defaut' }} · {{ $movement->creator?->name ?? 'Systeme' }}</div>
                    <div style="margin-top:6px;">Sortie : {{ number_format((float) $movement->quantity_out, 3, ',', ' ') }}</div>
                </div>
            @empty
                <p class="muted">Aucun mouvement de stock trouve pour ce bon.</p>
            @endforelse
        </section>
    </div>
@endsection


