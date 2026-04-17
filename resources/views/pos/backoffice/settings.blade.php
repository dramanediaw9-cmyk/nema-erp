@extends('layouts.app')

@section('title', 'Configuration POS - Nema ERP')
@section('page-title', 'Configuration POS')

@section('content')
    <style>
        .pos-settings-alert {
            display: grid;
            gap: 12px;
            padding: 18px 20px;
            border-radius: 22px;
            border: 1px solid rgba(180, 83, 9, 0.25);
            background: linear-gradient(180deg, rgba(255, 251, 235, 0.96) 0%, rgba(254, 243, 199, 0.96) 100%);
            color: #78350f;
        }
        .pos-settings-alert-head {
            display: flex;
            justify-content: space-between;
            gap: 14px;
            align-items: flex-start;
            flex-wrap: wrap;
        }
        .pos-settings-open-sessions {
            display: grid;
            gap: 10px;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        }
        .pos-settings-open-session {
            border-radius: 18px;
            border: 1px solid rgba(146, 64, 14, 0.14);
            background: rgba(255, 255, 255, 0.7);
            padding: 14px 16px;
        }
        .pos-settings-open-session strong {
            display: block;
            color: #7c2d12;
        }
        .pos-settings-locked {
            opacity: .78;
        }
    </style>

    <div class="grid" style="gap:18px;">
        @include('pos.partials.backoffice-nav')

        <div class="page-head">
            <div>
                <h2 style="margin:0;">Parametres, profils et preparation</h2>
                <div class="muted">Configuration des points de vente, modes d encaissement, modeles de notes, imprimantes et displays.</div>
            </div>
            <a href="{{ route('pos.index') }}" class="button button-secondary">Retour POS</a>
        </div>

        <div class="grid stats-grid">
            <div class="card"><div class="muted">Profils POS</div><div class="stat-value">{{ $data['summary']['profiles'] }}</div></div>
            <div class="card"><div class="muted">Modes de paiement</div><div class="stat-value">{{ $data['summary']['payment_methods'] }}</div></div>
            <div class="card"><div class="muted">Modeles notes</div><div class="stat-value">{{ $data['summary']['note_templates'] }}</div></div>
            <div class="card"><div class="muted">Imprimantes prep</div><div class="stat-value">{{ $data['summary']['printers'] }}</div></div>
            <div class="card"><div class="muted">Displays prep</div><div class="stat-value">{{ $data['summary']['displays'] }}</div></div>
            <div class="card"><div class="muted">Sessions ouvertes</div><div class="stat-value">{{ $data['summary']['open_sessions'] }}</div></div>
        </div>

        @if ($data['settings_locked'])
            <section class="pos-settings-alert">
                <div class="pos-settings-alert-head">
                    <div>
                        <strong>Une session POS est en cours sur ce point de vente.</strong>
                        <div>Certains parametres sensibles ne peuvent etre modifies qu apres la fermeture de la session active, pour eviter une incoherence entre la caisse en cours et sa configuration.</div>
                    </div>
                    @if ($data['open_sessions']->first())
                        <a href="{{ route('pos.show', $data['open_sessions']->first()) }}" class="button button-secondary">Ouvrir la session</a>
                    @endif
                </div>
                <div class="pos-settings-open-sessions">
                    @foreach ($data['open_sessions'] as $session)
                        <article class="pos-settings-open-session">
                            <strong>{{ $session->session_number }}</strong>
                            <div>{{ $session->warehouse?->name ?? 'Sans entrepot' }} · {{ $session->cashAccount?->name ?? 'Sans caisse' }}</div>
                            <div class="muted">Ouverte {{ $session->opened_at?->format('d/m H:i') ?? 'n/a' }} par {{ $session->opener?->name ?? 'n/a' }}</div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        @allowed('pos.manage')
            <div class="split">
                <form method="POST" action="{{ route('pos.profiles.store') }}" class="card form-grid {{ $data['settings_locked'] ? 'pos-settings-locked' : '' }}">
                    @csrf
                    <fieldset {{ $data['settings_locked'] ? 'disabled' : '' }} style="border:0; padding:0; margin:0; display:contents;">
                    <div class="full"><h3 class="section-title">Profil point de vente</h3></div>
                    <div>
                        <label for="profile_name">Nom</label>
                        <input id="profile_name" name="name" value="{{ old('name') }}" required>
                    </div>
                    <div>
                        <label for="profile_branch_id">Agence</label>
                        <select id="profile_branch_id" name="branch_id">
                            <option value="">Aucune</option>
                            @foreach ($data['branches'] as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="profile_warehouse_id">Entrepot</label>
                        <select id="profile_warehouse_id" name="warehouse_id">
                            <option value="">Aucun</option>
                            @foreach ($data['warehouses'] as $warehouse)
                                <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="profile_cash_account_id">Compte de caisse</label>
                        <select id="profile_cash_account_id" name="cash_account_id">
                            <option value="">Aucun</option>
                            @foreach ($data['cash_accounts'] as $account)
                                <option value="{{ $account->id }}">{{ $account->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="profile_price_list_id">Liste de prix</label>
                        <select id="profile_price_list_id" name="price_list_id">
                            <option value="">Aucune</option>
                            @foreach ($data['price_lists'] as $priceList)
                                <option value="{{ $priceList->id }}">{{ $priceList->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="profile_loyalty_program_id">Fidelite</label>
                        <select id="profile_loyalty_program_id" name="loyalty_program_id">
                            <option value="">Aucune</option>
                            @foreach ($data['loyalty_programs'] as $program)
                                <option value="{{ $program->id }}">{{ $program->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="full">
                        <label for="profile_active_payment_methods">Modes de paiement actifs</label>
                        <select id="profile_active_payment_methods" name="active_payment_methods[]" multiple size="6">
                            @foreach ($methodOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="full">
                        <label>Pieces / billets d ouverture</label>
                        <div class="grid stats-grid">
                            @foreach (['10000', '5000', '2000', '1000', '500', '100'] as $denomination)
                                <div>
                                    <label for="denomination_{{ $denomination }}">{{ $denomination }}</label>
                                    <input id="denomination_{{ $denomination }}" name="cash_denomination_preset[{{ $denomination }}]" type="number" min="0" value="{{ old('cash_denomination_preset.'.$denomination, 0) }}">
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="full checkbox-grid">
                        <label class="checkbox-row"><input type="checkbox" name="open_with_cash_control" value="1" checked> Controle caisse a l ouverture</label>
                        <label class="checkbox-row"><input type="checkbox" name="auto_print_receipt" value="1" checked> Impression auto ticket</label>
                        <label class="checkbox-row"><input type="checkbox" name="allow_draft_orders" value="1" checked> Autoriser les brouillons</label>
                        <label class="checkbox-row"><input type="checkbox" name="is_default" value="1"> Profil par defaut</label>
                    </div>
                    <div class="full actions">
                        <button type="submit" class="button button-primary">Enregistrer le profil</button>
                    </div>
                    </fieldset>
                </form>

                <div class="grid" style="gap:18px;">
                    <form method="POST" action="{{ route('pos.payment-methods.store') }}" class="card form-grid {{ $data['settings_locked'] ? 'pos-settings-locked' : '' }}">
                        @csrf
                        <fieldset {{ $data['settings_locked'] ? 'disabled' : '' }} style="border:0; padding:0; margin:0; display:contents;">
                        <div class="full"><h3 class="section-title">Mode de paiement</h3></div>
                        <div>
                            <label for="method_code">Code</label>
                            <select id="method_code" name="method_code" required>
                                @foreach ($methodOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="method_label">Libelle</label>
                            <input id="method_label" name="label" value="{{ old('label') }}" required>
                        </div>
                        <div>
                            <label for="cash_account_id">Compte</label>
                            <select id="cash_account_id" name="cash_account_id">
                                <option value="">Aucun</option>
                                @foreach ($data['cash_accounts'] as $account)
                                    <option value="{{ $account->id }}">{{ $account->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="sort_order">Ordre</label>
                            <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', 0) }}">
                        </div>
                        <div class="full checkbox-grid">
                            <label class="checkbox-row"><input type="checkbox" name="requires_reference" value="1"> Reference requise</label>
                            <label class="checkbox-row"><input type="checkbox" name="supports_change" value="1"> Rend la monnaie</label>
                            <label class="checkbox-row"><input type="checkbox" name="is_default" value="1"> Par defaut</label>
                            <label class="checkbox-row"><input type="checkbox" name="is_active" value="1" checked> Actif</label>
                        </div>
                        <div class="full actions"><button type="submit" class="button button-primary">Enregistrer le mode</button></div>
                        </fieldset>
                    </form>

                    <form method="POST" action="{{ route('pos.note-templates.store') }}" class="card form-grid {{ $data['settings_locked'] ? 'pos-settings-locked' : '' }}">
                        @csrf
                        <fieldset {{ $data['settings_locked'] ? 'disabled' : '' }} style="border:0; padding:0; margin:0; display:contents;">
                        <div class="full"><h3 class="section-title">Modele de note</h3></div>
                        <div>
                            <label for="note_name">Nom</label>
                            <input id="note_name" name="name" value="{{ old('name') }}" required>
                        </div>
                        <div>
                            <label for="note_usage">Usage</label>
                            <select id="note_usage" name="usage" required>
                                <option value="receipt">Ticket</option>
                                <option value="kitchen">Cuisine</option>
                                <option value="prep">Preparation</option>
                            </select>
                        </div>
                        <div class="full">
                            <label for="note_content">Contenu</label>
                            <textarea id="note_content" name="content" required>{{ old('content', "Merci pour votre achat.\nA bientot chez Nema.") }}</textarea>
                        </div>
                        <div class="full checkbox-grid">
                            <label class="checkbox-row"><input type="checkbox" name="is_default" value="1"> Par defaut</label>
                            <label class="checkbox-row"><input type="checkbox" name="is_active" value="1" checked> Actif</label>
                        </div>
                        <div class="full actions"><button type="submit" class="button button-primary">Enregistrer le modele</button></div>
                        </fieldset>
                    </form>
                </div>
            </div>

            <div class="split">
                <form method="POST" action="{{ route('pos.preparation-printers.store') }}" class="card form-grid {{ $data['settings_locked'] ? 'pos-settings-locked' : '' }}">
                    @csrf
                    <fieldset {{ $data['settings_locked'] ? 'disabled' : '' }} style="border:0; padding:0; margin:0; display:contents;">
                    <div class="full"><h3 class="section-title">Imprimante de preparation</h3></div>
                    <div><label for="printer_name">Nom</label><input id="printer_name" name="name" value="{{ old('name') }}" required></div>
                    <div><label for="printer_target_area">Zone</label><input id="printer_target_area" name="target_area" value="{{ old('target_area', 'Cuisine') }}"></div>
                    <div>
                        <label for="printer_connection_type">Connexion</label>
                        <select id="printer_connection_type" name="connection_type" required>
                            <option value="network">Reseau</option>
                            <option value="usb">USB</option>
                            <option value="cloud">Cloud</option>
                        </select>
                    </div>
                    <div><label for="printer_endpoint">Endpoint</label><input id="printer_endpoint" name="endpoint" value="{{ old('endpoint') }}"></div>
                    <div><label for="copy_count">Copies</label><input id="copy_count" name="copy_count" type="number" min="1" max="10" value="{{ old('copy_count', 1) }}"></div>
                    <div><label for="printer_prep_time_target_minutes">Temps cible</label><input id="printer_prep_time_target_minutes" name="prep_time_target_minutes" type="number" min="0" max="240" value="{{ old('prep_time_target_minutes', 10) }}"></div>
                    <div class="full actions"><button type="submit" class="button button-primary">Enregistrer l imprimante</button></div>
                    </fieldset>
                </form>

                <form method="POST" action="{{ route('pos.preparation-displays.store') }}" class="card form-grid {{ $data['settings_locked'] ? 'pos-settings-locked' : '' }}">
                    @csrf
                    <fieldset {{ $data['settings_locked'] ? 'disabled' : '' }} style="border:0; padding:0; margin:0; display:contents;">
                    <div class="full"><h3 class="section-title">Preparation Display</h3></div>
                    <div><label for="display_name">Nom</label><input id="display_name" name="name" value="{{ old('name') }}" required></div>
                    <div><label for="display_target_area">Zone</label><input id="display_target_area" name="target_area" value="{{ old('target_area', 'Retrait') }}"></div>
                    <div>
                        <label for="display_mode">Mode</label>
                        <select id="display_mode" name="display_mode" required>
                            <option value="kitchen">Cuisine</option>
                            <option value="pickup">Retrait</option>
                            <option value="counter">Comptoir</option>
                        </select>
                    </div>
                    <div><label for="display_endpoint">Endpoint</label><input id="display_endpoint" name="endpoint" value="{{ old('endpoint') }}"></div>
                    <div><label for="refresh_seconds">Refresh</label><input id="refresh_seconds" name="refresh_seconds" type="number" min="5" max="300" value="{{ old('refresh_seconds', 20) }}"></div>
                    <div><label for="display_prep_time_target_minutes">Temps cible</label><input id="display_prep_time_target_minutes" name="prep_time_target_minutes" type="number" min="0" max="240" value="{{ old('prep_time_target_minutes', 8) }}"></div>
                    <div class="full actions"><button type="submit" class="button button-primary">Enregistrer le display</button></div>
                    </fieldset>
                </form>
            </div>
        @endallowed

        <div class="split">
            <section class="card">
                <h3 class="section-title">Profils et modes de paiement</h3>
                <div class="summary-stack">
                    @foreach ($data['profiles'] as $profile)
                        <div class="summary-box">
                            <strong>{{ $profile->name }}</strong>
                            <div class="muted" style="margin-top:8px;">{{ $profile->branch?->name ?? 'Agence globale' }} · {{ $profile->warehouse?->name ?? 'Sans entrepot' }}</div>
                            <div class="help" style="margin-top:8px;">{{ count($profile->active_payment_methods ?? []) }} mode(s) actif(s) · {{ $profile->is_default ? 'profil par defaut' : 'profil secondaire' }}</div>
                        </div>
                    @endforeach
                    @foreach ($data['payment_methods'] as $method)
                        <div class="summary-box">
                            <strong>{{ $method->label }}</strong>
                            <div class="help" style="margin-top:8px;">{{ $method->cashAccount?->name ?? 'Sans compte' }} · {{ $method->supports_change ? 'monnaie' : 'sans monnaie' }}</div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="card">
                <h3 class="section-title">Notes et preparation</h3>
                <div class="summary-stack">
                    @foreach ($data['note_templates'] as $template)
                        <div class="summary-box">
                            <strong>{{ $template->name }}</strong>
                            <div class="help" style="margin-top:8px;">{{ strtoupper($template->usage) }} · {{ $template->is_default ? 'modele par defaut' : 'modele secondaire' }}</div>
                        </div>
                    @endforeach
                    @foreach ($data['printers'] as $printer)
                        <div class="summary-box">
                            <strong>{{ $printer->name }}</strong>
                            <div class="help" style="margin-top:8px;">Imprimante · {{ $printer->target_area ?: 'Zone non renseignee' }}</div>
                        </div>
                    @endforeach
                    @foreach ($data['displays'] as $display)
                        <div class="summary-box">
                            <strong>{{ $display->name }}</strong>
                            <div class="help" style="margin-top:8px;">Display · {{ $display->target_area ?: 'Zone non renseignee' }}</div>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>
    </div>
@endsection
