@extends('layouts.portal')

@section('title', 'Portail devis '.$quote->quote_number)

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
        $statusClass = match ($quote->status) {
            'accepted', 'converted' => 'badge-success',
            'cancelled' => 'badge-danger',
            'sent' => 'badge-warning',
            default => 'badge-muted',
        };
        $portalAction = $quote->latestPortalAction;
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
                <h1>{{ $quote->quote_number }}</h1>
                <p class="muted">{{ $quote->company?->name }} partage avec vous un devis client consultable, signable et confirmable en ligne.</p>
                <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:12px;">
                    <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
                    <span class="badge badge-muted">Valide jusqu au {{ $portal['expires_at']->format('d/m/Y H:i') }}</span>
                </div>
            </div>
            <div class="summary-box">
                <div class="label">Montant du devis</div>
                <div class="value">{{ number_format((float) $quote->total, 0, ',', ' ') }} XOF</div>
                <div class="muted" style="margin-top:10px;">Client {{ $quote->customer?->name }} · Agence {{ $quote->branch?->name }}</div>
            </div>
        </div>
    </section>

    <div class="layout">
        <section class="stack">
            <div class="card">
                <div class="summary-grid">
                    <div class="summary-box">
                        <div class="label">Date du devis</div>
                        <div class="value" style="font-size:22px;">{{ $quote->quote_date?->format('d/m/Y') }}</div>
                    </div>
                    <div class="summary-box">
                        <div class="label">Validite commerciale</div>
                        <div class="value" style="font-size:22px;">{{ $quote->valid_until?->format('d/m/Y') ?? 'Non renseignee' }}</div>
                    </div>
                    <div class="summary-box">
                        <div class="label">Emis par</div>
                        <div class="value" style="font-size:22px;">{{ $quote->creator?->name ?? 'Equipe commerciale' }}</div>
                    </div>
                    <div class="summary-box">
                        <div class="label">Reference client</div>
                        <div class="value" style="font-size:22px;">{{ $quote->customer?->code ?: 'Non renseignee' }}</div>
                    </div>
                </div>

                @if ($quote->notes)
                    <div class="notice" style="margin-top:16px;">
                        <strong>Notes commerciales</strong>
                        <div style="margin-top:8px; line-height:1.6;">{{ $quote->notes }}</div>
                    </div>
                @endif
            </div>

            @if ($portalAction)
                <div class="card">
                    @include('partials.portal-action-summary', ['portalAction' => $portalAction, 'title' => 'Derniere signature client'])
                </div>
            @endif

            <div class="card">
                <h2 style="margin-top:0;">Lignes du devis</h2>
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
                                <td>@include('partials.product-inline', ['product' => $item->product, 'meta' => $item->product?->barcode ?: $item->product?->sku, 'size' => 40, 'link' => null])</td>
                                <td>{{ $item->description }}</td>
                                <td>{{ number_format((float) $item->qty, 3, ',', ' ') }}</td>
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
                <div class="muted" style="margin-bottom:14px;">Confirme le devis avec une vraie signature nominative. Tu peux aussi annoncer un acompte et sa reference pour accelerer le traitement commercial.</div>

                @if ($portal['can_accept'])
                    @include('portal.sales._signature-form', [
                        'actionUrl' => $portal['accept_url'],
                        'submitLabel' => 'Signer et confirmer ce devis',
                        'documentLabel' => 'ce devis',
                        'depositMethods' => $depositMethods,
                    ])
                @elseif ($quote->status === 'accepted')
                    <div class="notice notice-success">
                        <strong>Devis deja confirme</strong>
                        <div class="muted" style="margin-top:6px;">Le devis a ete valide le {{ $quote->accepted_at?->format('d/m/Y H:i') ?? 'moment precedent' }}.</div>
                    </div>
                @elseif ($quote->status === 'converted')
                    <div class="notice notice-success">
                        <strong>Devis deja converti</strong>
                        <div class="muted" style="margin-top:6px;">Le devis a deja ete transforme en document commercial interne.</div>
                    </div>
                @else
                    <div class="notice notice-warning">
                        <strong>Action indisponible</strong>
                        <div class="muted" style="margin-top:6px;">Ce devis n est plus confirmable depuis le portail.</div>
                    </div>
                @endif
            </div>

            <div class="card">
                <h2 style="margin-top:0;">Coordonnees</h2>
                <div class="stack">
                    <div>
                        <div class="muted">Entreprise</div>
                        <strong>{{ $quote->company?->name }}</strong>
                    </div>
                    <div>
                        <div class="muted">Agence</div>
                        <strong>{{ $quote->branch?->name ?? 'Agence principale' }}</strong>
                    </div>
                    <div>
                        <div class="muted">Telephone</div>
                        <strong>{{ $quote->company?->phone ?: 'Non renseigne' }}</strong>
                    </div>
                </div>
            </div>
        </aside>
    </div>
@endsection
