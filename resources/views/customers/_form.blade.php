@php
    $customerLabel = $businessVocabulary['client'] ?? 'Client';
@endphp

<div class="card">
    <div class="form-grid">
        <div>
            <label for="code">Code</label>
            <input id="code" type="text" name="code" value="{{ old('code', $partner->code) }}">
            <div class="help">Laisser vide pour génération automatique.</div>
        </div>
        <div>
            <label for="name">Nom</label>
            <input id="name" type="text" name="name" value="{{ old('name', $partner->name) }}" required>
            <div class="help">Nom principal du dossier {{ strtolower($customerLabel) }}.</div>
        </div>
        <div>
            <label for="phone">Téléphone</label>
            <input id="phone" type="text" name="phone" value="{{ old('phone', $partner->phone) }}">
        </div>
        <div>
            <label for="email">E-mail</label>
            <input id="email" type="email" name="email" value="{{ old('email', $partner->email) }}">
        </div>
        <div>
            <label for="city">Ville</label>
            <input id="city" type="text" name="city" value="{{ old('city', $partner->city) }}">
        </div>
        <div>
            <label for="nif">NIF</label>
            <input id="nif" type="text" name="nif" value="{{ old('nif', $partner->nif) }}">
        </div>
        <div>
            <label for="opening_balance">Solde initial</label>
            <input id="opening_balance" type="number" step="0.01" min="0" name="opening_balance" value="{{ old('opening_balance', $partner->opening_balance ?: 0) }}">
        </div>
        <div>
            <label for="payment_term_id">Condition de paiement</label>
            <select id="payment_term_id" name="payment_term_id">
                <option value="">Aucune</option>
                @foreach ($paymentTerms as $paymentTerm)
                    <option value="{{ $paymentTerm->id }}" @selected((string) old('payment_term_id', $partner->payment_term_id) === (string) $paymentTerm->id)>{{ $paymentTerm->name }} · {{ $paymentTerm->days }} j</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="price_list_id">Liste de prix</label>
            <select id="price_list_id" name="price_list_id">
                <option value="">Aucune</option>
                @foreach ($priceLists as $priceList)
                    <option value="{{ $priceList->id }}" @selected((string) old('price_list_id', $partner->price_list_id) === (string) $priceList->id)>{{ $priceList->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="is_active">Statut</label>
            <select id="is_active" name="is_active">
                <option value="1" @selected(old('is_active', $partner->is_active ?? true))>Actif</option>
                <option value="0" @selected((string) old('is_active', $partner->is_active ?? true) === '0')>Inactif</option>
            </select>
        </div>
        <div class="full">
            <label for="address">Adresse</label>
            <textarea id="address" name="address">{{ old('address', $partner->address) }}</textarea>
        </div>
        <div class="full">
            <label for="notes">Notes</label>
            <textarea id="notes" name="notes" placeholder="Informations utiles sur ce dossier {{ strtolower($customerLabel) }}">{{ old('notes', $partner->notes) }}</textarea>
        </div>
    </div>

    <div class="actions">
        <a href="{{ route('customers.index') }}" class="button button-secondary">Annuler</a>
        <button type="submit" class="button button-primary">Enregistrer</button>
    </div>
</div>
