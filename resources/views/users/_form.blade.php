<div class="card">
    <div class="form-grid">
        <div>
            <label for="name">Nom complet</label>
            <input id="name" type="text" name="name" value="{{ old('name', $userModel->name) }}" required>
        </div>
        <div>
            <label for="phone">Téléphone</label>
            <input id="phone" type="text" name="phone" value="{{ old('phone', $userModel->phone) }}">
        </div>
        <div>
            <label for="email">E-mail</label>
            <input id="email" type="email" name="email" value="{{ old('email', $userModel->email) }}" required>
        </div>
        <div>
            <label for="branch_id">Agence</label>
            <select id="branch_id" name="branch_id" required>
                <option value="">Sélectionner</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected((string) old('branch_id', $userModel->branch_id) === (string) $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="password">Mot de passe {{ $userModel->exists ? '(laisser vide pour conserver)' : '' }}</label>
            <input id="password" type="password" name="password" {{ $userModel->exists ? '' : 'required' }}>
        </div>
        <div>
            <label for="password_confirmation">Confirmation mot de passe</label>
            <input id="password_confirmation" type="password" name="password_confirmation" {{ $userModel->exists ? '' : 'required' }}>
        </div>
        <div class="full">
            <label for="is_active">Statut</label>
            <select id="is_active" name="is_active">
                <option value="1" @selected(old('is_active', $userModel->is_active ?? true))>Actif</option>
                <option value="0" @selected((string) old('is_active', $userModel->is_active ?? true) === '0')>Inactif</option>
            </select>
        </div>
    </div>

    <div style="margin-top: 22px;">
        <h3>Rôles</h3>
        <div class="checkbox-grid">
            @foreach ($roles as $role)
                <label class="checkbox-card checkbox-row">
                    <input type="checkbox" name="roles[]" value="{{ $role->id }}" @checked(collect(old('roles', $userModel->roles?->pluck('id')->all() ?? []))->contains($role->id))>
                    <span>
                        <strong>{{ $role->name }}</strong>
                        <div class="muted">{{ $role->description ?: $role->slug }}</div>
                    </span>
                </label>
            @endforeach
        </div>
    </div>

    <div class="actions">
        <a href="{{ route('users.index') }}" class="button button-secondary">Annuler</a>
        <button type="submit" class="button button-primary">Enregistrer</button>
    </div>
</div>
