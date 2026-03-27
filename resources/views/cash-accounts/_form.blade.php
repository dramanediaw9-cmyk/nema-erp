<div class="card">
    <div class="form-grid">
        <div>
            <label for="name">Nom du compte</label>
            <input id="name" type="text" name="name" value="{{ old('name', $account->name) }}" required>
        </div>
        <div>
            <label for="type">Type</label>
            <select id="type" name="type" required>
                <option value="cash" @selected(old('type', $account->type) === 'cash')>Caisse</option>
                <option value="bank" @selected(old('type', $account->type) === 'bank')>Banque</option>
                <option value="mobile_money" @selected(old('type', $account->type) === 'mobile_money')>Mobile money</option>
            </select>
        </div>
        <div>
            <label for="branch_id">Agence</label>
            <select id="branch_id" name="branch_id">
                <option value="">Toutes agences</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected((string) old('branch_id', $account->branch_id) === (string) $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="account_number">Numero ou reference</label>
            <input id="account_number" type="text" name="account_number" value="{{ old('account_number', $account->account_number) }}">
        </div>
        <div>
            <label for="opening_balance">Solde initial</label>
            <input id="opening_balance" type="number" step="0.01" min="0" name="opening_balance" value="{{ old('opening_balance', $account->opening_balance ?: 0) }}" required>
        </div>
        <div>
            <label for="is_active">Statut</label>
            <select id="is_active" name="is_active">
                <option value="1" @selected(old('is_active', $account->is_active ?? true))>Actif</option>
                <option value="0" @selected((string) old('is_active', $account->is_active ?? true) === '0')>Inactif</option>
            </select>
        </div>
    </div>

    <div class="actions">
        <a href="{{ route('cash-accounts.index') }}" class="button button-secondary">Annuler</a>
        <button type="submit" class="button button-primary">Enregistrer</button>
    </div>
</div>
