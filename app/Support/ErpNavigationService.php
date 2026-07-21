<?php

namespace App\Support;

use App\Models\User;
use App\Modules\Core\Company\Services\SectorProfileService;
use App\Modules\Core\Dashboard\Models\UserNavigationFavorite;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ErpNavigationService
{
    private ?bool $favoritesFeatureAvailable = null;

    public function build(?User $user, Request $request, bool $merchantMode = false, ?int $companyId = null): array
    {
        if ($merchantMode || ! $user) {
            return [
                'modules' => [],
                'favorite_modules' => [],
                'favorites_enabled' => false,
                'active_module' => null,
                'active_menu' => [],
                'quick_actions' => [],
                'support_links' => [],
                'breadcrumbs' => [],
            ];
        }

        $favoritesEnabled = $this->favoritesFeatureAvailable();
        $favoriteOrder = $this->favoriteOrderFor($user, $companyId);

        $moduleDefinitions = collect($this->modules());
        $moduleDefinitions = $this->applyBusinessProfile($moduleDefinitions, $companyId);

        if ($this->isPureCashier($user)) {
            $moduleDefinitions = $moduleDefinitions->where('key', 'pos');
        }

        $modules = $moduleDefinitions
            ->filter(fn (array $module): bool => $this->canAccessModule($user, $module))
            ->map(function (array $module) use ($user, $request): array {
                $menu = $this->authorizeItems($user, $module['menu'] ?? [], $request);
                $quickActions = $this->authorizeItems($user, $module['quick_actions'] ?? [], $request, 3);

                return [
                    ...$module,
                    'active' => $this->matchesPatterns($request, $module['patterns'] ?? []),
                    'menu' => $menu,
                    'quick_actions' => $quickActions,
                ];
            })
            ->map(fn (array $module): array => [
                ...$module,
                'favorite' => $favoriteOrder->has($module['key']),
            ])
            ->values();

        $activeModule = $modules->firstWhere('active', true) ?? $modules->first();
        $favoriteModules = $modules
            ->filter(fn (array $module): bool => $module['favorite'] === true)
            ->sortBy(fn (array $module): int => $favoriteOrder->get($module['key'], PHP_INT_MAX))
            ->values();

        return [
            'modules' => $modules->all(),
            'favorite_modules' => $favoriteModules->all(),
            'favorites_enabled' => $favoritesEnabled,
            'active_module' => $activeModule,
            'active_menu' => $activeModule['menu'] ?? [],
            'quick_actions' => $activeModule['quick_actions'] ?? [],
            'support_links' => $this->isPureCashier($user) ? [] : $this->authorizeItems($user, $this->supportLinks(), $request),
            'breadcrumbs' => $this->breadcrumbs($activeModule),
        ];
    }

    private function modules(): array
    {
        return [
            [
                'key' => 'dashboard',
                'label' => 'Tableau de bord',
                'hint' => 'Pilotage, alertes, rapports',
                'icon' => 'gauge',
                'accent' => '#0f766e',
                'surface' => 'rgba(15, 118, 110, 0.12)',
                'border' => 'rgba(15, 118, 110, 0.22)',
                'permission' => 'dashboard.view',
                'url' => route('dashboard'),
                'patterns' => ['dashboard', 'manager.pilot', 'merchant.routine', 'onboarding.*', 'business-guide.*', 'approvals.*', 'reports.*', 'notifications.*', 'automation.*', 'search.index', 'budgets.*'],
                'menu' => [
                    ['label' => 'Dashboard', 'url' => route('dashboard'), 'permission' => 'dashboard.view', 'patterns' => ['dashboard']],
                    ['label' => 'Pilotage manager', 'url' => route('manager.pilot'), 'permission' => 'reports.view', 'patterns' => ['manager.pilot']],
                    ['label' => 'Demarrage', 'url' => route('onboarding.index'), 'permission' => 'dashboard.view', 'patterns' => ['onboarding.*']],
                    ['label' => 'Guide metier', 'url' => route('business-guide.index'), 'permission' => 'dashboard.view', 'patterns' => ['business-guide.*']],
                    ['label' => 'Approbations', 'url' => route('approvals.index'), 'permission' => 'approvals.view', 'patterns' => ['approvals.*']],
                    ['label' => 'Rapports', 'url' => route('reports.index'), 'permission' => 'reports.view', 'patterns' => ['reports.*']],
                    ['label' => 'Alertes', 'url' => route('notifications.index'), 'permission' => 'notifications.view', 'patterns' => ['notifications.*']],
                ],
                'quick_actions' => [
                    ['label' => 'Recherche globale', 'url' => route('search.index'), 'permission' => 'dashboard.view'],
                    ['label' => 'Voir les alertes', 'url' => route('notifications.index', ['scope' => 'active']), 'permission' => 'notifications.view'],
                    ['label' => 'Voir les approbations', 'url' => route('approvals.index'), 'permission' => 'approvals.view'],
                ],
            ],
            [
                'key' => 'sales',
                'label' => 'Ventes',
                'hint' => 'CRM, devis, commandes',
                'icon' => 'orders',
                'accent' => '#f97316',
                'surface' => 'rgba(249, 115, 22, 0.12)',
                'border' => 'rgba(249, 115, 22, 0.22)',
                'permission' => 'quotes.view',
                'url' => route('quotes.index'),
                'patterns' => ['crm.*', 'quotes.*', 'orders.*', 'delivery-notes.*'],
                'menu' => [
                    ['label' => 'CRM', 'url' => route('crm.index'), 'permission' => 'crm.view', 'patterns' => ['crm.*']],
                    ['label' => 'Devis', 'url' => route('quotes.index'), 'permission' => 'quotes.view', 'patterns' => ['quotes.*']],
                    ['label' => 'Commandes', 'url' => route('orders.index'), 'permission' => 'orders.view', 'patterns' => ['orders.*']],
                    ['label' => 'Livraisons', 'url' => route('delivery-notes.index'), 'permission' => 'delivery_notes.view', 'patterns' => ['delivery-notes.*']],
                ],
                'quick_actions' => [
                    ['label' => 'Nouveau devis', 'url' => route('quotes.create'), 'permission' => 'quotes.manage'],
                    ['label' => 'Nouvelle commande', 'url' => route('orders.create'), 'permission' => 'orders.manage'],
                ],
            ],
            [
                'key' => 'pos',
                'label' => 'POS / Caisse',
                'hint' => 'Vente comptoir et sessions',
                'icon' => 'pos',
                'accent' => '#db2777',
                'surface' => 'rgba(219, 39, 119, 0.12)',
                'border' => 'rgba(219, 39, 119, 0.22)',
                'permission' => 'pos.view',
                'url' => route('pos.index'),
                'patterns' => ['pos.*'],
                'menu' => [
                    ['label' => 'Caisse', 'url' => route('pos.index'), 'permission' => 'pos.view', 'patterns' => ['pos.index', 'pos.show', 'pos.sales.*', 'pos.receipt*', 'pos.returns.*']],
                    ['label' => 'Tickets POS', 'url' => route('pos.orders.index'), 'permission' => 'pos.view', 'patterns' => ['pos.orders.*']],
                    ['label' => 'Sessions caisse', 'url' => route('pos.sessions.index'), 'permission' => 'pos.view', 'except_roles' => ['cashier'], 'patterns' => ['pos.sessions.*']],
                    ['label' => 'Paiements POS', 'url' => route('pos.payments.index'), 'permission' => 'pos.view', 'except_roles' => ['cashier'], 'patterns' => ['pos.payments.*']],
                    ['label' => 'Rapport du jour', 'url' => route('pos.report'), 'permission' => 'pos.view', 'except_roles' => ['cashier'], 'patterns' => ['pos.report']],
                    ['label' => 'Config POS', 'url' => route('pos.settings.index'), 'permission' => 'pos.view', 'except_roles' => ['cashier'], 'patterns' => ['pos.settings.*']],
                ],
                'quick_actions' => [
                    ['label' => 'Nouvelle vente POS', 'url' => route('pos.sales.create'), 'permission' => 'pos.manage'],
                    ['label' => 'Ouvrir la caisse', 'url' => route('pos.index'), 'permission' => 'pos.view'],
                ],
            ],
            [
                'key' => 'purchases',
                'label' => 'Achats',
                'hint' => 'Appro, commandes, receptions',
                'icon' => 'buy',
                'accent' => '#d97706',
                'surface' => 'rgba(217, 119, 6, 0.12)',
                'border' => 'rgba(217, 119, 6, 0.22)',
                'permission' => 'purchase_requests.view',
                'url' => route('purchase-requests.index'),
                'patterns' => ['purchase-requests.*', 'replenishments.*', 'purchases.*', 'purchase-credit-notes.*', 'purchase-orders.*', 'goods-receipts.*'],
                'menu' => [
                    ['label' => 'Demandes achat', 'url' => route('purchase-requests.index'), 'permission' => 'purchase_requests.view', 'patterns' => ['purchase-requests.*']],
                    ['label' => 'Reappro', 'url' => route('replenishments.index'), 'permission' => 'purchase_requests.view', 'patterns' => ['replenishments.*']],
                    ['label' => 'Cmd fournisseurs', 'url' => route('purchase-orders.index'), 'permission' => 'purchase_orders.view', 'patterns' => ['purchase-orders.*']],
                    ['label' => 'Achats', 'url' => route('purchases.index'), 'permission' => 'purchases.view', 'patterns' => ['purchases.*']],
                    ['label' => 'Avoirs fournisseurs', 'url' => route('purchase-credit-notes.index'), 'permission' => 'supplier_credit_notes.view', 'patterns' => ['purchase-credit-notes.*']],
                    ['label' => 'Receptions', 'url' => route('goods-receipts.index'), 'permission' => 'goods_receipts.view', 'patterns' => ['goods-receipts.*']],
                ],
                'quick_actions' => [
                    ['label' => 'Nouvelle demande', 'url' => route('purchase-requests.create'), 'permission' => 'purchase_requests.manage'],
                    ['label' => 'Nouvel achat', 'url' => route('purchases.create'), 'permission' => 'purchases.manage'],
                    ['label' => 'Nouvelle commande fournisseur', 'url' => route('purchase-orders.create'), 'permission' => 'purchase_orders.manage'],
                ],
            ],
            [
                'key' => 'stock',
                'label' => 'Stock',
                'hint' => 'Disponibilite et mouvements',
                'icon' => 'stock',
                'accent' => '#16a34a',
                'surface' => 'rgba(22, 163, 74, 0.12)',
                'border' => 'rgba(22, 163, 74, 0.22)',
                'permission' => 'stock.view',
                'url' => route('stock.index'),
                'patterns' => ['stock.*', 'stock-counts.*', 'transfers.*', 'warehouses.*'],
                'menu' => [
                    ['label' => 'Stock', 'url' => route('stock.index'), 'permission' => 'stock.view', 'patterns' => ['stock.index', 'stock.show']],
                    ['label' => 'Lots', 'url' => route('stock.lots'), 'permission' => 'stock.view', 'patterns' => ['stock.lots']],
                    ['label' => 'Mouvements', 'url' => route('stock.movements'), 'permission' => 'stock.view', 'patterns' => ['stock.movements']],
                    ['label' => 'Inventaires', 'url' => route('stock-counts.index'), 'permission' => 'stock_counts.view', 'patterns' => ['stock-counts.*']],
                    ['label' => 'Transferts', 'url' => route('transfers.index'), 'permission' => 'transfers.view', 'patterns' => ['transfers.*']],
                ],
                'quick_actions' => [
                    ['label' => 'Ajuster le stock', 'url' => route('stock.adjustments.create'), 'permission' => 'stock.manage'],
                    ['label' => 'Inventaire rapide', 'url' => route('stock-counts.quick'), 'permission' => 'stock_counts.manage'],
                    ['label' => 'Nouveau transfert', 'url' => route('transfers.create'), 'permission' => 'transfers.manage'],
                ],
            ],
            [
                'key' => 'products',
                'label' => 'Produits',
                'hint' => 'Catalogue et categories',
                'icon' => 'stock',
                'accent' => '#059669',
                'surface' => 'rgba(5, 150, 105, 0.12)',
                'border' => 'rgba(5, 150, 105, 0.22)',
                'permission' => 'products.view',
                'url' => route('products.index'),
                'patterns' => ['products.*', 'categories.*', 'product-attributes.*'],
                'menu' => [
                    ['label' => 'Produits', 'url' => route('products.index'), 'permission' => 'products.view', 'patterns' => ['products.*']],
                    ['label' => 'Categories', 'url' => route('categories.index'), 'permission' => 'categories.view', 'patterns' => ['categories.*']],
                ],
                'quick_actions' => [
                    ['label' => 'Nouveau produit', 'url' => route('products.create'), 'permission' => 'products.manage'],
                ],
            ],
            [
                'key' => 'customers',
                'label' => 'Clients',
                'hint' => 'Fichier client et relation',
                'icon' => 'customer',
                'accent' => '#ec4899',
                'surface' => 'rgba(236, 72, 153, 0.12)',
                'border' => 'rgba(236, 72, 153, 0.22)',
                'permission' => 'customers.view',
                'url' => route('customers.index'),
                'patterns' => ['customers.*'],
                'menu' => [
                    ['label' => 'Clients', 'url' => route('customers.index'), 'permission' => 'customers.view', 'patterns' => ['customers.*']],
                ],
                'quick_actions' => [
                    ['label' => 'Nouveau client', 'url' => route('customers.create'), 'permission' => 'customers.manage'],
                ],
            ],
            [
                'key' => 'suppliers',
                'label' => 'Fournisseurs',
                'hint' => 'Base fournisseurs',
                'icon' => 'team',
                'accent' => '#475569',
                'surface' => 'rgba(71, 85, 105, 0.12)',
                'border' => 'rgba(71, 85, 105, 0.22)',
                'permission' => 'suppliers.view',
                'url' => route('suppliers.index'),
                'patterns' => ['suppliers.*'],
                'menu' => [
                    ['label' => 'Fournisseurs', 'url' => route('suppliers.index'), 'permission' => 'suppliers.view', 'patterns' => ['suppliers.*']],
                ],
                'quick_actions' => [
                    ['label' => 'Nouveau fournisseur', 'url' => route('suppliers.create'), 'permission' => 'suppliers.manage'],
                ],
            ],
            [
                'key' => 'billing',
                'label' => 'Facturation',
                'hint' => 'Factures, paiements, relances',
                'icon' => 'document',
                'accent' => '#2563eb',
                'surface' => 'rgba(37, 99, 235, 0.12)',
                'border' => 'rgba(37, 99, 235, 0.22)',
                'permission' => 'sales.view',
                'url' => route('sales.index'),
                'patterns' => ['sales.*', 'credit-notes.*', 'payments.*', 'collections.*'],
                'menu' => [
                    ['label' => 'Factures', 'url' => route('sales.index'), 'permission' => 'sales.view', 'patterns' => ['sales.*']],
                    ['label' => 'Avoirs', 'url' => route('credit-notes.index'), 'permission' => 'credit_notes.view', 'patterns' => ['credit-notes.*']],
                    ['label' => 'Paiements', 'url' => route('payments.index'), 'permission' => 'payments.view', 'patterns' => ['payments.*']],
                    ['label' => 'Recouvrement', 'url' => route('collections.index'), 'permission' => 'collections.view', 'patterns' => ['collections.*']],
                ],
                'quick_actions' => [
                    ['label' => 'Nouvelle facture', 'url' => route('sales.create'), 'permission' => 'sales.manage'],
                    ['label' => 'Nouvel encaissement', 'url' => route('payments.create', ['type' => 'customer_receipt']), 'permission' => 'payments.validate'],
                ],
            ],
            [
                'key' => 'accounting',
                'label' => 'Comptabilite simple',
                'hint' => 'Comptes, depenses, rapprochements',
                'icon' => 'report',
                'accent' => '#4f46e5',
                'surface' => 'rgba(79, 70, 229, 0.12)',
                'border' => 'rgba(79, 70, 229, 0.22)',
                'permission' => 'accounting.view',
                'url' => route('accounting.accounts.index'),
                'patterns' => ['cash-accounts.*', 'treasury-reconciliations.*', 'expenses.*', 'expense-categories.*', 'accounting.*', 'fixed-assets.*'],
                'menu' => [
                    ['label' => 'Comptes', 'url' => route('cash-accounts.index'), 'permission' => 'cash_accounts.view', 'patterns' => ['cash-accounts.*']],
                    ['label' => 'Rapprochements', 'url' => route('treasury-reconciliations.index'), 'permission' => 'reconciliations.view', 'patterns' => ['treasury-reconciliations.*']],
                    ['label' => 'Depenses', 'url' => route('expenses.index'), 'permission' => 'expenses.view', 'patterns' => ['expenses.*', 'expense-categories.*']],
                    ['label' => 'Journaux', 'url' => route('accounting.journal-entries.index'), 'permission' => 'accounting.view', 'patterns' => ['accounting.journal-entries.*']],
                    ['label' => 'Resultat', 'url' => route('accounting.profit-loss.index'), 'permission' => 'accounting.view', 'patterns' => ['accounting.profit-loss.*', 'accounting.balance-sheet.*', 'accounting.balance.*']],
                ],
                'quick_actions' => [
                    ['label' => 'Nouvelle depense', 'url' => route('expenses.create'), 'permission' => 'expenses.manage'],
                    ['label' => 'Plan comptable', 'url' => route('accounting.accounts.index'), 'permission' => 'accounting.view'],
                ],
            ],
            [
                'key' => 'hr',
                'label' => 'RH',
                'hint' => 'Equipe et paie',
                'icon' => 'team',
                'accent' => '#9333ea',
                'surface' => 'rgba(147, 51, 234, 0.12)',
                'border' => 'rgba(147, 51, 234, 0.22)',
                'permission' => 'hr.view',
                'url' => route('hr.index'),
                'patterns' => ['hr.*', 'payroll.*'],
                'menu' => [
                    ['label' => 'Capital humain', 'url' => route('hr.index'), 'permission' => 'hr.view', 'patterns' => ['hr.*']],
                    ['label' => 'Paie', 'url' => route('payroll.index'), 'permission' => 'payroll.view', 'patterns' => ['payroll.*']],
                ],
                'quick_actions' => [
                    ['label' => 'Ouvrir RH', 'url' => route('hr.index'), 'permission' => 'hr.view'],
                ],
            ],
            [
                'key' => 'settings',
                'label' => 'Parametres generaux',
                'hint' => 'Societe et acces',
                'icon' => 'settings',
                'accent' => '#334155',
                'surface' => 'rgba(51, 65, 85, 0.12)',
                'border' => 'rgba(51, 65, 85, 0.22)',
                'permission' => 'settings.view',
                'url' => route('settings.index'),
                'patterns' => ['settings.*', 'companies.*', 'branches.*', 'users.*', 'roles.*', 'imports.*', 'ops.*', 'platform.*', 'activity-logs.*'],
                'menu' => [
                    ['label' => 'Parametres generaux', 'url' => route('settings.index'), 'permission' => 'settings.view', 'patterns' => ['settings.*', 'companies.*', 'branches.*', 'users.*', 'roles.*']],
                ],
                'quick_actions' => [
                    ['label' => 'Nouvel utilisateur', 'url' => route('users.create'), 'permission' => 'users.manage'],
                    ['label' => 'Imports Excel/CSV', 'url' => route('imports.index'), 'permission' => 'imports.manage'],
                ],
            ],
        ];
    }

    private function applyBusinessProfile(Collection $modules, ?int $companyId): Collection
    {
        if (! $companyId) {
            return $modules;
        }

        $profile = app(SectorProfileService::class)->profileForCompany($companyId);
        $profileKey = $profile['key'] ?? SectorProfileService::DEFAULT_PROFILE;
        $moduleOrder = $this->businessModuleOrder($profile['recommended_modules'] ?? []);
        $labels = $this->businessModuleLabels($profileKey);

        return $modules
            ->map(function (array $module) use ($labels): array {
                if (! isset($labels[$module['key']])) {
                    return $module;
                }

                return array_replace($module, $labels[$module['key']]);
            })
            ->sortBy(fn (array $module): int => $moduleOrder[$module['key']] ?? 90)
            ->values();
    }

    private function businessModuleOrder(array $recommendedModules): array
    {
        $map = [
            'ventes' => 'sales',
            'achats' => 'purchases',
            'stock' => 'stock',
            'caisse/POS' => 'pos',
            'facturation' => 'billing',
            'clients' => 'customers',
            'fournisseurs' => 'suppliers',
            'depenses' => 'accounting',
            'employes' => 'hr',
            'rapports' => 'dashboard',
            'paiements' => 'billing',
            'alertes' => 'dashboard',
            'documents' => 'dashboard',
        ];

        return collect($recommendedModules)
            ->map(fn (string $module): ?string => $map[$module] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->flip()
            ->map(fn (int $position): int => $position)
            ->all();
    }

    private function businessModuleLabels(string $profileKey): array
    {
        return match ($profileKey) {
            'restaurant_cafe' => [
                'pos' => ['label' => 'Caisse restaurant', 'hint' => 'Commandes, service, tickets'],
                'products' => ['label' => 'Menus', 'hint' => 'Plats, boissons, ingredients'],
                'stock' => ['label' => 'Stock cuisine', 'hint' => 'Ingredients et boissons'],
                'customers' => ['label' => 'Clients restaurant', 'hint' => 'Comptoir, livraison, groupes'],
                'accounting' => ['label' => 'Depenses cuisine', 'hint' => 'Achats, charges, caisse'],
            ],
            'school_training' => [
                'billing' => ['label' => 'Factures scolaires', 'hint' => 'Frais, mensualites, recus'],
                'customers' => ['label' => 'Eleves et parents', 'hint' => 'Dossiers, payeurs, contacts'],
                'sales' => ['label' => 'Inscriptions', 'hint' => 'Devis, dossiers, suivi'],
                'accounting' => ['label' => 'Depenses ecole', 'hint' => 'Charges et paiements'],
                'hr' => ['label' => 'Personnel', 'hint' => 'Equipe et enseignants'],
            ],
            'food_store', 'general_trade' => [
                'pos' => ['label' => 'Caisse boutique', 'hint' => 'Tickets et encaissements'],
                'products' => ['label' => 'Produits boutique', 'hint' => 'Rayons, codes-barres, prix'],
                'stock' => ['label' => 'Stock magasin', 'hint' => 'Ruptures, inventaires, mouvements'],
                'billing' => ['label' => 'Facturation', 'hint' => 'Factures, paiements, relances'],
            ],
            'auto_parts_garage' => [
                'sales' => ['label' => 'Devis garage', 'hint' => 'Reparations, pieces, main-d oeuvre'],
                'products' => ['label' => 'Pieces et services', 'hint' => 'Pieces, forfaits, interventions'],
                'stock' => ['label' => 'Stock pieces', 'hint' => 'Disponibilite atelier et ruptures'],
                'purchases' => ['label' => 'Achats pieces', 'hint' => 'Approvisionnement atelier'],
                'billing' => ['label' => 'Factures garage', 'hint' => 'Factures, paiements, restes'],
                'customers' => ['label' => 'Clients vehicules', 'hint' => 'Proprietaires, dossiers, suivi'],
                'accounting' => ['label' => 'Depenses atelier', 'hint' => 'Charges et achats techniques'],
            ],
            'pharmacy_parapharmacy' => [
                'pos' => ['label' => 'Comptoir pharmacie', 'hint' => 'Ventes rapides et tickets'],
                'products' => ['label' => 'Produits sante', 'hint' => 'Lots, prix, ordonnances'],
                'stock' => ['label' => 'Lots et peremption', 'hint' => 'Dates, ruptures, stock critique'],
                'purchases' => ['label' => 'Reappro pharmacie', 'hint' => 'Commandes et fournisseurs'],
                'billing' => ['label' => 'Factures comptoir', 'hint' => 'Factures et paiements'],
                'customers' => ['label' => 'Patients / clients', 'hint' => 'Contacts et historique'],
            ],
            'wholesale_distribution' => [
                'stock' => ['label' => 'Depots et transferts', 'hint' => 'Entrepots, sorties, mouvements'],
                'sales' => ['label' => 'Sorties / commandes', 'hint' => 'Commandes clients et livraisons'],
                'purchases' => ['label' => 'Reappro depot', 'hint' => 'Commandes fournisseurs'],
                'products' => ['label' => 'Articles stock', 'hint' => 'Lots, cartons, seuils'],
                'suppliers' => ['label' => 'Fournisseurs depot', 'hint' => 'Approvisionnement et delais'],
            ],
            'services_agency' => [
                'sales' => ['label' => 'Devis et missions', 'hint' => 'Prestations, offres, suivi'],
                'billing' => ['label' => 'Factures prestation', 'hint' => 'Acomptes, paiements, relances'],
                'customers' => ['label' => 'Clients / comptes', 'hint' => 'Portefeuille et contrats'],
                'accounting' => ['label' => 'Depenses mission', 'hint' => 'Frais et charges clients'],
                'hr' => ['label' => 'Equipe service', 'hint' => 'Intervenants et responsables'],
            ],
            'beauty_salon' => [
                'pos' => ['label' => 'Caisse salon', 'hint' => 'Prestations et encaissements'],
                'products' => ['label' => 'Services et produits', 'hint' => 'Coiffure, soins, articles'],
                'customers' => ['label' => 'Clients salon', 'hint' => 'Habitudes et fidelite'],
                'stock' => ['label' => 'Stock consommables', 'hint' => 'Produits utilises au salon'],
                'hr' => ['label' => 'Equipe salon', 'hint' => 'Coiffeurs et planning'],
            ],
            'workshop_manufacturing' => [
                'products' => ['label' => 'Articles fabriques', 'hint' => 'Produits, matieres, nomenclatures'],
                'manufacturing' => ['label' => 'Atelier production', 'hint' => 'Ordres et fabrication'],
                'stock' => ['label' => 'Matieres et stock', 'hint' => 'Entrees, sorties, composants'],
                'sales' => ['label' => 'Commandes atelier', 'hint' => 'Demandes clients et devis'],
                'purchases' => ['label' => 'Achats matieres', 'hint' => 'Approvisionnement production'],
            ],
            'delivery_company' => [
                'sales' => ['label' => 'Courses et livraisons', 'hint' => 'Commandes, courses, clients'],
                'billing' => ['label' => 'Facturation livraison', 'hint' => 'Frais, paiements, restes'],
                'customers' => ['label' => 'Expediteurs / clients', 'hint' => 'Comptes et contacts'],
                'hr' => ['label' => 'Livreurs', 'hint' => 'Equipe terrain et suivi'],
                'reports' => ['label' => 'Rapports livraison', 'hint' => 'Chiffres et performance'],
            ],
            default => [],
        };
    }

    private function favoriteOrderFor(User $user, ?int $companyId = null): Collection
    {
        if (! $companyId || ! $this->favoritesFeatureAvailable()) {
            return collect();
        }

        return UserNavigationFavorite::query()
            ->where('company_id', $companyId)
            ->where('user_id', $user->id)
            ->orderBy('sort_order')
            ->pluck('sort_order', 'module_key');
    }

    private function favoritesFeatureAvailable(): bool
    {
        return $this->favoritesFeatureAvailable ??= Schema::hasTable('user_navigation_favorites');
    }

    private function supportLinks(): array
    {
        return [
            ['label' => 'Guide metier', 'url' => route('business-guide.index'), 'permission' => 'dashboard.view', 'patterns' => ['business-guide.*']],
            ['label' => 'Plan comptable', 'url' => route('accounting.accounts.index'), 'permission' => 'accounting.view', 'patterns' => ['accounting.*']],
            ['label' => 'Roles et permissions', 'url' => route('roles.index'), 'permission' => 'roles.view', 'patterns' => ['roles.*']],
            ['label' => 'Imports Excel/CSV', 'url' => route('imports.index'), 'permission' => 'imports.manage', 'patterns' => ['imports.*']],
            ['label' => 'Operations', 'url' => route('ops.index'), 'permission' => 'ops.view', 'patterns' => ['ops.*']],
            ['label' => 'Journaux d activite', 'url' => route('activity-logs.index'), 'permission' => 'activity_logs.view', 'patterns' => ['activity-logs.*']],
        ];
    }

    private function authorizeItems(User $user, array $items, Request $request, ?int $limit = null): array
    {
        $authorized = collect($items)
            ->filter(fn (array $item): bool => $this->canAccess($user, $item['permission'] ?? null)
                && ! collect($item['except_roles'] ?? [])->contains(fn (string $role): bool => $user->hasRole($role)))
            ->map(fn (array $item): array => [
                ...$item,
                'active' => $this->matchesPatterns($request, $item['patterns'] ?? []),
            ]);

        if ($limit !== null) {
            $authorized = $authorized->take($limit);
        }

        return $authorized->values()->all();
    }

    private function breadcrumbs(?array $activeModule): array
    {
        if (! $activeModule) {
            return [];
        }

        $breadcrumbs = [
            ['label' => 'Applications', 'url' => $activeModule['key'] === 'pos' ? route('pos.index') : route('dashboard')],
        ];

        if (($activeModule['key'] ?? null) !== 'dashboard') {
            $breadcrumbs[] = [
                'label' => $activeModule['label'],
                'url' => $activeModule['url'],
            ];
        }

        $activeMenu = collect($activeModule['menu'] ?? [])->firstWhere('active', true);

        if ($activeMenu && ($activeMenu['label'] ?? null) !== ($activeModule['label'] ?? null)) {
            $breadcrumbs[] = [
                'label' => $activeMenu['label'],
                'url' => $activeMenu['url'] ?? null,
            ];
        }

        return $breadcrumbs;
    }

    private function canAccess(User $user, ?string $permission): bool
    {
        return ! $permission || $user->hasPermission($permission);
    }

    private function canAccessModule(User $user, array $module): bool
    {
        if ($this->canAccess($user, $module['permission'] ?? null)) {
            return true;
        }

        foreach (['menu', 'quick_actions'] as $bucket) {
            foreach ($module[$bucket] ?? [] as $item) {
                if ($this->canAccess($user, $item['permission'] ?? null)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isPureCashier(User $user): bool
    {
        if (! $user->hasRole('cashier')) {
            return false;
        }

        foreach (['platform_admin', 'company_admin', 'director', 'manager', 'pos_supervisor'] as $role) {
            if ($user->hasRole($role)) {
                return false;
            }
        }

        return true;
    }

    private function matchesPatterns(Request $request, array|string $patterns): bool
    {
        $patterns = is_array($patterns) ? $patterns : [$patterns];

        foreach ($patterns as $pattern) {
            if ($pattern !== null && $request->routeIs($pattern)) {
                return true;
            }
        }

        return false;
    }
}
