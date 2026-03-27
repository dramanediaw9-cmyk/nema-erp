<div class="card">
    <div class="form-grid">
        <div>
            <label for="name">Nom</label>
            <input id="name" type="text" name="name" value="{{ old('name', $category->name) }}" required>
        </div>
        <div>
            <label for="is_active">Statut</label>
            <select id="is_active" name="is_active">
                <option value="1" @selected(old('is_active', $category->is_active ?? true))>Active</option>
                <option value="0" @selected((string) old('is_active', $category->is_active ?? true) === '0')>Inactive</option>
            </select>
        </div>
        <div>
            <label for="default_account_code">Compte comptable</label>
            <input id="default_account_code" type="text" name="default_account_code" value="{{ old('default_account_code', $category->default_account_code) }}" placeholder="Ex: 606300">
            <div class="help">Code utilise pour generer l ecriture comptable de la depense.</div>
        </div>
        <div class="full">
            <label for="description">Description</label>
            <textarea id="description" name="description">{{ old('description', $category->description) }}</textarea>
        </div>
    </div>

    <div class="actions">
        <a href="{{ route('expense-categories.index') }}" class="button button-secondary">Annuler</a>
        <button type="submit" class="button button-primary">Enregistrer</button>
    </div>
</div>
