@extends('layouts.portal')

@section('title', 'Reglement facture '.$invoice->invoice_number)

@section('content')
    @php
        $statusLabel = $invoice->payment_status === 'paid' ? 'Payee' : ($invoice->payment_status === 'partial' ? 'Partiellement reglee' : 'A regler');
        $statusClass = $invoice->payment_status === 'paid' ? 'badge-success' : ($invoice->payment_status === 'partial' ? 'badge-warning' : 'badge-muted');
        $portalAction = $invoice->latestPortalAction;
    @endphp

    @if (session('success'))
        <div class="notice notice-success">{{ session('success') }}</div>
    @endif
    @if (session('warning'))
        <div class="notice notice-warning">{{ session('warning') }}</div>
    @endif
    @if ($errors->any())
        <div class="notice notice-warning">
            <strong>L avis de reglement n a pas pu etre transmis.</strong>
            <div class="muted" style="margin-top:8px;">Corrige les champs signales puis renvoie les informations de paiement.</div>
        </div>
    @endif

    <section class="card hero">
        <div class="hero-grid">
            <div>
                <div class="kicker">Portail client · Reglement facture</div>
                <h1>{{ $invoice->invoice_number }}</h1>
                <p class="muted">{{ $invoice->company?->name }} met a disposition un lien de reglement pour transmettre un avis de paiement rapide a l equipe.</p>
                <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:12px;">
                    <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
                    <span class="badge badge-muted">Lien actif jusqu au {{ $portal['expires_at']->format('d/m/Y H:i') }}</span>
                </div>
            </div>
            <div class="summary-box">
                <div class="label">Solde restant</div>
                <div class="value">{{ number_format((float) $invoice->balance_due, 0, ',', ' ') }} XOF</div>
                <div class="muted" style="margin-top:10px;">Facture totale {{ number_format((float) $invoice->total, 0, ',', ' ') }} XOF · Deja regle {{ number_format((float) $invoice->amount_paid, 0, ',', ' ') }} XOF</div>
            </div>
        </div>
    </section>

    <div class="layout">
        <section class="stack">
            <div class="card">
                <div class="summary-grid">
                    <div class="summary-box">
                        <div class="label">Date facture</div>
                        <div class="value" style="font-size:22px;">{{ $invoice->invoice_date?->format('d/m/Y') }}</div>
                    </div>
                    <div class="summary-box">
                        <div class="label">Echeance</div>
                        <div class="value" style="font-size:22px;">{{ $invoice->due_date?->format('d/m/Y') ?? 'Non renseignee' }}</div>
                    </div>
                    <div class="summary-box">
                        <div class="label">Client</div>
                        <div class="value" style="font-size:22px;">{{ $invoice->customer?->name }}</div>
                    </div>
                    <div class="summary-box">
                        <div class="label">Agence</div>
                        <div class="value" style="font-size:22px;">{{ $invoice->branch?->name ?? 'Agence principale' }}</div>
                    </div>
                </div>

                @if ($invoice->notes)
                    <div class="notice" style="margin-top:16px;">
                        <strong>Notes commerciales</strong>
                        <div style="margin-top:8px; line-height:1.6;">{{ $invoice->notes }}</div>
                    </div>
                @endif
            </div>

            @if ($portalAction && $portalAction->action_type === 'invoice_payment_notice')
                <div class="card">
                    @include('partials.portal-action-summary', ['portalAction' => $portalAction, 'title' => 'Dernier avis de reglement'])
                </div>
            @endif

            <div class="card">
                <h2 style="margin-top:0;">Lignes facture</h2>
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
                        @foreach ($invoice->items as $item)
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
                <h2 style="margin-top:0;">Declarer un reglement</h2>
                <div class="muted" style="margin-bottom:14px;">Choisis un canal terrain, lance l action mobile utile puis renvoie l avis de paiement a l equipe. Le reglement sera ensuite rapproche et valide dans l ERP.</div>

                @if (! empty($portal['payment_channels']))
                    <div class="stack" style="margin-bottom:16px;">
                        @foreach ($portal['payment_channels'] as $channel)
                            <div class="summary-box">
                                <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:flex-start;">
                                    <div>
                                        <strong>{{ $channel['label'] }}</strong>
                                        <div class="muted" style="margin-top:6px;">{{ $channel['target'] ?: 'Coordonnee en attente de configuration' }}</div>
                                    </div>
                                    @if ($channel['requires_reference'])
                                        <span class="badge badge-muted">Ref {{ $portal['payment_reference_hint'] }}</span>
                                    @endif
                                </div>
                                @if ($channel['instructions'])
                                    <div class="muted" style="margin-top:8px;">{{ $channel['instructions'] }}</div>
                                @endif
                                <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:12px;">
                                    <button
                                        type="button"
                                        class="button button-primary"
                                        data-prefill-payment-channel="{{ $channel['method'] }}"
                                        data-channel-label="{{ $channel['label'] }}"
                                        data-channel-target="{{ $channel['target'] }}"
                                        data-channel-reference="{{ $channel['prefill_reference'] }}"
                                        data-channel-note="{{ $channel['prefill_note'] }}"
                                    >Utiliser ce canal</button>
                                    <button type="button" class="button button-secondary" data-copy-text="{{ $channel['payment_message'] }}" data-copy-success="Message copie" data-copy-label="{{ $channel['copy_label'] }}">{{ $channel['copy_label'] }}</button>
                                    @if (! empty($channel['target_copy_text']))
                                        <button type="button" class="button button-secondary" data-copy-text="{{ $channel['target_copy_text'] }}" data-copy-success="Coordonnees copiees" data-copy-label="{{ $channel['target_copy_label'] }}">{{ $channel['target_copy_label'] }}</button>
                                    @endif
                                    @foreach ($channel['actions'] as $action)
                                        <a href="{{ $action['url'] }}" class="button {{ $action['style'] }}" target="_blank" rel="noopener">{{ $action['label'] }}</a>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($portal['can_notify_payment'])
                    @include('portal.sales._payment-notice-form', [
                        'actionUrl' => $portal['notify_url'],
                        'submitLabel' => 'Transmettre l avis de reglement',
                        'depositMethods' => $depositMethods,
                        'referenceHint' => $portal['payment_reference_hint'] ?? null,
                    ])
                @elseif ($invoice->payment_status === 'paid')
                    <div class="notice notice-success">
                        <strong>Facture deja reglee</strong>
                        <div class="muted" style="margin-top:6px;">Aucun nouvel avis de paiement n est necessaire.</div>
                    </div>
                @else
                    <div class="notice notice-warning">
                        <strong>Action indisponible</strong>
                        <div class="muted" style="margin-top:6px;">La facture doit d abord etre completement approuvee avant toute declaration de reglement.</div>
                    </div>
                @endif
            </div>

            <div class="card">
                <h2 style="margin-top:0;">Coordonnees</h2>
                <div class="stack">
                    <div>
                        <div class="muted">Entreprise</div>
                        <strong>{{ $invoice->company?->name }}</strong>
                    </div>
                    <div>
                        <div class="muted">Telephone</div>
                        <strong>{{ $invoice->company?->phone ?: 'Non renseigne' }}</strong>
                    </div>
                    <div>
                        <div class="muted">Client</div>
                        <strong>{{ $invoice->customer?->name }}</strong>
                    </div>
                </div>
            </div>
        </aside>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('click', function (event) {
    const trigger = event.target.closest('[data-prefill-payment-channel]');
    if (!trigger) {
        return;
    }

    const form = document.querySelector('[data-payment-notice-form]');
    if (!form) {
        return;
    }

    const methodInput = form.querySelector('[data-payment-method]');
    const referenceInput = form.querySelector('[data-payment-reference]');
    const noteInput = form.querySelector('[data-payment-note]');
    const amountInput = form.querySelector('[data-payment-amount]');
    const selectedChannelInput = form.querySelector('[data-payment-channel-input]');
    const hint = form.querySelector('[data-payment-channel-hint]');

    const method = trigger.getAttribute('data-prefill-payment-channel') || '';
    const label = trigger.getAttribute('data-channel-label') || method;
    const target = trigger.getAttribute('data-channel-target') || '';
    const reference = trigger.getAttribute('data-channel-reference') || '';
    const note = trigger.getAttribute('data-channel-note') || '';

    if (methodInput) {
        methodInput.value = method;
    }

    if (referenceInput && !referenceInput.value && reference) {
        referenceInput.value = reference;
    }

    if (noteInput && !noteInput.value && note) {
        noteInput.value = note;
    }

    if (selectedChannelInput) {
        selectedChannelInput.value = method;
    }

    if (hint) {
        hint.style.display = '';
        hint.innerHTML = '<strong>Canal terrain choisi</strong><div class="muted" style="margin-top:6px;">' + [label, target].filter(Boolean).join(' · ') + '</div>';
    }

    if (amountInput) {
        amountInput.focus();
        amountInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
});
</script>
@endpush
