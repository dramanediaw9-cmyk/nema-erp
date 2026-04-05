@extends('layouts.portal')

@section('title', 'Portail commande '.$order->order_number)

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
        $portalAction = $order->latestPortalAction;
    @endphp

    @if (session('success'))
        <div class="notice notice-success">{{ session('success') }}</div>
    @endif
    @if (session('warning'))
        <div class="notice notice-warning">{{ session('warning') }}</div>
    @endif
    @if ($errors->any())
        <div class="notice notice-warning">
            <strong>La confirmation n a pas pu etre enregistree.</strong>
            <div class="muted" style="margin-top:8px;">Corrige les champs signales puis renvoie la signature.</div>
        </div>
    @endif

    <section class="card hero">
        <div class="hero-grid">
            <div>
                <div class="kicker">Portail client · Nema ERP</div>
                <h1>{{ $order->order_number }}</h1>
                <p class="muted">{{ $order->company?->name }} partage avec vous une commande client consultable, signable et confirmable en ligne.</p>
                <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:12px;">
                    <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
                    <span class="badge badge-muted">Lien actif jusqu au {{ $portal['expires_at']->format('d/m/Y H:i') }}</span>
                </div>
            </div>
            <div class="summary-box">
                <div class="label">Montant de la commande</div>
                <div class="value">{{ number_format((float) $order->total, 0, ',', ' ') }} XOF</div>
                <div class="muted" style="margin-top:10px;">Client {{ $order->customer?->name }} · Agence {{ $order->branch?->name }}</div>
            </div>
        </div>
    </section>

    <div class="layout">
        <section class="stack">
            <div class="card">
                <div class="summary-grid">
                    <div class="summary-box">
                        <div class="label">Date commande</div>
                        <div class="value" style="font-size:22px;">{{ $order->order_date?->format('d/m/Y') }}</div>
                    </div>
                    <div class="summary-box">
                        <div class="label">Livraison souhaitee</div>
                        <div class="value" style="font-size:22px;">{{ $order->requested_delivery_date?->format('d/m/Y') ?? 'Non renseignee' }}</div>
                    </div>
                    <div class="summary-box">
                        <div class="label">Date engagement</div>
                        <div class="value" style="font-size:22px;">{{ $order->commitment_date?->format('d/m/Y') ?? 'Non renseignee' }}</div>
                    </div>
                    <div class="summary-box">
                        <div class="label">Reference client</div>
                        <div class="value" style="font-size:22px;">{{ $order->customer_reference ?: 'Non renseignee' }}</div>
                    </div>
                </div>

                @if ($order->delivery_instruction)
                    <div class="notice" style="margin-top:16px;">
                        <strong>Instruction de livraison</strong>
                        <div style="margin-top:8px; line-height:1.6;">{{ $order->delivery_instruction }}</div>
                    </div>
                @endif

                @if ($order->notes)
                    <div class="notice" style="margin-top:16px;">
                        <strong>Notes commerciales</strong>
                        <div style="margin-top:8px; line-height:1.6;">{{ $order->notes }}</div>
                    </div>
                @endif
            </div>

            @if ($portalAction)
                <div class="card">
                    @include('partials.portal-action-summary', ['portalAction' => $portalAction, 'title' => 'Derniere signature client'])
                </div>
            @endif

            <div class="card">
                <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:center; margin-bottom:12px;">
                    <div>
                        <h2 style="margin:0;">Progression livraison</h2>
                        <div class="muted">{{ number_format((float) $order->items->sum('delivered_qty'), 3, ',', ' ') }} / {{ number_format((float) $order->items->sum('qty'), 3, ',', ' ') }}</div>
                    </div>
                    <span class="badge {{ $deliveryProgress >= 100 ? 'badge-success' : 'badge-warning' }}">{{ number_format($deliveryProgress, 1, ',', ' ') }} %</span>
                </div>
            </div>

            <div class="card">
                <h2 style="margin-top:0;">Lignes de commande</h2>
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
                                <td>@include('partials.product-inline', ['product' => $item->product, 'meta' => $item->product?->barcode ?: $item->product?->sku, 'size' => 40, 'link' => null])</td>
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
            </div>
        </section>

        <aside class="stack portal-actions">
            <div class="card">
                <h2 style="margin-top:0;">Action client</h2>
                <div class="muted" style="margin-bottom:14px;">Confirme la commande avec une signature nominative pour lancer sa prise en charge commerciale et logistique. Tu peux aussi annoncer un acompte et sa reference.</div>

                @if ($portal['can_confirm'])
                    @include('portal.sales._signature-form', [
                        'actionUrl' => $portal['confirm_url'],
                        'submitLabel' => 'Signer et confirmer cette commande',
                        'documentLabel' => 'cette commande',
                        'depositMethods' => $depositMethods,
                    ])
                @elseif ($order->status === 'confirmed')
                    <div class="notice notice-success">
                        <strong>Commande deja confirmee</strong>
                        <div class="muted" style="margin-top:6px;">La commande a ete validee le {{ $order->confirmed_at?->format('d/m/Y H:i') ?? 'moment precedent' }}.</div>
                    </div>
                @else
                    <div class="notice notice-warning">
                        <strong>Action indisponible</strong>
                        <div class="muted" style="margin-top:6px;">Cette commande n est plus confirmable depuis le portail.</div>
                    </div>
                @endif
            </div>

            <div class="card">
                <h2 style="margin-top:0;">Coordonnees</h2>
                <div class="stack">
                    <div>
                        <div class="muted">Entreprise</div>
                        <strong>{{ $order->company?->name }}</strong>
                    </div>
                    <div>
                        <div class="muted">Agence</div>
                        <strong>{{ $order->branch?->name ?? 'Agence principale' }}</strong>
                    </div>
                    <div>
                        <div class="muted">Telephone</div>
                        <strong>{{ $order->company?->phone ?: 'Non renseigne' }}</strong>
                    </div>
                </div>
            </div>
        </aside>
    </div>
@endsection
