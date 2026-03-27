<div class="card">
    <div class="form-grid">
        <div>
            <label for="name">Nom du rôle</label>
            <input id="name" type="text" name="name" value="{{ old('name', $role->name) }}" required>
        </div>
        <div>
            <label for="slug">Slug</label>
            <input id="slug" type="text" name="slug" value="{{ old('slug', $role->slug) }}">
            <div class="help">Laisser vide pour générer automatiquement.</div>
        </div>
        <div class="full">
            <label for="description">Description</label>
            <input id="description" type="text" name="description" value="{{ old('description', $role->description) }}">
        </div>
    </div>

    <div style="margin-top: 22px;">
        <h3>Permissions</h3>
        @foreach ($permissions as $module => $modulePermissions)
            <div style="margin-top: 18px;">
                <strong style="text-transform: capitalize;">{{ $module }}</strong>
                <div class="checkbox-grid" style="margin-top: 10px;">
                    @foreach ($modulePermissions as $permission)
                        <label class="checkbox-card checkbox-row">
                            <input type="checkbox" name="permissions[]" value="{{ $permission->id }}"
                                @checked(collect(old('permissions', $role->permissions?->pluck('id')->all() ?? []))->contains($permission->id))>
                            <span>
                                <strong>{{ $permission->name }}</strong>
                                <div class="muted">{{ $permission->slug }}</div>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <div class="actions">
        <a href="{{ route('roles.index') }}" class="button button-secondary">Annuler</a>
        <button type="submit" class="button button-primary">Enregistrer</button>
    </div>
</div>
