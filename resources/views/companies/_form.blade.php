<div class="card">
    <div class="form-grid">
        <div>
            <label for="name">Nom commercial</label>
            <input id="name" type="text" name="name" value="{{ old('name', $company->name) }}" required>
        </div>
        <div>
            <label for="legal_name">Raison sociale</label>
            <input id="legal_name" type="text" name="legal_name" value="{{ old('legal_name', $company->legal_name) }}">
        </div>
        <div>
            <label for="nif">NIF</label>
            <input id="nif" type="text" name="nif" value="{{ old('nif', $company->nif) }}">
        </div>
        <div>
            <label for="rccm">RCCM</label>
            <input id="rccm" type="text" name="rccm" value="{{ old('rccm', $company->rccm) }}">
        </div>
        <div>
            <label for="phone">Téléphone</label>
            <input id="phone" type="text" name="phone" value="{{ old('phone', $company->phone) }}">
        </div>
        <div>
            <label for="email">E-mail</label>
            <input id="email" type="email" name="email" value="{{ old('email', $company->email) }}">
        </div>
        <div>
            <label for="currency_code">Devise</label>
            <input id="currency_code" type="text" name="currency_code" value="{{ old('currency_code', $company->currency_code ?: 'XOF') }}" maxlength="3" required>
            <div class="help">`XOF` par défaut pour le Mali.</div>
        </div>
        <div>
            <label for="is_active">Statut</label>
            <select id="is_active" name="is_active">
                <option value="1" @selected(old('is_active', $company->is_active ?? true))>Active</option>
                <option value="0" @selected((string) old('is_active', $company->is_active ?? true) === '0')>Inactive</option>
            </select>
        </div>
        <div class="full">
            <label for="address">Adresse</label>
            <textarea id="address" name="address">{{ old('address', $company->address) }}</textarea>
        </div>
    </div>

    <div class="actions">
        <a href="{{ route('companies.index') }}" class="button button-secondary">Annuler</a>
        <button type="submit" class="button button-primary">Enregistrer</button>
    </div>
</div>
