@extends('layouts.app')

@section('title', 'Parametres - Nema ERP')
@section('page-title', 'Parametres societe')

@section('content')
    @if (session('generated_api_token'))
        <div class="alert alert-success">
            <strong>Jeton API genere :</strong>
            <code style="display:block; margin-top:8px; word-break:break-all;">{{ session('generated_api_token') }}</code>
            <div class="help" style="margin-top:8px;">Copie ce jeton maintenant. Il n apparaitra plus ensuite.</div>
        </div>
    @endif

    <div class="split">
        <section class="card">
            <h2 style="margin-top:0;">Profil societe</h2>
            <form method="POST" action="{{ route('settings.company.update') }}">
                @csrf
                @method('PUT')
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
                        <label for="phone">Telephone</label>
                        <input id="phone" type="text" name="phone" value="{{ old('phone', $company->phone) }}">
                    </div>
                    <div>
                        <label for="email">E-mail</label>
                        <input id="email" type="email" name="email" value="{{ old('email', $company->email) }}">
                    </div>
                    <div>
                        <label for="currency_code">Devise</label>
                        <input id="currency_code" type="text" name="currency_code" value="{{ old('currency_code', $company->currency_code) }}" required>
                    </div>
                    <div>
                        <label for="country">Pays</label>
                        <input id="country" type="text" name="country" value="{{ old('country', $general->value['country'] ?? 'Mali') }}" required>
                    </div>
                    <div>
                        <label for="timezone">Fuseau horaire</label>
                        <input id="timezone" type="text" name="timezone" value="{{ old('timezone', $general->value['timezone'] ?? 'Africa/Bamako') }}" required>
                    </div>
                    <div>
                        <label for="locale">Langue</label>
                        <input id="locale" type="text" name="locale" value="{{ old('locale', $general->value['locale'] ?? 'fr') }}" required>
                    </div>
                    <div class="full">
                        <label for="address">Adresse</label>
                        <textarea id="address" name="address">{{ old('address', $company->address) }}</textarea>
                    </div>
                </div>
                <div class="actions">
                    <button type="submit" class="button button-primary">Enregistrer le profil</button>
                </div>
            </form>
        </section>

        <section class="card">
            <h2 style="margin-top:0;">Sequences documents</h2>
            <div class="help" style="margin-bottom:16px;">Placeholders disponibles dans les prefixes : <strong>{BRANCH}</strong>, <strong>{YEAR}</strong>, <strong>{YY}</strong>, <strong>{MONTH}</strong>, <strong>{JOURNAL}</strong>.</div>
            <form method="POST" action="{{ route('settings.sequences.update') }}">
                @csrf
                @method('PUT')
                <div class="grid">
                    @foreach ($sequences as $index => $sequence)
                        <div class="card" style="padding:16px;">
                            <input type="hidden" name="sequences[{{ $index }}][id]" value="{{ $sequence->id }}">
                            <div style="font-weight:700; margin-bottom: 12px;">{{ str($sequence->document_type)->replace('_', ' ')->title() }}</div>
                            <div class="form-grid" style="grid-template-columns: repeat(3, minmax(0, 1fr));">
                                <div>
                                    <label>Prefixe</label>
                                    <input type="text" name="sequences[{{ $index }}][prefix]" value="{{ old('sequences.'.$index.'.prefix', $sequence->prefix) }}" required>
                                </div>
                                <div>
                                    <label>Prochain numero</label>
                                    <input type="number" min="1" name="sequences[{{ $index }}][next_number]" value="{{ old('sequences.'.$index.'.next_number', $sequence->next_number) }}" required>
                                </div>
                                <div>
                                    <label>Padding</label>
                                    <input type="number" min="3" max="10" name="sequences[{{ $index }}][padding]" value="{{ old('sequences.'.$index.'.padding', $sequence->padding) }}" required>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="actions">
                    <button type="submit" class="button button-primary">Mettre a jour les sequences</button>
                </div>
            </form>
        </section>
    </div>

    <div class="split" style="margin-top:18px;">
        <section class="card">
            <h2 style="margin-top:0;">Workflow d approbation</h2>
            <div class="help" style="margin-bottom:16px;">Les roles restent fixes dans cette version : validation operationnelle puis direction. Tu ajustes ici les seuils qui declenchent la deuxieme etape.</div>
            <form method="POST" action="{{ route('settings.approvals.update') }}">
                @csrf
                @method('PUT')
                <div class="grid">
                    @foreach (['sales' => 'Ventes', 'purchases' => 'Achats', 'expenses' => 'Depenses'] as $key => $label)
                        <div class="card" style="padding:16px;">
                            <div style="font-weight:700; margin-bottom:12px;">{{ $label }}</div>
                            <div class="form-grid" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
                                <div>
                                    <label>Seuil double validation</label>
                                    <input type="number" min="0" step="1" name="workflows[{{ $key }}][step2_threshold]" value="{{ old('workflows.'.$key.'.step2_threshold', $approvalWorkflows[$key]['step2_threshold']) }}" required>
                                </div>
                                <div>
                                    <label>Seuil direction obligatoire</label>
                                    <input type="number" min="0" step="1" name="workflows[{{ $key }}][critical_threshold]" value="{{ old('workflows.'.$key.'.critical_threshold', $approvalWorkflows[$key]['critical_threshold']) }}" required>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="actions">
                    <button type="submit" class="button button-primary">Mettre a jour le workflow</button>
                </div>
            </form>
        </section>

        <section class="card">
            <h2 style="margin-top:0;">Notifications externes d approbation</h2>
            <form method="POST" action="{{ route('settings.approval-notifications.update') }}">
                @csrf
                @method('PUT')
                <div class="grid">
                    <div class="checkbox-card">
                        <div class="checkbox-row">
                            <input id="channel-email-enabled" type="checkbox" name="channels[email][enabled]" value="1" @checked(old('channels.email.enabled', $approvalNotificationChannels['email']['enabled']))>
                            <div style="width:100%;">
                                <label for="channel-email-enabled" style="margin:0 0 8px;">Activer les emails</label>
                                <textarea name="channels[email][copy_to]" placeholder="copie@entreprise.ml, dg@entreprise.ml">{{ old('channels.email.copy_to', $approvalNotificationChannels['email']['copy_to']) }}</textarea>
                            </div>
                        </div>
                    </div>
                    <div class="checkbox-card">
                        <div class="checkbox-row">
                            <input id="channel-whatsapp-enabled" type="checkbox" name="channels[whatsapp][enabled]" value="1" @checked(old('channels.whatsapp.enabled', $approvalNotificationChannels['whatsapp']['enabled']))>
                            <div style="width:100%;">
                                <label for="channel-whatsapp-enabled" style="margin:0 0 8px;">Activer WhatsApp</label>
                                <textarea name="channels[whatsapp][copy_to]" placeholder="+22370000001, +22370000002">{{ old('channels.whatsapp.copy_to', $approvalNotificationChannels['whatsapp']['copy_to']) }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="actions">
                    <button type="submit" class="button button-primary">Mettre a jour les notifications</button>
                </div>
            </form>
        </section>
    </div>

    <div class="split" style="margin-top:18px;">
        <section class="card">
            <h2 style="margin-top:0;">Conditions de paiement</h2>
            <div class="grid" style="margin-bottom:16px;">
                @foreach ($paymentTerms as $term)
                    <div class="summary-box">
                        <strong>{{ $term->name }}</strong>
                        <div class="muted" style="margin-top:6px;">{{ $term->days }} jour(s) · {{ $term->code }}</div>
                        @if ($term->description)
                            <div class="help" style="margin-top:8px;">{{ $term->description }}</div>
                        @endif
                        @if ($term->is_default)
                            <div class="chip-row"><span class="badge badge-success">Par defaut</span></div>
                        @endif
                    </div>
                @endforeach
            </div>
            <form method="POST" action="{{ route('settings.payment-terms.store') }}">
                @csrf
                <div class="form-grid">
                    <div><label>Code</label><input type="text" name="code" placeholder="PT-30"></div>
                    <div><label>Libelle</label><input type="text" name="name" required></div>
                    <div><label>Nombre de jours</label><input type="number" name="days" min="0" value="0" required></div>
                    <div class="checkbox-card"><label style="display:flex; gap:10px; align-items:center; margin:0;"><input type="checkbox" name="is_default" value="1"> Par defaut</label></div>
                    <div class="full"><label>Description</label><textarea name="description"></textarea></div>
                </div>
                <div class="actions"><button type="submit" class="button button-primary">Ajouter la condition</button></div>
            </form>
        </section>

        <section class="card">
            <h2 style="margin-top:0;">Regles fiscales</h2>
            <div class="grid" style="margin-bottom:16px;">
                @foreach ($taxRules as $taxRule)
                    <div class="summary-box">
                        <strong>{{ $taxRule->name }}</strong>
                        <div class="muted" style="margin-top:6px;">{{ $taxRule->code }} · {{ number_format((float) $taxRule->rate, 2, ',', ' ') }}% · {{ strtoupper($taxRule->tax_kind) }}</div>
                        <div class="chip-row">
                            @if ($taxRule->is_default_sales)<span class="badge badge-success">Defaut vente</span>@endif
                            @if ($taxRule->is_default_purchases)<span class="badge badge-success">Defaut achat</span>@endif
                        </div>
                    </div>
                @endforeach
            </div>
            <form method="POST" action="{{ route('settings.tax-rules.store') }}">
                @csrf
                <div class="form-grid">
                    <div><label>Code</label><input type="text" name="code" placeholder="TVA18"></div>
                    <div><label>Libelle</label><input type="text" name="name" required></div>
                    <div>
                        <label>Portee</label>
                        <select name="scope">
                            <option value="sales">Ventes</option>
                            <option value="purchases">Achats</option>
                            <option value="both">Ventes et achats</option>
                        </select>
                    </div>
                    <div>
                        <label>Type</label>
                        <select name="tax_kind">
                            <option value="vat">TVA</option>
                            <option value="withholding">Retenue</option>
                        </select>
                    </div>
                    <div><label>Taux (%)</label><input type="number" step="0.01" min="0" max="100" name="rate" required></div>
                    <div><label>Compte TVA collectee</label><input type="text" name="collect_account_code" placeholder="443100"></div>
                    <div><label>Compte TVA deductible</label><input type="text" name="deductible_account_code" placeholder="445100"></div>
                    <div class="checkbox-card"><label style="display:flex; gap:10px; align-items:center; margin:0;"><input type="checkbox" name="is_default_sales" value="1"> Defaut ventes</label></div>
                    <div class="checkbox-card"><label style="display:flex; gap:10px; align-items:center; margin:0;"><input type="checkbox" name="is_default_purchases" value="1"> Defaut achats</label></div>
                </div>
                <div class="actions"><button type="submit" class="button button-primary">Ajouter la regle fiscale</button></div>
            </form>
        </section>
    </div>

    <div class="split" style="margin-top:18px;">
        <section class="card">
            <h2 style="margin-top:0;">Listes de prix</h2>
            <div class="grid" style="margin-bottom:16px;">
                @foreach ($priceLists as $priceList)
                    <div class="summary-box">
                        <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:flex-start;">
                            <div>
                                <strong>{{ $priceList->name }}</strong>
                                <div class="muted" style="margin-top:6px;">{{ $priceList->code }} · {{ $priceList->currency_code }}</div>
                            </div>
                            @if ($priceList->is_default)
                                <span class="badge badge-success">Par defaut</span>
                            @endif
                        </div>
                        @if ($priceList->items->isNotEmpty())
                            <div class="table-wrap" style="margin-top:12px;">
                                <table>
                                    <thead><tr><th>Produit</th><th>Qté mini</th><th>Prix</th></tr></thead>
                                    <tbody>
                                    @foreach ($priceList->items as $item)
                                        <tr>
                                            <td>{{ $item->product?->name }}</td>
                                            <td>{{ number_format((float) $item->min_qty, 0, ',', ' ') }}</td>
                                            <td>{{ number_format((float) $item->price, 0, ',', ' ') }}</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
            <form method="POST" action="{{ route('settings.price-lists.store') }}" style="margin-bottom:20px;">
                @csrf
                <div class="form-grid">
                    <div><label>Code</label><input type="text" name="code" placeholder="DETAIL"></div>
                    <div><label>Libelle</label><input type="text" name="name" required></div>
                    <div><label>Devise</label><input type="text" name="currency_code" value="XOF" required></div>
                    <div class="checkbox-card"><label style="display:flex; gap:10px; align-items:center; margin:0;"><input type="checkbox" name="is_default" value="1"> Par defaut</label></div>
                    <div class="full"><label>Description</label><textarea name="description"></textarea></div>
                </div>
                <div class="actions"><button type="submit" class="button button-primary">Ajouter la liste</button></div>
            </form>
            <form method="POST" action="{{ route('settings.price-list-items.store') }}">
                @csrf
                <div class="form-grid">
                    <div>
                        <label>Liste de prix</label>
                        <select name="price_list_id" required>
                            <option value="">Choisir</option>
                            @foreach ($priceLists as $priceList)
                                <option value="{{ $priceList->id }}">{{ $priceList->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label>Produit</label>
                        <select name="product_id" required>
                            <option value="">Choisir</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}">{{ $product->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div><label>Quantite mini</label><input type="number" step="1" min="1" name="min_qty" value="1" required></div>
                    <div><label>Prix</label><input type="number" step="0.01" min="0" name="price" required></div>
                </div>
                <div class="actions"><button type="submit" class="button button-primary">Ajouter une ligne tarifaire</button></div>
            </form>
        </section>

        <section class="card">
            <h2 style="margin-top:0;">API et integrations</h2>
            <div class="help" style="margin-bottom:16px;">Les jetons donnent acces a l API v1 securisee par Bearer token. Les evenements metier partent maintenant aussi dans une outbox interne.</div>
            <div class="grid" style="margin-bottom:16px;">
                @forelse ($apiTokens as $apiToken)
                    <div class="summary-box">
                        <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:flex-start;">
                            <div>
                                <strong>{{ $apiToken->name }}</strong>
                                <div class="muted" style="margin-top:6px;">Derniere utilisation : {{ $apiToken->last_used_at?->format('d/m/Y H:i') ?: 'Jamais' }}</div>
                                <div class="muted">Expiration : {{ $apiToken->expires_at?->format('d/m/Y') ?: 'Aucune' }}</div>
                            </div>
                            <form method="POST" action="{{ route('settings.api-tokens.destroy', $apiToken) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="button button-danger">Revoquer</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="empty-state">
                        <h3>Aucun jeton API</h3>
                        <p class="muted">Genere ton premier acces pour commencer les integrations externes.</p>
                    </div>
                @endforelse
            </div>
            <form method="POST" action="{{ route('settings.api-tokens.store') }}">
                @csrf
                <div class="form-grid">
                    <div><label>Nom du jeton</label><input type="text" name="name" placeholder="Connecteur BI" required></div>
                    <div><label>Date d expiration</label><input type="date" name="expires_at"></div>
                </div>
                <div class="actions"><button type="submit" class="button button-primary">Generer un jeton API</button></div>
            </form>
        </section>
    </div>
@endsection
