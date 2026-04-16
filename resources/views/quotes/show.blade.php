@extends('layouts.app')

@section('title', 'Devis '.$quote->quote_number)
@section('page-title', 'Detail devis client')

@section('content')
    @php
        $statusLabel = match ($quote->status) {
            'draft' => 'Brouillon',
            'sent' => 'Envoye',
            'accepted' => 'Accepte',
            'converted' => 'Converti',
            'cancelled' => 'Annule',
            default => $quote->status,
        };
        $statusClass = in_array($quote->status, ['accepted', 'converted'], true) ? 'badge-success' : ($quote->status === 'cancelled' ? 'badge-warning' : 'badge-muted');
    @endphp

    <div class="page-head">
        <div>
            <h2 style="margin:0;">{{ $quote->quote_number }}</h2>
            <div class="muted">Client {{ $quote->customer?->name }} · Agence {{ $quote->branch?->name }}</div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="{{ route('quotes.print', $quote) }}" class="button button-secondary" target="_blank">PDF</a>
            <a href="{{ route('quotes.index') }}" class="button button-secondary">Retour liste</a>
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
                    <div class="muted">Montant du devis</div>
                    <div class="value">{{ number_format((float) $quote->total, 0, ',', ' ') }} XOF</div>
                </div>
            </div>

            <div class="grid" style="grid-template-columns: repeat(2, minmax(0, 1fr)); margin-top:18px;">
                <div>
                    <div class="muted">Date du devis</div>
                    <strong>{{ $quote->quote_date?->format('d/m/Y') }}</strong>
                </div>
                <div>
                    <div class="muted">Valable jusqu au</div>
                    <strong>{{ $quote->valid_until?->format('d/m/Y') ?? 'Non renseignee' }}</strong>
                </div>
                <div>
                    <div class="muted">Cree par</div>
                    <strong>{{ $quote->creator?->name ?? 'Systeme' }}</strong>
                </div>
                <div>
                    <div class="muted">Facture issue du devis</div>
                    @if ($quote->convertedInvoice)
                        <a href="{{ route('sales.show', $quote->convertedInvoice) }}"><strong>{{ $quote->convertedInvoice->invoice_number }}</strong></a>
                    @else
                        <strong>Aucune</strong>
                    @endif
                </div>
                <div>
                    <div class="muted">Commande issue du devis</div>
                    @if ($quote->convertedOrder)
                        <a href="{{ route('orders.show', $quote->convertedOrder) }}"><strong>{{ $quote->convertedOrder->order_number }}</strong></a>
                    @else
                        <strong>Aucune</strong>
                    @endif
                </div>
            </div>

            @if ($quote->notes)
                <div class="card" style="margin-top:18px; padding:16px;">
                    <div class="muted">Notes</div>
                    <div style="margin-top:8px;">{{ $quote->notes }}</div>
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
                        <label for="quote_portal_url">Lien partageable</label>
                        <input id="quote_portal_url" type="text" value="{{ $portal['view_url'] }}" readonly onclick="this.select()" style="font-size:12px;">
                    </div>
                    <div class="actions" style="justify-content:flex-start; margin-top:12px;">
                        <a href="{{ $portal['view_url'] }}" class="button button-secondary" target="_blank" rel="noopener">Ouvrir le portail</a>
                        @if ($portal['whatsapp_url'])
                            <a href="{{ $portal['whatsapp_url'] }}" class="button button-primary" target="_blank" rel="noopener">Partager via WhatsApp</a>
                        @endif
                    </div>
                </div>
            @endif
            @if ($quote->latestPortalAction)
                <div class="card" style="padding:16px; margin-bottom:14px;">
                    @include('partials.portal-action-summary', ['portalAction' => $quote->latestPortalAction, 'title' => 'Signature client portail'])
                </div>
            @endif
            <div class="summary-stack">
                @if ($quote->status === 'draft')
                    <form method="POST" action="{{ route('quotes.send', $quote) }}">
                        @csrf
                        <button type="submit" class="button button-primary" style="width:100%;">Marquer comme envoye</button>
                    </form>
                    <form method="POST" action="{{ route('quotes.accept', $quote) }}">
                        @csrf
                        <button type="submit" class="button button-secondary" style="width:100%;">Marquer comme accepte</button>
                    </form>
                @elseif ($quote->status === 'sent')
                    <form method="POST" action="{{ route('quotes.accept', $quote) }}">
                        @csrf
                        <button type="submit" class="button button-primary" style="width:100%;">Valider acceptance client</button>
                    </form>
                @endif

                @if (in_array($quote->status, ['draft', 'sent', 'accepted'], true))
                    <form method="POST" action="{{ route('quotes.cancel', $quote) }}">
                        @csrf
                        <button type="submit" class="button button-secondary" style="width:100%;">Annuler le devis</button>
                    </form>
                @endif

                @if ($quote->isConvertible())
                    <div class="card" style="padding:16px; margin-top:6px;">
                        <strong>Convertir en commande client</strong>
                        <div class="muted" style="margin:8px 0 14px;">A utiliser quand le client confirme le devis et qu il faut ouvrir un flux commande puis livraison.</div>
                        <form method="POST" action="{{ route('quotes.convert-order', $quote) }}">
                            @csrf
                            <div class="form-grid" style="grid-template-columns:1fr;">
                                <div>
                                    <label for="order_date">Date commande</label>
                                    <input id="order_date" type="date" name="order_date" value="{{ now()->format('Y-m-d') }}" required>
                                </div>
                                <div>
                                    <label for="requested_delivery_date">Livraison souhaitee</label>
                                    <input id="requested_delivery_date" type="date" name="requested_delivery_date" value="{{ $quote->valid_until?->format('Y-m-d') }}">
                                </div>
                                <div>
                                    <label for="order_notes">Notes commande</label>
                                    <textarea id="order_notes" name="notes" placeholder="Commentaire optionnel ajoute a la commande"></textarea>
                                </div>
                            </div>
                            <div class="actions" style="justify-content:flex-start; margin-top:14px;">
                                <button type="submit" class="button button-primary">Convertir en commande</button>
                            </div>
                        </form>
                    </div>

                    <div class="card" style="padding:16px; margin-top:6px;">
                        <strong>Convertir en facture</strong>
                        <div class="muted" style="margin:8px 0 14px;">A utiliser si tu veux facturer directement sans passer par une commande et une livraison.</div>
                        <form method="POST" action="{{ route('quotes.convert', $quote) }}">
                            @csrf
                            <div class="form-grid" style="grid-template-columns:1fr;">
                                <div>
                                    <label for="invoice_date">Date facture</label>
                                    <input id="invoice_date" type="date" name="invoice_date" value="{{ now()->format('Y-m-d') }}" required>
                                </div>
                                <div>
                                    <label for="due_date">Echeance facture</label>
                                    <input id="due_date" type="date" name="due_date" value="{{ $quote->valid_until?->format('Y-m-d') }}">
                                </div>
                                <div>
                                    <label for="invoice_notes">Notes facture</label>
                                    <textarea id="invoice_notes" name="notes" placeholder="Commentaire optionnel ajoute a la facture"></textarea>
                                </div>
                            </div>
                            <div class="actions" style="justify-content:flex-start; margin-top:14px;">
                                <button type="submit" class="button button-primary">Convertir maintenant</button>
                            </div>
                        </form>
                    </div>
                @elseif ($quote->status === 'converted')
                    <div class="tip-card">
                        <strong>Devis deja converti</strong>
                        <div class="muted">
                            @if ($quote->convertedOrder)
                                La commande issue du devis est disponible dans la fiche ci-contre.
                            @else
                                La facture issue du devis est disponible dans la fiche ci-contre.
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </aside>
    </div>

    <section class="card" style="margin-top:20px;">
        <h2 class="section-title">Lignes du devis</h2>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Produit</th>
                    <th>Description</th>
                    <th>Quantite</th>
                    <th>PU</th>
                    <th>Total</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($quote->items as $item)
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
@endsection


