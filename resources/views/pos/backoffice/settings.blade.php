@extends('layouts.app')

@section('title', 'Configuration POS - Nema ERP')
@section('page-title', 'Configuration POS')

@section('content')
    @php
        $productLabel = $businessVocabulary['product'] ?? 'Produit';
        $productsLabel = $businessVocabulary['products'] ?? 'Produits';
        $saleLabel = $businessVocabulary['sale'] ?? 'Vente';
        $stockLabel = $businessVocabulary['stock'] ?? 'Stock';
        $cashierLabel = $businessVocabulary['cashier'] ?? 'Caissier';
    @endphp

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
                <form method="POST" action="{{ route('pos.profiles.store') }}" enctype="multipart/form-data" class="card form-grid {{ $data['settings_locked'] ? 'pos-settings-locked' : '' }}">
                    @csrf
                    <fieldset {{ $data['settings_locked'] ? 'disabled' : '' }} style="border:0; padding:0; margin:0; display:contents;">
                    <div class="full"><h3 class="section-title">Configuration de la caisse</h3></div>
                    <div class="full">
                        <label for="profile_id">Modifier une caisse existante</label>
                        <select id="profile_id" name="profile_id">
                            <option value="">Creer une nouvelle configuration</option>
                            @foreach ($data['profiles'] as $existingProfile)
                                <option value="{{ $existingProfile->id }}">{{ $existingProfile->name }} · {{ $existingProfile->cashAccount?->name ?? 'Sans compte' }}</option>
                            @endforeach
                        </select>
                    </div>
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
                    <div>
                        <label for="stock_policy">{{ $saleLabel }} sans {{ strtolower($stockLabel) }}</label>
                        <select id="stock_policy" name="stock_policy" required>
                            <option value="block">Bloquer la {{ strtolower($saleLabel) }}</option>
                            <option value="warn">Avertir le {{ strtolower($cashierLabel) }}</option>
                            <option value="allow">Autoriser</option>
                        </select>
                    </div>
                    <div>
                        <label for="max_cash_variance">Ecart maximal de caisse</label>
                        <input id="max_cash_variance" name="max_cash_variance" type="number" min="0" step="1" placeholder="Ex: 1000">
                    </div>
                    <div>
                        <label for="cash_rounding_precision">Arrondi especes</label>
                        <input id="cash_rounding_precision" name="cash_rounding_precision" type="number" min="0.01" step="0.01" value="5">
                    </div>
                    <div class="full checkbox-grid">
                        <label class="checkbox-row"><input type="checkbox" name="show_stock_quantity" value="1" checked> Afficher le {{ strtolower($stockLabel) }} dans la caisse</label>
                        <label class="checkbox-row"><input type="checkbox" name="show_product_images" value="1" checked> Afficher les images {{ strtolower($productsLabel) }}</label>
                        <label class="checkbox-row"><input type="checkbox" name="group_products_by_category" value="1" checked> Regrouper par categories</label>
                        <label class="checkbox-row"><input type="checkbox" name="share_open_orders" value="1"> Partager les commandes ouvertes</label>
                        <label class="checkbox-row"><input type="checkbox" name="quick_cash_payment" value="1"> Paiement especes en un clic</label>
                        <label class="checkbox-row"><input type="checkbox" name="cash_rounding_enabled" value="1"> Activer l arrondi especes</label>
                        <label class="checkbox-row"><input type="checkbox" name="allow_tips" value="1"> Autoriser les pourboires</label>
                    </div>
                    <div>
                        <label for="receipt_logo">Logo du ticket</label>
                        <input id="receipt_logo" name="receipt_logo" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                    </div>
                    <div>
                        <label for="receipt_header">Entete du ticket</label>
                        <input id="receipt_header" name="receipt_header" maxlength="255" placeholder="Bienvenue chez nous">
                    </div>
                    <div>
                        <label for="receipt_footer">Pied du ticket</label>
                        <input id="receipt_footer" name="receipt_footer" maxlength="255" placeholder="Merci pour votre achat">
                    </div>
                    <div class="full checkbox-grid">
                        <label class="checkbox-row"><input type="checkbox" name="receipt_show_cashier" value="1" checked> Afficher le {{ strtolower($cashierLabel) }} sur le ticket</label>
                        <label class="checkbox-row"><input type="checkbox" name="receipt_show_address" value="1" checked> Afficher l adresse sur le ticket</label>
                    </div>
                    <div class="full actions">
                        <button type="submit" class="button button-primary">Enregistrer la configuration</button>
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
                            <div class="help" style="margin-top:6px;">{{ $stockLabel }} : {{ match($profile->stock_policy ?? 'block') { 'allow' => 'autorise', 'warn' => 'avertissement', default => 'bloque' } }} · Images {{ $profile->show_product_images ? 'oui' : 'non' }} · Arrondi {{ $profile->cash_rounding_enabled ? 'oui' : 'non' }}</div>
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
    @php
        $profileSettings = $data['profiles']->map(function ($profile) {
            return [
                'id' => $profile->id,
                'name' => $profile->name,
                'branch_id' => $profile->branch_id,
                'warehouse_id' => $profile->warehouse_id,
                'cash_account_id' => $profile->cash_account_id,
                'price_list_id' => $profile->price_list_id,
                'loyalty_program_id' => $profile->loyalty_program_id,
                'active_payment_methods' => $profile->active_payment_methods ?? [],
                'cash_denomination_preset' => $profile->cash_denomination_preset ?? [],
                'open_with_cash_control' => $profile->open_with_cash_control,
                'auto_print_receipt' => $profile->auto_print_receipt,
                'allow_draft_orders' => $profile->allow_draft_orders,
                'stock_policy' => $profile->stock_policy ?? 'block',
                'show_stock_quantity' => $profile->show_stock_quantity,
                'show_product_images' => $profile->show_product_images,
                'group_products_by_category' => $profile->group_products_by_category,
                'share_open_orders' => $profile->share_open_orders,
                'quick_cash_payment' => $profile->quick_cash_payment,
                'cash_rounding_enabled' => $profile->cash_rounding_enabled,
                'cash_rounding_precision' => $profile->cash_rounding_precision,
                'max_cash_variance' => $profile->max_cash_variance,
                'allow_tips' => $profile->allow_tips,
                'receipt_show_cashier' => $profile->receipt_show_cashier,
                'receipt_show_address' => $profile->receipt_show_address,
                'receipt_header' => $profile->receipt_header,
                'receipt_footer' => $profile->receipt_footer,
                'is_default' => $profile->is_default,
            ];
        })->values();
    @endphp
    <script>
        (() => {
            const profiles = {{ Illuminate\Support\Js::from($profileSettings) }};
            const selector = document.getElementById('profile_id');
            const form = selector?.closest('form');
            if (!selector || !form) return;

            const setValue = (name, value) => {
                const input = form.elements.namedItem(name);
                if (input && input instanceof HTMLElement) input.value = value ?? '';
            };
            const setChecked = (name, value) => {
                const input = form.elements.namedItem(name);
                if (input instanceof HTMLInputElement) input.checked = Boolean(value);
            };

            selector.addEventListener('change', () => {
                const profile = profiles.find((item) => String(item.id) === selector.value);
                if (!profile) return;
                ['name', 'branch_id', 'warehouse_id', 'cash_account_id', 'price_list_id', 'loyalty_program_id', 'stock_policy', 'cash_rounding_precision', 'max_cash_variance', 'receipt_header', 'receipt_footer']
                    .forEach((name) => setValue(name, profile[name]));
                ['open_with_cash_control', 'auto_print_receipt', 'allow_draft_orders', 'show_stock_quantity', 'show_product_images', 'group_products_by_category', 'share_open_orders', 'quick_cash_payment', 'cash_rounding_enabled', 'allow_tips', 'receipt_show_cashier', 'receipt_show_address', 'is_default']
                    .forEach((name) => setChecked(name, profile[name]));
                Array.from(form.querySelectorAll('[name="active_payment_methods[]"] option'))
                    .forEach((option) => { option.selected = profile.active_payment_methods.includes(option.value); });
                Object.entries(profile.cash_denomination_preset || {})
                    .forEach(([denomination, count]) => setValue(`cash_denomination_preset[${denomination}]`, count));
            });
        })();
    </script>
@endsection
