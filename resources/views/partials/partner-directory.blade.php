<section class="card" style="margin-top:20px;">
    <h2 style="margin-top:0;">Contacts et coordonnees</h2>
    <div class="split">
        <section class="card" style="padding:16px;">
            <h3 style="margin-top:0;">Contacts</h3>
            @forelse ($partner->contacts as $contact)
                <div style="padding-bottom:12px; border-bottom:1px solid #efe4d3; margin-bottom:12px;">
                    <div style="display:flex; justify-content:space-between; gap:12px; align-items:flex-start; flex-wrap:wrap;">
                        <div>
                            <strong>{{ $contact->name }}</strong>
                            <div class="muted">{{ $contact->role ?: 'Role non renseigne' }}</div>
                            <div class="muted">{{ $contact->phone ?: 'Telephone non renseigne' }} · {{ $contact->email ?: 'Email non renseigne' }}</div>
                            @if ($contact->is_primary)
                                <div class="chip-row"><span class="badge badge-success">Principal</span></div>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('partners.contacts.destroy', [$partner, $contact]) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="button button-danger">Supprimer</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="muted">Aucun contact enregistre.</p>
            @endforelse
            <form method="POST" action="{{ route('partners.contacts.store', $partner) }}">
                @csrf
                <div class="form-grid">
                    <div><label>Nom</label><input type="text" name="name" required></div>
                    <div><label>Fonction</label><input type="text" name="role"></div>
                    <div><label>Telephone</label><input type="text" name="phone"></div>
                    <div><label>Email</label><input type="email" name="email"></div>
                    <div class="checkbox-card"><label style="display:flex; gap:10px; align-items:center; margin:0;"><input type="checkbox" name="is_primary" value="1"> Contact principal</label></div>
                </div>
                <div class="actions"><button type="submit" class="button button-primary">Ajouter un contact</button></div>
            </form>
        </section>

        <section class="card" style="padding:16px;">
            <h3 style="margin-top:0;">Adresses</h3>
            @forelse ($partner->addresses as $address)
                <div style="padding-bottom:12px; border-bottom:1px solid #efe4d3; margin-bottom:12px;">
                    <div style="display:flex; justify-content:space-between; gap:12px; align-items:flex-start; flex-wrap:wrap;">
                        <div>
                            <strong>{{ $address->label ?: 'Adresse '.$address->type }}</strong>
                            <div class="muted">{{ $address->address_line }}</div>
                            <div class="muted">{{ $address->city ?: 'Ville non renseignee' }} · {{ $address->country }}</div>
                            @if ($address->is_primary)
                                <div class="chip-row"><span class="badge badge-success">Principale</span></div>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('partners.addresses.destroy', [$partner, $address]) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="button button-danger">Supprimer</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="muted">Aucune adresse enregistree.</p>
            @endforelse
            <form method="POST" action="{{ route('partners.addresses.store', $partner) }}">
                @csrf
                <div class="form-grid">
                    <div><label>Libelle</label><input type="text" name="label"></div>
                    <div>
                        <label>Type</label>
                        <select name="type"><option value="billing">Facturation</option><option value="shipping">Livraison</option><option value="other">Autre</option></select>
                    </div>
                    <div class="full"><label>Adresse</label><textarea name="address_line" required></textarea></div>
                    <div><label>Ville</label><input type="text" name="city"></div>
                    <div><label>Pays</label><input type="text" name="country" value="Mali" required></div>
                    <div class="checkbox-card"><label style="display:flex; gap:10px; align-items:center; margin:0;"><input type="checkbox" name="is_primary" value="1"> Adresse principale</label></div>
                </div>
                <div class="actions"><button type="submit" class="button button-primary">Ajouter une adresse</button></div>
            </form>
        </section>
    </div>

    <div class="split" style="margin-top:18px;">
        <section class="card" style="padding:16px;">
            <h3 style="margin-top:0;">Comptes bancaires</h3>
            @forelse ($partner->bankAccounts as $bankAccount)
                <div style="padding-bottom:12px; border-bottom:1px solid #efe4d3; margin-bottom:12px;">
                    <div style="display:flex; justify-content:space-between; gap:12px; align-items:flex-start; flex-wrap:wrap;">
                        <div>
                            <strong>{{ $bankAccount->bank_name }}</strong>
                            <div class="muted">{{ $bankAccount->account_name ?: 'Titulaire non renseigne' }}</div>
                            <div class="muted">{{ $bankAccount->account_number }}</div>
                        </div>
                        <form method="POST" action="{{ route('partners.bank-accounts.destroy', [$partner, $bankAccount]) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="button button-danger">Supprimer</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="muted">Aucun compte bancaire enregistre.</p>
            @endforelse
            <form method="POST" action="{{ route('partners.bank-accounts.store', $partner) }}">
                @csrf
                <div class="form-grid">
                    <div><label>Banque</label><input type="text" name="bank_name" required></div>
                    <div><label>Titulaire</label><input type="text" name="account_name"></div>
                    <div><label>Numero</label><input type="text" name="account_number" required></div>
                    <div><label>IBAN</label><input type="text" name="iban"></div>
                    <div><label>SWIFT</label><input type="text" name="swift_code"></div>
                    <div class="checkbox-card"><label style="display:flex; gap:10px; align-items:center; margin:0;"><input type="checkbox" name="is_primary" value="1"> Compte principal</label></div>
                </div>
                <div class="actions"><button type="submit" class="button button-primary">Ajouter un compte bancaire</button></div>
            </form>
        </section>

        <section class="card" style="padding:16px;">
            <h3 style="margin-top:0;">Wallets mobile money</h3>
            @forelse ($partner->mobileWallets as $wallet)
                <div style="padding-bottom:12px; border-bottom:1px solid #efe4d3; margin-bottom:12px;">
                    <div style="display:flex; justify-content:space-between; gap:12px; align-items:flex-start; flex-wrap:wrap;">
                        <div>
                            <strong>{{ $wallet->provider }}</strong>
                            <div class="muted">{{ $wallet->wallet_number }}</div>
                            <div class="muted">{{ $wallet->account_name ?: 'Nom du compte non renseigne' }}</div>
                        </div>
                        <form method="POST" action="{{ route('partners.wallets.destroy', [$partner, $wallet]) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="button button-danger">Supprimer</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="muted">Aucun wallet enregistre.</p>
            @endforelse
            <form method="POST" action="{{ route('partners.wallets.store', $partner) }}">
                @csrf
                <div class="form-grid">
                    <div><label>Operateur</label><input type="text" name="provider" placeholder="Orange Money / Wave / Moov Money" required></div>
                    <div><label>Numero</label><input type="text" name="wallet_number" required></div>
                    <div><label>Nom du compte</label><input type="text" name="account_name"></div>
                    <div class="checkbox-card"><label style="display:flex; gap:10px; align-items:center; margin:0;"><input type="checkbox" name="is_primary" value="1"> Wallet principal</label></div>
                </div>
                <div class="actions"><button type="submit" class="button button-primary">Ajouter un wallet</button></div>
            </form>
        </section>
    </div>
</section>
