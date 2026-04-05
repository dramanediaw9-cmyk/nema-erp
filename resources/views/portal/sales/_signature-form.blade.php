<form method="POST" action="{{ $actionUrl }}" class="stack" autocomplete="on">
    @csrf

    <div class="form-grid">
        <div>
            <label for="signer_name">Nom du signataire</label>
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
            <input id="signer_title" name="signer_title" type="text" value="{{ old('signer_title') }}" placeholder="Gerant, acheteur, DG..." autocomplete="organization-title" autocapitalize="words" enterkeyhint="next">
            @error('signer_title')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <div>
            <label for="signer_company">Societe / structure</label>
            <input id="signer_company" name="signer_company" type="text" value="{{ old('signer_company') }}" placeholder="Entreprise cliente" autocomplete="organization" autocapitalize="words" enterkeyhint="next">
            @error('signer_company')<div class="field-error">{{ $message }}</div>@enderror
        </div>
    </div>

    <div>
        <label for="signer_note">Commentaire client</label>
        <textarea id="signer_note" name="signer_note" placeholder="Precision de validation, remarque livraison, confirmation commerciale...">{{ old('signer_note') }}</textarea>
        @error('signer_note')<div class="field-error">{{ $message }}</div>@enderror
    </div>

    @include('portal.sales._signature-pad')

    <div class="notice">
        <strong>Acompte annonce</strong>
        <div class="muted" style="margin-top:8px;">Optionnel. Le client peut annoncer un premier reglement et sa reference. L equipe le verra dans l ERP avant validation comptable.</div>

        <div class="form-grid" style="margin-top:12px;">
            <div>
                <label for="deposit_amount">Montant</label>
                <input id="deposit_amount" name="deposit_amount" type="number" min="0" step="0.01" value="{{ old('deposit_amount') }}" placeholder="0" inputmode="decimal" enterkeyhint="next">
                @error('deposit_amount')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="deposit_method">Mode de paiement</label>
                <select id="deposit_method" name="deposit_method">
                    <option value="">Aucun</option>
                    @foreach ($depositMethods as $value => $label)
                        <option value="{{ $value }}" @selected(old('deposit_method') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('deposit_method')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="deposit_reference">Reference</label>
                <input id="deposit_reference" name="deposit_reference" type="text" value="{{ old('deposit_reference') }}" placeholder="Numero transaction, cheque, virement..." autocapitalize="characters" spellcheck="false" enterkeyhint="next">
                @error('deposit_reference')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="deposit_expected_at">Date prevue</label>
                <input id="deposit_expected_at" name="deposit_expected_at" type="date" value="{{ old('deposit_expected_at') }}">
                @error('deposit_expected_at')<div class="field-error">{{ $message }}</div>@enderror
            </div>
        </div>

        <div style="margin-top:12px;">
            <label for="deposit_note">Note de paiement</label>
            <textarea id="deposit_note" name="deposit_note" placeholder="Exemple : acompte deja envoye via Wave, confirmation bancaire en attente...">{{ old('deposit_note') }}</textarea>
            @error('deposit_note')<div class="field-error">{{ $message }}</div>@enderror
        </div>
    </div>

    <label style="display:flex; gap:10px; align-items:flex-start; font-size:14px; line-height:1.5; text-transform:none; letter-spacing:0; font-weight:600;">
        <input type="checkbox" name="accepted_terms" value="1" @checked(old('accepted_terms')) style="width:auto; margin-top:3px;">
        <span>Je confirme que j accepte {{ $documentLabel }} et que les informations de signature saisies sont exactes.</span>
    </label>
    @error('accepted_terms')<div class="field-error">{{ $message }}</div>@enderror

    <button type="submit" class="button button-primary" style="width:100%;">{{ $submitLabel }}</button>
</form>
