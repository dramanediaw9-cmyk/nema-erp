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
        <div class="full">
            <label for="description">Description</label>
            <textarea id="description" name="description">{{ old('description', $category->description) }}</textarea>
        </div>
    </div>

    <div class="actions">
        <a href="{{ route('categories.index') }}" class="button button-secondary">Annuler</a>
        <button type="submit" class="button button-primary">Enregistrer</button>
    </div>
</div>
