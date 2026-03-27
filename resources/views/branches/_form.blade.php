<div class="card">
    <div class="form-grid">
        <div>
            <label for="name">Nom de l'agence</label>
            <input id="name" type="text" name="name" value="{{ old('name', $branch->name) }}" required>
        </div>
        <div>
            <label for="code">Code agence</label>
            <input id="code" type="text" name="code" value="{{ old('code', $branch->code) }}" required>
        </div>
        <div>
            <label for="city">Ville</label>
            <input id="city" type="text" name="city" value="{{ old('city', $branch->city) }}">
        </div>
        <div>
            <label for="is_active">Statut</label>
            <select id="is_active" name="is_active">
                <option value="1" @selected(old('is_active', $branch->is_active ?? true))>Active</option>
                <option value="0" @selected((string) old('is_active', $branch->is_active ?? true) === '0')>Inactive</option>
            </select>
        </div>
        <div class="full">
            <label for="address">Adresse</label>
            <textarea id="address" name="address">{{ old('address', $branch->address) }}</textarea>
        </div>
        <div class="full">
            <label for="is_default">Définir comme agence par défaut</label>
            <select id="is_default" name="is_default">
                <option value="0" @selected((string) old('is_default', $branch->is_default ?? false) === '0')>Non</option>
                <option value="1" @selected(old('is_default', $branch->is_default ?? false))>Oui</option>
            </select>
        </div>
    </div>
    <div class="actions">
        <a href="{{ route('branches.index') }}" class="button button-secondary">Annuler</a>
        <button type="submit" class="button button-primary">Enregistrer</button>
    </div>
</div>
