@php
    $isPaymentNotice = $portalAction->action_type === 'invoice_payment_notice';
    $amountLabel = $isPaymentNotice ? 'Paiement annonce' : 'Acompte annonce';
    $detailLabel = $isPaymentNotice ? 'Detail du reglement annonce' : 'Detail de paiement annonce';
@endphp
<div class="notice" style="padding:16px;">
    <strong>{{ $title ?? 'Signature client portail' }}</strong>
    <div class="summary-grid" style="margin-top:12px; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));">
        <div class="summary-box">
            <div class="label">Signataire</div>
            <div class="value" style="font-size:20px;">{{ $portalAction->signer_name }}</div>
            @if ($portalAction->signer_title || $portalAction->signer_company)
                <div class="muted" style="margin-top:8px;">{{ collect([$portalAction->signer_title, $portalAction->signer_company])->filter()->implode(' · ') }}</div>
            @endif
        </div>
        <div class="summary-box">
            <div class="label">Signe le</div>
            <div class="value" style="font-size:20px;">{{ $portalAction->signed_at?->format('d/m/Y H:i') }}</div>
            <div class="muted" style="margin-top:8px;">Empreinte {{ $portalAction->signatureCode() }}</div>
        </div>
        <div class="summary-box">
            <div class="label">Contact</div>
            <div class="value" style="font-size:20px;">{{ $portalAction->signer_phone ?: 'Non renseigne' }}</div>
            <div class="muted" style="margin-top:8px;">Canal portail client</div>
        </div>
        <div class="summary-box">
            <div class="label">{{ $amountLabel }}</div>
            <div class="value" style="font-size:20px;">{{ $portalAction->deposit_amount ? number_format((float) $portalAction->deposit_amount, 0, ',', ' ').' XOF' : 'Aucun' }}</div>
            @if ($portalAction->deposit_amount)
                <div class="muted" style="margin-top:8px;">{{ collect([$portalAction->depositMethodLabel(), $portalAction->deposit_reference])->filter()->implode(' · ') }}</div>
                @if (! empty(data_get($portalAction->properties, 'selected_payment_channel_label')))
                    <div class="help" style="margin-top:6px;">Canal terrain choisi : {{ data_get($portalAction->properties, 'selected_payment_channel_label') }}</div>
                @endif
            @endif
        </div>
    </div>

    @if ($portalAction->signature_image_data_url)
        <div class="signature-preview">
            <strong>Signature graphique</strong>
            <div class="muted" style="margin-top:6px;">Capture tactile conservee avec la signature nominative.</div>
            <img src="{{ $portalAction->signature_image_data_url }}" alt="Signature graphique {{ $portalAction->signer_name }}">
        </div>
    @endif

    @if ($portalAction->deposit_amount && ($portalAction->deposit_expected_at || $portalAction->deposit_note))
        <div class="notice" style="margin-top:12px;">
            <strong>{{ $detailLabel }}</strong>
            <div class="muted" style="margin-top:8px; line-height:1.6;">
                @if ($portalAction->deposit_expected_at)
                    <div>Date prevue : {{ $portalAction->deposit_expected_at->format('d/m/Y') }}</div>
                @endif
                @if ($portalAction->deposit_note)
                    <div style="margin-top:6px;">{{ $portalAction->deposit_note }}</div>
                @endif
            </div>
        </div>
    @endif

    @if ($portalAction->signer_note)
        <div class="notice" style="margin-top:12px;">
            <strong>Message du client</strong>
            <div class="muted" style="margin-top:8px; line-height:1.6;">{{ $portalAction->signer_note }}</div>
        </div>
    @endif
</div>

