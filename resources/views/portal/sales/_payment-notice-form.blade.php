<form method="POST" action="{{ $actionUrl }}" class="stack" data-payment-notice-form autocomplete="on">
    @csrf

    <input type="hidden" name="selected_payment_channel" value="{{ old('selected_payment_channel') }}" data-payment-channel-input>

    <div class="form-grid">
        <div>
            <label for="signer_name">Nom du declarant</label>
            <input id="signer_name" name="signer_name" type="text" value="{{ old('signer_name') }}" placeholder="Nom complet" required autocomplete="name" autocapitalize="words" enterkeyhint="next">
            @error('signer_name')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <div>
            <label for="signer_phone">Telephone</label>
            <input id="signer_phone" name="signer_phone" type="tel" value="{{ old('signer_phone') }}" placeholder="+223 ..." autocomplete="tel" inputmode="tel" enterkeyhint="next">
            @error('signer_phone')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <div>
            <label for="signer_title">Fonction</label>
            <input id="signer_title" name="signer_title" type="text" value="{{ old('signer_title') }}" placeholder="Gerant, comptable, DG..." autocomplete="organization-title" autocapitalize="words" enterkeyhint="next">
            @error('signer_title')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <div>
            <label for="signer_company">Societe / structure</label>
            <input id="signer_company" name="signer_company" type="text" value="{{ old('signer_company') }}" placeholder="Entreprise cliente" autocomplete="organization" autocapitalize="words" enterkeyhint="next">
            @error('signer_company')<div class="field-error">{{ $message }}</div>@enderror
        </div>
    </div>

    @include('portal.sales._signature-pad')

    <div class="notice">
        <strong>Reglement annonce</strong>
        <div class="muted" style="margin-top:8px;">Renseigne le paiement envoye ou programme. L equipe rapprochera ensuite l encaissement dans l ERP.</div>

        <div class="notice" style="margin-top:12px; display:none;" data-payment-channel-hint></div>
        @error('selected_payment_channel')<div class="field-error">{{ $message }}</div>@enderror

        <div class="form-grid" style="margin-top:12px;">
            <div>
                <label for="deposit_amount">Montant regle</label>
                <input id="deposit_amount" name="deposit_amount" type="number" min="0.01" step="0.01" value="{{ old('deposit_amount') }}" placeholder="0" required data-payment-amount inputmode="decimal" enterkeyhint="next">
                @error('deposit_amount')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="deposit_method">Mode de paiement</label>
                <select id="deposit_method" name="deposit_method" required data-payment-method>
                    <option value="">Choisir</option>
                    @foreach ($depositMethods as $value => $label)
                        <option value="{{ $value }}" @selected(old('deposit_method') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('deposit_method')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="deposit_reference">Reference</label>
                <input id="deposit_reference" name="deposit_reference" type="text" value="{{ old('deposit_reference', $referenceHint ?? '') }}" placeholder="Numero transaction, cheque, virement..." data-payment-reference autocapitalize="characters" spellcheck="false" enterkeyhint="next">
                @if (! empty($referenceHint))
                    <div class="muted" style="margin-top:6px;">Reference conseillee : {{ $referenceHint }}</div>
                @endif
                @error('deposit_reference')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="deposit_expected_at">Date du reglement</label>
                <input id="deposit_expected_at" name="deposit_expected_at" type="date" value="{{ old('deposit_expected_at', now()->format('Y-m-d')) }}">
                @error('deposit_expected_at')<div class="field-error">{{ $message }}</div>@enderror
            </div>
        </div>

        <div style="margin-top:12px;">
            <label for="deposit_note">Precision de paiement</label>
            <textarea id="deposit_note" name="deposit_note" placeholder="Exemple : Wave envoye, capture disponible, virement emis depuis BDM..." data-payment-note>{{ old('deposit_note') }}</textarea>
            @error('deposit_note')<div class="field-error">{{ $message }}</div>@enderror
        </div>
    </div>

    <div>
        <label for="signer_note">Commentaire complementaire</label>
        <textarea id="signer_note" name="signer_note" placeholder="Precision utile pour l equipe commerciale ou comptable...">{{ old('signer_note') }}</textarea>
        @error('signer_note')<div class="field-error">{{ $message }}</div>@enderror
    </div>

    <label style="display:flex; gap:10px; align-items:flex-start; font-size:14px; line-height:1.5; text-transform:none; letter-spacing:0; font-weight:600;">
        <input type="checkbox" name="accepted_terms" value="1" @checked(old('accepted_terms')) style="width:auto; margin-top:3px;">
        <span>Je confirme que ce reglement a ete envoye ou programme pour cette facture et que les informations saisies sont exactes.</span>
    </label>
    @error('accepted_terms')<div class="field-error">{{ $message }}</div>@enderror

    <button type="submit" class="button button-primary" style="width:100%;">{{ $submitLabel }}</button>
</form>
