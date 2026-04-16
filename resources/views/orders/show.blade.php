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
            'cancelled' => 'badge-danger',
            default => 'badge-muted',
        };
        $deliveryProgress = $order->items->sum('qty') > 0 ? round(($order->items->sum('delivered_qty') / $order->items->sum('qty')) * 100, 1) : 0;
        $reservedQty = in_array($order->status, ['confirmed', 'partial_delivered'], true) ? (float) $order->items->sum(fn ($item) => $item->remainingQty()) : 0;
        $hasRemainingDelivery = $order->items->contains(fn ($item) => $item->remainingQty() > 0);
        $invoiceStatusLabel = $order->convertedInvoice ? 'Facturee' : ($order->status === 'delivered' ? 'A facturer' : 'Non facturee');
    @endphp

    <div class="premium-detail-page">
        <section class="card premium-detail-hero premium-detail-hero--ocean">
            <div class="premium-detail-hero__grid">
                <div class="premium-detail-hero__copy">
                    <div class="badge badge-muted">Execution commerciale</div>
                    <h2>{{ $order->order_number }} · {{ $order->customer?->name }}</h2>
                    <p class="muted">Commande client rattachee a l agence {{ $order->branch?->name }}, avec promesse de stock, etat de livraison et prochaines actions de conversion ou d approvisionnement au meme endroit.</p>
                    <div class="premium-detail-hero__meta">
                        <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
                        <span class="badge badge-muted">Facturation : {{ $invoiceStatusLabel }}</span>
                        <span class="badge badge-muted">Depot : {{ $order->warehouse?->name ?? 'Depot principal' }}</span>
                        @if ($order->salesperson_name)
                            <span class="badge badge-muted">Commercial : {{ $order->salesperson_name }}</span>
                        @endif
                    </div>
                </div>
                <div class="premium-detail-panel">
                    <div>
                        <strong>Actions immediates</strong>
                        <div class="muted" style="margin-top:8px;">Imprimer, confirmer, convertir, livrer ou declencher l achat depuis la meme vue de pilotage.</div>
                    </div>
                    <div class="premium-detail-panel__actions">
                        <a href="{{ route('orders.print', $order) }}" class="button button-secondary" target="_blank">PDF</a>
                        <a href="{{ route('orders.index') }}" class="button button-secondary">Retour liste</a>
                        @if (in_array($order->status, ['confirmed', 'partial_delivered'], true) && $hasRemainingDelivery)
                            <a href="{{ route('delivery-notes.create', ['order' => $order->id]) }}" class="button button-primary">Generer un bon de livraison</a>
                        @endif
                    </div>
                </div>
            </div>
        </section>

        <section class="premium-stat-grid">
            <article class="premium-stat-card"><div class="label">Montant commande</div><div class="value">{{ number_format((float) $order->total, 0, ',', ' ') }}</div><div class="hint">Valeur commerciale du dossier client.</div></article>
            <article class="premium-stat-card"><div class="label">Progression livraison</div><div class="value">{{ number_format($deliveryProgress, 1, ',', ' ') }} %</div><div class="hint">Avancement des quantites deja livrees.</div></article>
            <article class="premium-stat-card"><div class="label">Reserve en stock</div><div class="value">{{ number_format($reservedQty, 3, ',', ' ') }}</div><div class="hint">Quantite encore mobilisee pour cette commande.</div></article>
            <article class="premium-stat-card"><div class="label">Lignes a risque</div><div class="value">{{ $coverageSummary['at_risk'] }}</div><div class="hint">Lignes qui demandent une action achat ou arbitrage.</div></article>
        </section>
    <div class="split">
        <section class="card">
            <div style="display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap; align-items:flex-start;">
                <div>
                    <div class="muted">Statut</div>
                    <span class="badge {{ $statusClass }}" style="margin-top:8px;">{{ $statusLabel }}</span>
                    <div class="chip-row" style="margin-top:10px;">
                        <span class="badge badge-muted">Facturation : {{ $invoiceStatusLabel }}</span>
                        @if ($order->salesperson_name)
                            <span class="badge badge-muted">Commercial : {{ $order->salesperson_name }}</span>
                        @endif
                        <span class="badge badge-muted">Depot : {{ $order->warehouse?->name ?? 'Depot principal' }}</span>
                    </div>
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
                    <div class="muted">Date d engagement</div>
                    <strong>{{ $order->commitment_date?->format('d/m/Y') ?? 'Non renseignee' }}</strong>
                </div>
                <div>
                    <div class="muted">Reference client</div>
                    <strong>{{ $order->customer_reference ?: 'Aucune' }}</strong>
                </div>
                <div>
                    <div class="muted">Document source</div>
                    <strong>{{ $order->source_document ?: 'Aucun' }}</strong>
                </div>
                <div>
                    <div class="muted">Creee par</div>
                    <strong>{{ $order->creator?->name ?? 'Systeme' }}</strong>
                </div>
                <div>
                    <div class="muted">Depot de preparation</div>
                    <strong>{{ $order->warehouse?->name ?? 'Depot principal agence' }}</strong>
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
                    <div class="muted">Devis source</div>
                    @if ($order->originQuote)
                        <a href="{{ route('quotes.show', $order->originQuote) }}"><strong>{{ $order->originQuote->quote_number }}</strong></a>
                    @else
                        <strong>Aucun</strong>
                    @endif
                </div>
                <div>
                    <div class="muted">Confirmee le</div>
                    <strong>{{ $order->confirmed_at?->format('d/m/Y H:i') ?? 'Pas encore' }}</strong>
                </div>
            </div>

            @if ($order->delivery_instruction)
                <div class="card" style="margin-top:18px; padding:16px;">
                    <div class="muted">Instructions de livraison</div>
                    <div style="margin-top:8px; line-height:1.6;">{{ $order->delivery_instruction }}</div>
                </div>
            @endif

            <div class="card" style="margin-top:18px; padding:16px;">
                <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:center;">
                    <div>
                        <div class="muted">Progression livraison</div>
                        <strong>{{ number_format((float) $order->items->sum('delivered_qty'), 3, ',', ' ') }} / {{ number_format((float) $order->items->sum('qty'), 3, ',', ' ') }}</strong>
                    </div>
                    <span class="badge {{ $deliveryProgress >= 100 ? 'badge-success' : 'badge-warning' }}">{{ number_format($deliveryProgress, 1, ',', ' ') }} %</span>
                </div>
                <div class="progress" style="margin-top:12px;"><div class="progress-bar" style="width: {{ min(100, $deliveryProgress) }}%;"></div></div>
                <div class="chip-row" style="margin-top:12px;">
                    <span class="badge badge-warning">Reserve en stock : {{ number_format($reservedQty, 3, ',', ' ') }}</span>
                    <span class="badge badge-muted">Reste a livrer : {{ number_format((float) $order->items->sum(fn ($item) => $item->remainingQty()), 3, ',', ' ') }}</span>
                </div>
            </div>

            <div class="grid" style="grid-template-columns: repeat(3, minmax(0, 1fr)); gap:14px; margin-top:18px;">
                <div class="card" style="padding:16px;">
                    <div class="muted">Lignes couvertes maintenant</div>
                    <div class="value" style="font-size:1.5rem; margin-top:6px;">{{ $coverageSummary['covered_now'] }}</div>
                    <div class="muted" style="margin-top:8px;">Disponibles ou deja securisees par la reservation.</div>
                </div>
                <div class="card" style="padding:16px;">
                    <div class="muted">Couvre par appro</div>
                    <div class="value" style="font-size:1.5rem; margin-top:6px;">{{ $coverageSummary['covered_incoming'] }}</div>
                    <div class="muted" style="margin-top:8px;">Depend d un achat fournisseur deja attendu.</div>
                </div>
                <div class="card" style="padding:16px;">
                    <div class="muted">Lignes a risque</div>
                    <div class="value" style="font-size:1.5rem; margin-top:6px;">{{ $coverageSummary['at_risk'] }}</div>
                    <div class="muted" style="margin-top:8px;">Demandent une action achat ou un arbitrage stock.</div>
                </div>
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
            @if (isset($portal))
                <div class="card" style="padding:16px; margin-bottom:14px;">
                    <strong>Portail client</strong>
                    <div class="muted" style="margin:8px 0 12px;">Lien signe partageable jusqu au {{ $portal['expires_at']->format('d/m/Y H:i') }}.</div>
                    <div>
                        <label for="order_portal_url">Lien partageable</label>
                        <input id="order_portal_url" type="text" value="{{ $portal['view_url'] }}" readonly onclick="this.select()" style="font-size:12px;">
                    </div>
                    <div class="actions" style="justify-content:flex-start; margin-top:12px;">
                        <a href="{{ $portal['view_url'] }}" class="button button-secondary" target="_blank" rel="noopener">Ouvrir le portail</a>
                        @if ($portal['whatsapp_url'])
                            <a href="{{ $portal['whatsapp_url'] }}" class="button button-primary" target="_blank" rel="noopener">Partager via WhatsApp</a>
                        @endif
                    </div>
                </div>
            @endif
            @if ($order->latestPortalAction)
                <div class="card" style="padding:16px; margin-bottom:14px;">
                    @include('partials.portal-action-summary', ['portalAction' => $order->latestPortalAction, 'title' => 'Signature client portail'])
                </div>
            @endif

            <div class="card" style="padding:16px; margin-bottom:14px;">
                <strong>Promesse logistique</strong>
                <div class="muted" style="margin:8px 0 12px;">Lecture rapide du reste a livrer ou a couvrir sur cette commande.</div>
                <div class="chip-row">
                    <span class="badge badge-success">Couvert maintenant : {{ $coverageSummary['covered_now'] }}</span>
                    <span class="badge badge-warning">Avec achat attendu : {{ $coverageSummary['covered_incoming'] }}</span>
                    <span class="badge badge-danger">A risque : {{ $coverageSummary['at_risk'] }}</span>
                </div>
                @allowed('purchase_requests.manage')
                    @if ($openGeneratedPurchaseRequest)
                        <div class="muted" style="margin:12px 0 10px;">Une demande d achat issue de cette commande est deja ouverte.</div>
                        <a href="{{ route('purchase-requests.show', $openGeneratedPurchaseRequest) }}" class="button button-secondary" style="width:100%; text-align:center;">Ouvrir la demande liee</a>
                    @elseif ($coverageSummary['at_risk'] > 0)
                        <div class="muted" style="margin:12px 0 10px;">Genere automatiquement une demande d achat sur les lignes encore non couvertes.</div>
                        <form method="POST" action="{{ route('orders.generate-purchase-request', $order) }}">
                            @csrf
                            <button type="submit" class="button button-primary" style="width:100%;">Generer une demande d achat</button>
                        </form>
                    @endif
                @endallowed
            </div>

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
                    <th>Reserve</th>
                    <th>Reste</th>
                    <th>Couverture previsionnelle</th>
                    <th>PU</th>
                    <th>Total</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($order->items as $item)
                    @php
                        $coverage = $lineCoverage[$item->id] ?? null;
                    @endphp
                    <tr>
                        <td>@include('partials.product-inline', ['product' => $item->product, 'meta' => $item->product?->barcode ?: $item->product?->sku, 'size' => 42])</td>
                        <td>{{ $item->description }}</td>
                        <td>{{ number_format((float) $item->qty, 3, ',', ' ') }}</td>
                        <td>{{ number_format((float) $item->delivered_qty, 3, ',', ' ') }}</td>
                        <td>{{ number_format(in_array($order->status, ['confirmed', 'partial_delivered'], true) ? $item->remainingQty() : 0, 3, ',', ' ') }}</td>
                        <td>{{ number_format($item->remainingQty(), 3, ',', ' ') }}</td>
                        <td style="min-width:240px;">
                            @if ($coverage)
                                <span class="badge {{ $coverage['tone'] }}">{{ $coverage['label'] }}</span>
                                <div class="muted" style="margin-top:8px; line-height:1.5;">{{ $coverage['detail'] }}</div>
                                @if ($coverage['available_now'] !== null)
                                    <div class="muted" style="margin-top:6px;">
                                        ATP {{ number_format((float) $coverage['available_now'], 3, ',', ' ') }}
                                        @if ((float) $coverage['incoming_qty'] > 0)
                                            · Entrant {{ number_format((float) $coverage['incoming_qty'], 3, ',', ' ') }}
                                        @endif
                                        @if ($coverage['next_incoming_date'])
                                            · Attendu le {{ $coverage['next_incoming_date']->format('d/m/Y') }}
                                        @endif
                                    </div>
                                @endif
                            @else
                                <span class="badge badge-muted">Analyse indisponible</span>
                            @endif
                        </td>
                        <td>{{ number_format((float) $item->unit_price, 0, ',', ' ') }} XOF</td>
                        <td>{{ number_format((float) $item->line_total, 0, ',', ' ') }} XOF</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="card" style="margin-top:20px;">
        <h2 class="section-title">Demandes d achat liees</h2>
        @forelse ($generatedPurchaseRequests as $generatedRequest)
            <div style="padding-bottom:12px; border-bottom:1px solid #efe4d3; margin-bottom:12px; display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                <div>
                    <strong>{{ $generatedRequest->request_number }}</strong>
                    <div class="muted" style="margin-top:6px;">{{ $generatedRequest->request_date?->format('d/m/Y') }} · {{ $generatedRequest->warehouse?->name ?? 'Entrepot par defaut' }}</div>
                    <div class="muted" style="margin-top:6px;">Statut {{ str_replace('_', ' ', $generatedRequest->status) }}</div>
                </div>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <a href="{{ route('purchase-requests.show', $generatedRequest) }}" class="button button-secondary">Voir la demande</a>
                    @if ($generatedRequest->convertedPurchaseOrder)
                        <a href="{{ route('purchase-orders.show', $generatedRequest->convertedPurchaseOrder) }}" class="button button-secondary">Voir la commande fournisseur</a>
                    @endif
                </div>
            </div>
        @empty
            <p class="muted">Aucune demande d achat n'a encore ete generee depuis cette commande.</p>
        @endforelse
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
    </div>
@endsection


