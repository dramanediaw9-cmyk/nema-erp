@extends('layouts.app')

@section('title', 'Commande '.$order->order_number)
@section('page-title', 'Detail commande client')

@section('content')
    @php
        $statusLabel = match ($order->status) {
            'draft' => 'Brouillon',
            'confirmed' => 'Confirmee',
            'partial_delivered' => 'Partiellement livree',
            'delivered' => 'Livree',
            'converted' => 'Convertie',
            'cancelled' => 'Annulee',
            default => $order->status,
        };
        $statusClass = match ($order->status) {
            'confirmed', 'delivered', 'converted' => 'badge-success',
            'partial_delivered' => 'badge-warning',
            'cancelled' => 'badge-muted',
            default => 'badge-muted',
        };
        $deliveryProgress = $order->items->sum('qty') > 0 ? round(($order->items->sum('delivered_qty') / $order->items->sum('qty')) * 100, 1) : 0;
        $hasRemainingDelivery = $order->items->contains(fn ($item) => $item->remainingQty() > 0);
    @endphp

    <div class="page-head">
        <div>
            <h2 style="margin:0;">{{ $order->order_number }}</h2>
            <div class="muted">Client {{ $order->customer?->name }} · Agence {{ $order->branch?->name }}</div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="{{ route('orders.print', $order) }}" class="button button-secondary" target="_blank">Imprimer</a>
            <a href="{{ route('orders.index') }}" class="button button-secondary">Retour liste</a>
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
                    <div class="muted">Montant de la commande</div>
                    <div class="value">{{ number_format((float) $order->total, 0, ',', ' ') }} XOF</div>
                </div>
            </div>

            <div class="grid" style="grid-template-columns: repeat(2, minmax(0, 1fr)); margin-top:18px;">
                <div>
                    <div class="muted">Date de commande</div>
                    <strong>{{ $order->order_date?->format('d/m/Y') }}</strong>
                </div>
                <div>
                    <div class="muted">Livraison souhaitee</div>
                    <strong>{{ $order->requested_delivery_date?->format('d/m/Y') ?? 'Non renseignee' }}</strong>
                </div>
                <div>
                    <div class="muted">Creee par</div>
                    <strong>{{ $order->creator?->name ?? 'Systeme' }}</strong>
                </div>
                <div>
                    <div class="muted">Facture directe issue de la commande</div>
                    @if ($order->convertedInvoice)
                        <a href="{{ route('sales.show', $order->convertedInvoice) }}"><strong>{{ $order->convertedInvoice->invoice_number }}</strong></a>
                    @else
                        <strong>Aucune</strong>
                    @endif
                </div>
                <div>
                    <div class="muted">Confirmee le</div>
                    <strong>{{ $order->confirmed_at?->format('d/m/Y H:i') ?? 'Pas encore' }}</strong>
                </div>
                <div>
                    <div class="muted">Devis source</div>
                    @if ($order->originQuote)
                        <a href="{{ route('quotes.show', $order->originQuote) }}"><strong>{{ $order->originQuote->quote_number }}</strong></a>
                    @else
                        <strong>Aucun</strong>
                    @endif
                </div>
            </div>

            <div class="card" style="margin-top:18px; padding:16px;">
                <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:center;">
                    <div>
                        <div class="muted">Progression livraison</div>
                        <strong>{{ number_format((float) $order->items->sum('delivered_qty'), 3, ',', ' ') }} / {{ number_format((float) $order->items->sum('qty'), 3, ',', ' ') }}</strong>
                    </div>
                    <span class="badge {{ $deliveryProgress >= 100 ? 'badge-success' : 'badge-warning' }}">{{ number_format($deliveryProgress, 1, ',', ' ') }} %</span>
                </div>
                <div class="progress" style="margin-top:12px;"><div class="progress-bar" style="width: {{ min(100, $deliveryProgress) }}%;"></div></div>
            </div>

            @if ($order->notes)
                <div class="card" style="margin-top:18px; padding:16px;">
                    <div class="muted">Notes</div>
                    <div style="margin-top:8px;">{{ $order->notes }}</div>
                </div>
            @endif
        </section>

        <aside class="card">
            <h2 class="section-title">Actions</h2>
            <div class="summary-stack">
                @if ($order->status === 'draft')
                    <form method="POST" action="{{ route('orders.confirm', $order) }}">
                        @csrf
                        <button type="submit" class="button button-primary" style="width:100%;">Confirmer la commande</button>
                    </form>
                @endif

                @if (in_array($order->status, ['draft', 'confirmed'], true))
                    <form method="POST" action="{{ route('orders.cancel', $order) }}">
                        @csrf
                        <button type="submit" class="button button-secondary" style="width:100%;">Annuler la commande</button>
                    </form>
                @endif

                @if (in_array($order->status, ['confirmed', 'partial_delivered'], true) && $hasRemainingDelivery)
                    <a href="{{ route('delivery-notes.create', ['order' => $order->id]) }}" class="button button-primary" style="text-align:center;">Generer un bon de livraison</a>
                @endif

                @if ($order->isConvertible() && $order->deliveryNotes->isEmpty())
                    <div class="card" style="padding:16px; margin-top:6px;">
                        <strong>Convertir en facture</strong>
                        <div class="muted" style="margin:8px 0 14px;">Utilise cette option seulement si tu factures directement sans bon de livraison intermediaire.</div>
                        <form method="POST" action="{{ route('orders.convert', $order) }}">
                            @csrf
                            <div class="form-grid" style="grid-template-columns:1fr;">
                                <div>
                                    <label for="invoice_date">Date facture</label>
                                    <input id="invoice_date" type="date" name="invoice_date" value="{{ now()->format('Y-m-d') }}" required>
                                </div>
                                <div>
                                    <label for="due_date">Echeance facture</label>
                                    <input id="due_date" type="date" name="due_date" value="{{ $order->requested_delivery_date?->format('Y-m-d') }}">
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
                @elseif ($order->status === 'converted')
                    <div class="tip-card">
                        <strong>Commande deja convertie</strong>
                        <div class="muted">La facture issue de cette commande est disponible dans la fiche ci-contre.</div>
                    </div>
                @endif
            </div>
        </aside>
    </div>

    <section class="card" style="margin-top:20px;">
        <h2 class="section-title">Lignes de commande</h2>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Produit</th>
                    <th>Description</th>
                    <th>Commande</th>
                    <th>Deja livre</th>
                    <th>Reste</th>
                    <th>PU</th>
                    <th>Total</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($order->items as $item)
                    <tr>
                        <td>@include('partials.product-inline', ['product' => $item->product, 'meta' => $item->product?->barcode ?: $item->product?->sku, 'size' => 42])</td>
                        <td>{{ $item->description }}</td>
                        <td>{{ number_format((float) $item->qty, 3, ',', ' ') }}</td>
                        <td>{{ number_format((float) $item->delivered_qty, 3, ',', ' ') }}</td>
                        <td>{{ number_format($item->remainingQty(), 3, ',', ' ') }}</td>
                        <td>{{ number_format((float) $item->unit_price, 0, ',', ' ') }} XOF</td>
                        <td>{{ number_format((float) $item->line_total, 0, ',', ' ') }} XOF</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="card" style="margin-top:20px;">
        <h2 class="section-title">Bons de livraison lies</h2>
        @forelse ($order->deliveryNotes->sortByDesc('delivery_date') as $deliveryNote)
            <div style="padding-bottom:12px; border-bottom:1px solid #efe4d3; margin-bottom:12px; display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                <div>
                    <strong>{{ $deliveryNote->delivery_number }}</strong>
                    <div class="muted" style="margin-top:6px;">{{ $deliveryNote->delivery_date?->format('d/m/Y') }} · {{ $deliveryNote->warehouse?->name ?? 'Entrepot par defaut' }}</div>
                    <div class="muted" style="margin-top:6px;">{{ $deliveryNote->status === 'invoiced' ? 'Deja facture' : 'En attente de facturation' }}</div>
                </div>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <a href="{{ route('delivery-notes.show', $deliveryNote) }}" class="button button-secondary">Voir le bon</a>
                    @if ($deliveryNote->convertedInvoice)
                        <a href="{{ route('sales.show', $deliveryNote->convertedInvoice) }}" class="button button-secondary">Voir la facture</a>
                    @endif
                </div>
            </div>
        @empty
            <p class="muted">Aucun bon de livraison n'a encore ete emis sur cette commande.</p>
        @endforelse
    </section>
@endsection

