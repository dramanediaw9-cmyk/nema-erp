@extends('layouts.app')

@section('title', 'Demarrage - Nema ERP')
@section('page-title', 'Parcours de demarrage')

@section('content')
    @php
        $sectorProfile = $summary['sector_profile'];
        $sectorStarter = $summary['sector_starter'];
        $sectorDemo = $summary['sector_demo_data'];
        $pilotReadiness = $summary['pilot_readiness'];
    @endphp

    <div class="page-head">
        <div>
            <h2 style="margin:0;">Checklist de mise en route</h2>
            <div class="muted">Cette page aide l entreprise a passer d une installation ERP a une utilisation quotidienne sans oublier les prealables.</div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            @if ($bannerDismissed)
                <form method="POST" action="{{ route('onboarding.reopen') }}">
                    @csrf
                    <button type="submit" class="button button-secondary">Reafficher le bandeau</button>
                </form>
            @else
                <form method="POST" action="{{ route('onboarding.dismiss') }}">
                    @csrf
                    <button type="submit" class="button button-secondary">Masquer le bandeau dashboard</button>
                </form>
            @endif
        </div>
    </div>

    <section class="card" style="margin-bottom:20px;">
        <div style="display:flex; justify-content:space-between; gap:16px; align-items:flex-start; flex-wrap:wrap;">
            <div>
                <div class="muted">Progression</div>
                <div class="stat-value" style="margin-top:6px;">{{ $summary['completed'] }}/{{ $summary['total'] }}</div>
                <div class="muted">{{ $summary['progress'] }}% des etapes sont terminees pour {{ $company->name }}.</div>
            </div>
            <div style="min-width:260px; flex:1; max-width:420px;">
                <div class="progress"><div class="progress-bar" style="width: {{ $summary['progress'] }}%;"></div></div>
                @if ($summary['is_complete'])
                    <div class="badge badge-success" style="margin-top:12px;">Societe prete a operer</div>
                @elseif ($summary['next_step'])
                    <div class="badge badge-warning" style="margin-top:12px;">Prochaine priorite : {{ $summary['next_step']['title'] }}</div>
                @endif
            </div>
        </div>
    </section>

    <section class="card" style="margin-bottom:20px; border-color:#a9d5c9; background:linear-gradient(135deg, rgba(239, 250, 248, 0.98) 0%, rgba(255, 250, 241, 0.96) 100%);">
        <div style="display:flex; justify-content:space-between; gap:18px; align-items:flex-start; flex-wrap:wrap; margin-bottom:18px;">
            <div style="max-width:780px;">
                <div class="badge badge-success">Objectif 15 minutes</div>
                <h2 style="margin:12px 0 8px;">Demarrer plus vite qu un ERP classique</h2>
                <div class="muted">Le but est simple : configurer l entreprise, ouvrir une caisse, ajouter les premiers produits, vendre et voir les chiffres sans formation longue.</div>
            </div>
            <div style="display:grid; gap:8px; min-width:230px;">
                <div class="summary-box" style="background:rgba(255,255,255,.72);">
                    <strong>{{ $summary['progress'] }}% pret</strong>
                    <div class="help" style="margin-top:8px;">{{ $summary['completed'] }} etape(s) terminee(s) sur {{ $summary['total'] }}.</div>
                </div>
            </div>
        </div>

        <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
            @foreach ($summary['launch_plan'] as $item)
                @php
                    $step = $item['step'] ?? [];
                    $done = (bool) ($step['completed'] ?? false);
                @endphp
                <div class="card" style="padding:16px; border-color:{{ $done ? '#b8d8d0' : '#efc28c' }}; background:{{ $done ? 'rgba(247, 252, 251, .9)' : 'rgba(255, 249, 240, .92)' }};">
                    <div style="display:flex; justify-content:space-between; gap:10px; align-items:flex-start; flex-wrap:wrap;">
                        <span class="badge badge-muted">{{ $item['minute'] }}</span>
                        <span class="badge {{ $done ? 'badge-success' : 'badge-warning' }}">{{ $done ? 'Pret' : 'A faire' }}</span>
                    </div>
                    <strong style="display:block; margin-top:12px;">{{ $item['title'] }}</strong>
                    <div class="muted" style="margin-top:8px;">{{ $item['promise'] }}</div>
                    <div class="help" style="margin-top:10px;">Etat : {{ $step['metric'] ?? 'A verifier' }}</div>
                    <div style="margin-top:12px;">
                        <a href="{{ $step['route'] ?? route('onboarding.index') }}" class="button {{ $done ? 'button-secondary' : 'button-primary' }}">{{ $step['action'] ?? 'Continuer' }}</a>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <section id="starter-pack" class="card" style="margin-bottom:20px; border-color:#b8d8d0; background:linear-gradient(135deg, rgba(239, 250, 248, 0.98) 0%, rgba(255, 249, 240, 0.94) 100%);">
        <div style="display:flex; justify-content:space-between; gap:18px; align-items:flex-start; flex-wrap:wrap; margin-bottom:18px;">
            <div style="max-width:760px;">
                <div class="badge badge-success">Profil secteur actif</div>
                <h2 style="margin:12px 0 8px;">{{ $sectorProfile['label'] }}</h2>
                <div class="muted">{{ $sectorProfile['description'] }}</div>
                <div class="chip-row" style="margin-top:12px;">
                    @foreach ($sectorProfile['use_cases'] as $useCase)
                        <span class="badge badge-muted">{{ $useCase }}</span>
                    @endforeach
                </div>
            </div>
            <div style="display:grid; gap:10px; min-width:240px;">
                @if ($sectorStarter['is_applied'])
                    <div class="badge badge-success">Starter pack applique</div>
                    @if ($sectorStarter['applied_at'])
                        <div class="help">Derniere application : {{ \Illuminate\Support\Carbon::parse($sectorStarter['applied_at'])->format('d/m/Y H:i') }}</div>
                    @endif
                @else
                    <div class="badge badge-warning">Starter pack a appliquer</div>
                    <div class="help">Le profil est choisi, mais les reglages de depart ne sont pas encore poses.</div>
                @endif
                @allowed('settings.manage')
                    <form method="POST" action="{{ route('onboarding.sector-starter.apply') }}">
                        @csrf
                        <button type="submit" class="button button-primary" style="width:100%;">{{ $sectorStarter['is_applied'] ? 'Reappliquer le starter pack' : 'Appliquer le starter pack' }}</button>
                    </form>
                @endallowed
                @allowed('settings.view')
                    <a href="{{ route('settings.index') }}" class="button button-secondary" style="text-align:center;">Ajuster le profil secteur</a>
                @endallowed
            </div>
        </div>

        <div class="grid" style="margin-bottom:18px;">
            <div class="summary-box">
                <strong>Focus terrain</strong>
                <div class="help" style="margin-top:8px;">{{ implode(' · ', $sectorProfile['operational_focus']) }}</div>
                <div class="chip-row" style="margin-top:10px;">
                    @foreach ($sectorProfile['starter_catalog'] as $item)
                        <span class="badge badge-muted">{{ $item }}</span>
                    @endforeach
                </div>
            </div>
            <div class="summary-box">
                <strong>Unites conseillees</strong>
                <div class="chip-row" style="margin-top:10px;">
                    @foreach ($sectorProfile['recommended_units'] as $unit)
                        <span class="badge badge-muted">{{ $unit }}</span>
                    @endforeach
                </div>
            </div>
            <div class="summary-box">
                <strong>Paiements terrain</strong>
                <div class="chip-row" style="margin-top:10px;">
                    @foreach ($sectorProfile['recommended_payments'] as $payment)
                        <span class="badge badge-muted">{{ $payment }}</span>
                    @endforeach
                </div>
            </div>
            <div class="summary-box">
                <strong>Etat du starter</strong>
                <div class="help" style="margin-top:8px;">{{ $sectorStarter['categories_count'] }} categories produits · {{ $sectorStarter['expense_categories_count'] }} categories depense</div>
                <div class="help" style="margin-top:8px;">{{ $sectorStarter['price_lists_count'] }} listes de prix · {{ $sectorStarter['payment_terms_count'] }} conditions de paiement</div>
                <div class="help" style="margin-top:8px;">{{ $sectorStarter['recommended_gateways_ready'] }}/{{ $sectorStarter['recommended_gateways_count'] }} paiements terrain deja prets</div>
            </div>
        </div>

        @if (! empty($sectorProfile['recommended_modules']))
            <div class="grid">
                @foreach ($sectorProfile['recommended_modules'] as $module)
                    @if (auth()->user()?->hasPermission($module['permission']))
                        <a href="{{ route($module['route_name']) }}" class="card" style="padding:16px; text-decoration:none; color:inherit;">
                            <strong>{{ $module['label'] }}</strong>
                            <div class="muted" style="margin-top:8px;">{{ $module['description'] }}</div>
                        </a>
                    @endif
                @endforeach
            </div>
        @endif
    </section>

    <section id="demo-data" class="card" style="margin-bottom:20px; border-color:#d5c6ff; background:linear-gradient(135deg, rgba(247, 244, 255, 0.98) 0%, rgba(240, 249, 255, 0.95) 100%);">
        <div style="display:flex; justify-content:space-between; gap:18px; align-items:flex-start; flex-wrap:wrap; margin-bottom:18px;">
            <div style="max-width:760px;">
                <div class="badge badge-warning">Donnees de demonstration secteur</div>
                <h2 style="margin:12px 0 8px;">Charge un catalogue et des parcours de test prets a l emploi</h2>
                <div class="muted">Ce pack met en place des fournisseurs, clients, produits, tarifs, stock et scenarios de test adaptes a {{ $sectorProfile['label'] }}.</div>
                @if (! empty($sectorDemo['catalog_highlights']))
                    <div class="chip-row" style="margin-top:12px;">
                        @foreach ($sectorDemo['catalog_highlights'] as $highlight)
                            <span class="badge badge-muted">{{ $highlight }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
            <div style="display:grid; gap:10px; min-width:260px;">
                @if ($sectorDemo['is_applied'])
                    <div class="badge badge-success">Demo metier chargee</div>
                    @if ($sectorDemo['applied_at'])
                        <div class="help">Dernier chargement : {{ \Illuminate\Support\Carbon::parse($sectorDemo['applied_at'])->format('d/m/Y H:i') }}</div>
                    @endif
                    @if ($sectorDemo['branch_name'])
                        <div class="help">Agence : {{ $sectorDemo['branch_name'] }} · Depot : {{ $sectorDemo['warehouse_name'] }}</div>
                    @endif
                @else
                    <div class="badge badge-warning">Demo metier a charger</div>
                    <div class="help">Ideal pour tester le POS, les achats, le stock et les rapports sans tout saisir a la main.</div>
                @endif
                @allowed('settings.manage')
                    <form method="POST" action="{{ route('onboarding.sector-demo.apply') }}">
                        @csrf
                        <button type="submit" class="button button-primary" style="width:100%;">{{ $sectorDemo['is_applied'] ? 'Recharger la demo metier' : 'Charger la demo metier' }}</button>
                    </form>
                @endallowed
                @allowed('products.view')
                    <a href="{{ route('products.index') }}" class="button button-secondary" style="text-align:center;">Voir le catalogue demo</a>
                @endallowed
            </div>
        </div>

        <div class="grid" style="margin-bottom:18px;">
            <div class="summary-box">
                <strong>Tiers de demo</strong>
                <div class="help" style="margin-top:8px;">{{ $sectorDemo['suppliers_count'] }} fournisseurs · {{ $sectorDemo['customers_count'] }} clients</div>
                <div class="help" style="margin-top:8px;">Des partenaires demo sont prets pour ventes, achats et portail client.</div>
            </div>
            <div class="summary-box">
                <strong>Catalogue et tarifs</strong>
                <div class="help" style="margin-top:8px;">{{ $sectorDemo['products_count'] }} produits · {{ $sectorDemo['price_items_count'] }} lignes tarifaires</div>
                <div class="help" style="margin-top:8px;">Les listes detail, gros, promo ou VIP sont deja chargees selon le secteur.</div>
            </div>
            <div class="summary-box">
                <strong>Stock et tracabilite</strong>
                <div class="help" style="margin-top:8px;">{{ $sectorDemo['stock_entries_count'] }} mouvements demo · {{ $sectorDemo['lots_count'] }} lots</div>
                <div class="help" style="margin-top:8px;">Le pack prepare aussi les articles traces pour les secteurs sensibles.</div>
            </div>
            <div class="summary-box">
                <strong>Achat et sourcing</strong>
                <div class="help" style="margin-top:8px;">{{ $sectorDemo['supplier_links_count'] }} liaisons produit-fournisseur</div>
                <div class="help" style="margin-top:8px;">Les fournisseurs preferes et delais servent tout de suite au reassort et aux achats.</div>
            </div>
        </div>

        @if (! empty($sectorDemo['playbooks']))
            <div style="display:flex; justify-content:space-between; gap:16px; align-items:flex-end; flex-wrap:wrap; margin-bottom:14px;">
                <div>
                    <h3 style="margin:0 0 6px;">Parcours de test prets</h3>
                    <div class="muted">Chaque parcours a ete pense pour montrer une vraie valeur ERP sur ton secteur.</div>
                </div>
                <div class="badge badge-muted">{{ $sectorDemo['playbooks_count'] }} scenario(s)</div>
            </div>
            <div class="grid">
                @foreach ($sectorDemo['playbooks'] as $playbook)
                    @php
                        $canOpen = ! isset($playbook['permission']) || auth()->user()?->hasPermission($playbook['permission']);
                    @endphp
                    @if ($canOpen)
                        <div class="card" style="padding:16px;">
                            <div style="display:flex; justify-content:space-between; gap:12px; align-items:flex-start; flex-wrap:wrap;">
                                <strong>{{ $playbook['title'] }}</strong>
                                <a href="{{ route($playbook['route_name']) }}" class="button button-secondary">{{ $playbook['action'] }}</a>
                            </div>
                            <div class="muted" style="margin-top:8px;">{{ $playbook['description'] }}</div>
                            <div style="display:grid; gap:8px; margin-top:12px;">
                                @foreach ($playbook['steps'] as $index => $step)
                                    <div class="help">{{ $index + 1 }}. {{ $step }}</div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        @endif
    </section>

    <section id="pilot-readiness" class="card" style="margin-bottom:20px; border-color:#f0bb86; background:linear-gradient(135deg, rgba(255, 250, 241, 0.98) 0%, rgba(247, 250, 255, 0.95) 100%);">
        <div style="display:flex; justify-content:space-between; gap:18px; align-items:flex-start; flex-wrap:wrap; margin-bottom:18px;">
            <div style="max-width:760px;">
                <div class="badge {{ $pilotReadiness['is_ready'] ? 'badge-success' : 'badge-warning' }}">Essai reel terrain</div>
                <h2 style="margin:12px 0 8px;">Verrouille le pilote avant de passer en boutique</h2>
                <div class="muted">Cette section verifie si la caisse, les roles, le catalogue, le stock et les paiements sont assez solides pour un essai reel sans surprise.</div>
                <div class="chip-row" style="margin-top:12px;">
                    <span class="badge badge-muted">Agence pilote : {{ $pilotReadiness['pilot_branch'] ?: 'Non definie' }}</span>
                    <span class="badge badge-muted">Score : {{ $pilotReadiness['score'] }}%</span>
                    <span class="badge badge-muted">{{ $pilotReadiness['blockers_count'] }} bloquant(s)</span>
                    <span class="badge badge-muted">{{ $pilotReadiness['stocked_saleable_count'] }} produit(s) vendables en stock</span>
                </div>
            </div>
            <div style="display:grid; gap:10px; min-width:260px;">
                @if ($pilotReadiness['is_ready'])
                    <div class="badge badge-success">Pilote terrain pret</div>
                    <div class="help">Le socle minimum est en place pour un essai reel pilote.</div>
                @else
                    <div class="badge badge-warning">Pilote encore a verrouiller</div>
                    <div class="help">Le test peut demarrer apres correction des points bloquants ci-dessous.</div>
                @endif
                @allowed('pos.view')
                    <a href="{{ route('pos.index') }}" class="button button-primary" style="text-align:center;">Tester la caisse</a>
                @endallowed
                @allowed('reports.view')
                    <a href="{{ route('reports.index') }}" class="button button-secondary" style="text-align:center;">Voir les chiffres du pilote</a>
                @endallowed
            </div>
        </div>

        <div class="grid" style="margin-bottom:18px;">
            <div class="summary-box">
                <strong>Roles pilotes</strong>
                <div class="help" style="margin-top:8px;">{{ $pilotReadiness['cashier_count'] }} caissier(s) · {{ $pilotReadiness['operations_count'] }} operation(s) · {{ $pilotReadiness['supervisor_count'] }} supervision</div>
                <div class="help" style="margin-top:8px;">Il faut au minimum un caissier, un gestionnaire et un referent operations.</div>
            </div>
            <div class="summary-box">
                <strong>Caisse et paiements</strong>
                <div class="help" style="margin-top:8px;">{{ $pilotReadiness['cash_count'] }} compte(s) cash · {{ $pilotReadiness['mobile_count'] }} compte(s) mobile money</div>
                <div class="help" style="margin-top:8px;">Le pilote doit couvrir l espece et au moins un canal terrain mobile.</div>
            </div>
            <div class="summary-box">
                <strong>Catalogue vendable</strong>
                <div class="help" style="margin-top:8px;">{{ $pilotReadiness['saleable_products_count'] }} produit(s) vendables · {{ $pilotReadiness['stocked_saleable_count'] }} avec stock positif</div>
                <div class="help" style="margin-top:8px;">Mets en avant les produits les plus frequents du pilote pour aller vite.</div>
            </div>
            <div class="summary-box">
                <strong>Logistique pilote</strong>
                <div class="help" style="margin-top:8px;">{{ $pilotReadiness['warehouse_count'] }} depot(s) actif(s)</div>
                <div class="help" style="margin-top:8px;">Le stock, les lots et les reassorts doivent etre rattaches a l agence pilote.</div>
            </div>
        </div>

        <div style="margin-bottom:18px;">
            <div style="display:flex; justify-content:space-between; gap:16px; align-items:flex-end; flex-wrap:wrap; margin-bottom:14px;">
                <div>
                    <h3 style="margin:0 0 6px;">Prerequis pilote</h3>
                    <div class="muted">Ces verifications decident si l essai reel peut commencer sans bricolage de derniere minute.</div>
                </div>
                <div class="badge badge-muted">{{ count($pilotReadiness['prerequisites']) }} controles</div>
            </div>
            <div class="grid">
                @foreach ($pilotReadiness['prerequisites'] as $item)
                    <div class="card" style="padding:16px;">
                        <div style="display:flex; justify-content:space-between; gap:12px; align-items:flex-start; flex-wrap:wrap;">
                            <strong>{{ $item['title'] }}</strong>
                            <span class="badge {{ $item['completed'] ? 'badge-success' : 'badge-warning' }}">{{ $item['completed'] ? 'OK' : 'A corriger' }}</span>
                        </div>
                        <div class="help" style="margin-top:10px;">{{ $item['metric'] }}</div>
                        <div class="muted" style="margin-top:8px;">{{ $item['message'] }}</div>
                        <div style="margin-top:12px;">
                            <a href="{{ $item['route'] }}" class="button button-secondary">{{ $item['action'] }}</a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div style="margin-bottom:18px;">
            <div style="display:flex; justify-content:space-between; gap:16px; align-items:flex-end; flex-wrap:wrap; margin-bottom:14px;">
                <div>
                    <h3 style="margin:0 0 6px;">Qualite des donnees pilotes</h3>
                    <div class="muted">Cette passe evite les blocages de caisse, de stock ou de reassort pendant le test.</div>
                </div>
                <div class="badge badge-muted">{{ count($pilotReadiness['data_quality']) }} indicateurs</div>
            </div>
            <div class="grid">
                @foreach ($pilotReadiness['data_quality'] as $item)
                    <div class="card" style="padding:16px;">
                        <div style="display:flex; justify-content:space-between; gap:12px; align-items:flex-start; flex-wrap:wrap;">
                            <strong>{{ $item['title'] }}</strong>
                            <span class="badge {{ $item['status'] === 'ok' ? 'badge-success' : 'badge-warning' }}">{{ $item['value'] }}</span>
                        </div>
                        <div class="muted" style="margin-top:8px;">{{ $item['message'] }}</div>
                        <div style="margin-top:12px;">
                            <a href="{{ $item['route'] }}" class="button button-secondary">{{ $item['action'] }}</a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div style="margin-bottom:18px;">
            <div style="display:flex; justify-content:space-between; gap:16px; align-items:flex-end; flex-wrap:wrap; margin-bottom:14px;">
                <div>
                    <h3 style="margin:0 0 6px;">Bloquants a lever</h3>
                    <div class="muted">Le pilote reel ne doit pas demarrer tant que ces points ne sont pas corriges.</div>
                </div>
                <div class="badge {{ $pilotReadiness['blockers_count'] === 0 ? 'badge-success' : 'badge-warning' }}">{{ $pilotReadiness['blockers_count'] }} bloquant(s)</div>
            </div>
            @if ($pilotReadiness['blockers_count'] === 0)
                <div class="summary-box">
                    <strong>Aucun point bloquant</strong>
                    <div class="help" style="margin-top:8px;">Tu peux passer au pilote terrain avec la caisse, le stock, les roles et les paiements deja prets.</div>
                </div>
            @else
                <div class="grid">
                    @foreach ($pilotReadiness['blockers'] as $blocker)
                        <div class="card" style="padding:16px; border-color:#efb78f; background:rgba(255, 246, 239, 0.94);">
                            <strong>{{ $blocker['title'] }}</strong>
                            <div class="muted" style="margin-top:8px;">{{ $blocker['message'] }}</div>
                            <div style="margin-top:12px;">
                                <a href="{{ $blocker['route'] }}" class="button button-secondary">{{ $blocker['action'] }}</a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div>
            <div style="display:flex; justify-content:space-between; gap:16px; align-items:flex-end; flex-wrap:wrap; margin-bottom:14px;">
                <div>
                    <h3 style="margin:0 0 6px;">Parcours terrain a valider</h3>
                    <div class="muted">Ce mini-runbook aide a valider le pilote avant de passer au vrai quotidien.</div>
                </div>
                <div class="badge badge-muted">{{ count($pilotReadiness['validation_runs']) }} scenarios</div>
            </div>
            <div class="grid">
                @foreach ($pilotReadiness['validation_runs'] as $run)
                    <div class="card" style="padding:16px;">
                        <div style="display:flex; justify-content:space-between; gap:12px; align-items:flex-start; flex-wrap:wrap;">
                            <strong>{{ $run['title'] }}</strong>
                            <a href="{{ $run['route'] }}" class="button button-secondary">{{ $run['action'] }}</a>
                        </div>
                        <div class="muted" style="margin-top:8px;">{{ $run['description'] }}</div>
                        <div style="display:grid; gap:8px; margin-top:12px;">
                            @foreach ($run['steps'] as $index => $step)
                                <div class="help">{{ $index + 1 }}. {{ $step }}</div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="card" style="margin-bottom:20px;">
        <h2 style="margin-top:0;">Parcours recommande</h2>
        <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
            <div class="card" style="padding:16px;">
                <strong>1. Choisir le secteur</strong>
                <div class="muted" style="margin-top:8px;">Valide le profil metier puis applique le starter pack adapte.</div>
                <div style="margin-top:12px;"><a href="#starter-pack" class="button button-secondary">Voir le starter</a></div>
            </div>
            <div class="card" style="padding:16px;">
                <strong>2. Charger la demo</strong>
                <div class="muted" style="margin-top:8px;">Pose le jeu d essai metier pour explorer l ERP tout de suite.</div>
                <div style="margin-top:12px;"><a href="#demo-data" class="button button-secondary">Voir la demo</a></div>
            </div>
            <div class="card" style="padding:16px;">
                <strong>3. Verrouiller l essai reel</strong>
                <div class="muted" style="margin-top:8px;">Controle roles, paiements, catalogue et stock avant le premier vrai pilote.</div>
                <div style="margin-top:12px;"><a href="#pilot-readiness" class="button button-secondary">Voir l essai reel</a></div>
            </div>
            <div class="card" style="padding:16px;">
                <strong>4. Charger les donnees reelles</strong>
                <div class="muted" style="margin-top:8px;">Importe ou cree clients, fournisseurs et catalogue apres la demo ou le starter.</div>
                @allowed('imports.manage')
                    <div style="margin-top:12px;"><a href="{{ route('imports.index') }}" class="button button-secondary">Ouvrir les imports</a></div>
                @else
                    <div style="margin-top:12px;"><a href="{{ route('products.index') }}" class="button button-secondary">Voir les produits</a></div>
                @endallowed
            </div>
            <div class="card" style="padding:16px;">
                <strong>5. Initialiser le stock</strong>
                <div class="muted" style="margin-top:8px;">Utilise l import en lot ou la saisie unitaire selon ton volume.</div>
                <div style="margin-top:12px;"><a href="{{ route('stock.opening.create') }}" class="button button-secondary">Initialiser le stock</a></div>
            </div>
            <div class="card" style="padding:16px;">
                <strong>6. Lancer l activite</strong>
                <div class="muted" style="margin-top:8px;">Passe la premiere vente ou le premier achat puis controle les rapports.</div>
                <div style="margin-top:12px;"><a href="{{ route('reports.index') }}" class="button button-secondary">Voir les rapports</a></div>
            </div>
        </div>
    </section>

    @allowed('imports.manage')
        <section class="card" style="margin-bottom:20px; border-color:#d7c08b; background:linear-gradient(135deg, #fffaf0 0%, #fff4dd 100%);">
            <div style="display:flex; justify-content:space-between; gap:18px; align-items:flex-start; flex-wrap:wrap;">
                <div style="max-width:760px;">
                    <div class="badge badge-warning">Onboarding de donnees</div>
                    <h2 style="margin:12px 0 8px;">Charge rapidement tes donnees de base avant le stock initial.</h2>
                    <div class="muted">Le centre d imports fournit les modeles CSV pour les clients, fournisseurs, produits et stock initial.</div>
                </div>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <a href="{{ route('imports.index') }}" class="button button-primary">Ouvrir le centre d imports</a>
                    <a href="{{ route('imports.templates.download', 'customers') }}" class="button button-secondary">Modele clients</a>
                    <a href="{{ route('imports.templates.download', 'suppliers') }}" class="button button-secondary">Modele fournisseurs</a>
                    <a href="{{ route('imports.templates.download', 'products') }}" class="button button-secondary">Modele produits</a>
                    <a href="{{ route('imports.templates.download', 'opening-stock') }}" class="button button-secondary">Modele stock</a>
                </div>
            </div>
        </section>
    @endallowed

    <section class="grid">
        @foreach ($summary['steps'] as $step)
            <div class="card" style="padding:18px;">
                <div style="display:flex; justify-content:space-between; gap:16px; align-items:flex-start; flex-wrap:wrap;">
                    <div style="flex:1; min-width:280px;">
                        <div style="display:flex; align-items:center; gap:10px; margin-bottom:8px; flex-wrap:wrap;">
                            <strong>{{ $step['title'] }}</strong>
                            @if ($step['completed'])
                                <span class="badge badge-success">Terminee</span>
                            @else
                                <span class="badge badge-warning">A faire</span>
                            @endif
                        </div>
                        <div class="muted">{{ $step['description'] }}</div>
                        <div class="help" style="margin-top:10px;">Etat actuel : {{ $step['metric'] }}</div>
                    </div>
                    <div>
                        <a href="{{ $step['route'] }}" class="button {{ $step['completed'] ? 'button-secondary' : 'button-primary' }}">{{ $step['action'] }}</a>
                    </div>
                </div>
            </div>
        @endforeach
    </section>
@endsection
