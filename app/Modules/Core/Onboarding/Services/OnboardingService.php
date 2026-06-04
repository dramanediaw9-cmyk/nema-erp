<?php

namespace App\Modules\Core\Onboarding\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Core\Company\Models\Setting;
use App\Modules\Core\Company\Services\SectorProfileService;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Partners\Models\Partner;
use App\Modules\Purchases\Models\PurchaseBill;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Treasury\Models\CashAccount;

class OnboardingService
{
    public function __construct(
        private readonly SectorProfileService $sectorProfileService,
        private readonly SectorStarterService $sectorStarterService,
        private readonly SectorDemoDataService $sectorDemoDataService,
        private readonly PilotReadinessService $pilotReadinessService,
    ) {}

    public function summary(int $companyId): array
    {
        $sectorProfile = $this->sectorProfileService->profileForCompany($companyId);
        $sectorStarter = $this->sectorStarterService->status($companyId);
        $sectorDemoData = $this->sectorDemoDataService->status($companyId);
        $pilotReadiness = $this->pilotReadinessService->summary($companyId);
        $steps = $this->steps($companyId, $sectorProfile, $sectorStarter, $sectorDemoData, $pilotReadiness);
        $completed = collect($steps)->where('completed', true)->count();
        $total = count($steps);
        $progress = $total > 0 ? (int) round(($completed / $total) * 100) : 0;

        return [
            'steps' => $steps,
            'launch_plan' => $this->launchPlan($steps),
            'completed' => $completed,
            'total' => $total,
            'progress' => $progress,
            'is_complete' => $completed === $total,
            'next_step' => collect($steps)->firstWhere('completed', false),
            'sector_profile' => $sectorProfile,
            'sector_starter' => $sectorStarter,
            'sector_demo_data' => $sectorDemoData,
            'pilot_readiness' => $pilotReadiness,
        ];
    }

    public function isDashboardBannerDismissed(int $companyId): bool
    {
        $setting = Setting::query()->where('company_id', $companyId)->where('key', 'onboarding')->first();

        return filled($setting?->value['dashboard_dismissed_at'] ?? null);
    }

    public function dismissDashboardBanner(int $companyId): void
    {
        Setting::query()->updateOrCreate(
            ['company_id' => $companyId, 'key' => 'onboarding'],
            ['value' => ['dashboard_dismissed_at' => now()->toDateTimeString()]]
        );
    }

    public function reopenDashboardBanner(int $companyId): void
    {
        Setting::query()->updateOrCreate(
            ['company_id' => $companyId, 'key' => 'onboarding'],
            ['value' => ['dashboard_dismissed_at' => null]]
        );
    }

    private function launchPlan(array $steps): array
    {
        $byKey = collect($steps)->keyBy('key');

        return [
            [
                'minute' => '0-2 min',
                'title' => 'Entreprise prete',
                'promise' => 'Le nom, la devise, le pays et les informations legales sont poses.',
                'step' => $byKey->get('company_profile'),
            ],
            [
                'minute' => '2-4 min',
                'title' => 'Agence et caisse',
                'promise' => 'La boutique pilote et les comptes caisse/mobile money sont utilisables.',
                'step' => $byKey->get('cash_accounts') ?: $byKey->get('branches'),
            ],
            [
                'minute' => '4-7 min',
                'title' => 'Catalogue vendable',
                'promise' => 'Les premiers articles, prix et unites sont prets pour la caisse.',
                'step' => $byKey->get('products'),
            ],
            [
                'minute' => '7-10 min',
                'title' => 'Stock initial',
                'promise' => 'Le stock de depart evite les ecarts des la premiere journee.',
                'step' => $byKey->get('stock'),
            ],
            [
                'minute' => '10-13 min',
                'title' => 'Premiere operation',
                'promise' => 'Une vente, un achat ou une depense confirme que le cycle ERP tourne.',
                'step' => $byKey->get('operations'),
            ],
            [
                'minute' => '13-15 min',
                'title' => 'Pilotage dirigeant',
                'promise' => 'Le dashboard et les rapports montrent deja ce qui se passe.',
                'step' => [
                    'route' => route('reports.index'),
                    'action' => 'Voir les rapports',
                    'completed' => (bool) ($byKey->get('operations')['completed'] ?? false),
                    'metric' => 'Dashboard, rapports et alertes',
                ],
            ],
        ];
    }

    private function steps(int $companyId, array $sectorProfile, array $sectorStarter, array $sectorDemoData, array $pilotReadiness): array
    {
        $company = Company::query()->find($companyId);
        $generalSetting = Setting::query()->where('company_id', $companyId)->where('key', 'general')->first();
        $sectorProfileConfigured = $this->sectorProfileService->isExplicitlyConfigured($companyId);
        $hasProfile = $company
            && filled($company->name)
            && filled($company->currency_code)
            && filled($company->address)
            && filled($generalSetting?->value['country'] ?? null);

        $branchCount = Branch::query()->where('company_id', $companyId)->count();
        $cashAccountCount = CashAccount::query()->where('company_id', $companyId)->count();
        $customerCount = Partner::query()->customers()->where('company_id', $companyId)->count();
        $supplierCount = Partner::query()->suppliers()->where('company_id', $companyId)->count();
        $productCount = Product::query()->where('company_id', $companyId)->count();
        $stockMovementCount = StockMovement::query()->where('company_id', $companyId)->count();
        $salesCount = SalesInvoice::query()->where('company_id', $companyId)->count();
        $purchaseCount = PurchaseBill::query()->where('company_id', $companyId)->count();
        $expenseCount = Expense::query()->where('company_id', $companyId)->count();

        return [
            [
                'key' => 'company_profile',
                'title' => 'Completer le profil societe',
                'description' => 'Verifier les coordonnees, la devise, l adresse et les informations legales.',
                'route' => route('settings.index'),
                'action' => 'Ouvrir les parametres',
                'completed' => $hasProfile,
                'metric' => $hasProfile ? 'Profil complet' : 'Profil incomplet',
            ],
            [
                'key' => 'sector_profile',
                'title' => 'Choisir le profil secteur',
                'description' => 'Positionner le metier principal pour que Nema ERP recommande les bons modules et le bon pack de depart.',
                'route' => route('settings.index'),
                'action' => 'Choisir le profil',
                'completed' => $sectorProfileConfigured,
                'metric' => $sectorProfileConfigured ? $sectorProfile['label'] : 'Profil par defaut encore actif',
            ],
            [
                'key' => 'sector_starter',
                'title' => 'Appliquer le starter pack secteur',
                'description' => 'Preparer automatiquement categories, depenses, listes de prix, unites conseillees et paiements terrain pour '.$sectorProfile['label'].'.',
                'route' => route('onboarding.index').'#starter-pack',
                'action' => $sectorStarter['is_applied'] ? 'Voir le pack' : 'Appliquer le pack',
                'completed' => $sectorStarter['is_applied'],
                'metric' => $this->sectorStarterMetric($sectorStarter),
            ],
            [
                'key' => 'sector_demo_data',
                'title' => 'Charger la demo secteur',
                'description' => 'Poser un jeu d essai complet avec tiers, produits, tarifs, stock et parcours de test adaptes a '.$sectorProfile['label'].'.',
                'route' => route('onboarding.index').'#demo-data',
                'action' => $sectorDemoData['is_applied'] ? 'Voir la demo' : 'Charger la demo',
                'completed' => $sectorDemoData['is_applied'],
                'metric' => $this->sectorDemoMetric($sectorDemoData),
            ],
            [
                'key' => 'pilot_readiness',
                'title' => 'Valider l essai reel terrain',
                'description' => 'Verifier que la caisse, le catalogue, le stock et les roles sont vraiment prets avant un pilote en boutique ou chez un client.',
                'route' => route('onboarding.index').'#pilot-readiness',
                'action' => 'Voir l essai reel',
                'completed' => $pilotReadiness['is_ready'],
                'metric' => $this->pilotReadinessMetric($pilotReadiness),
            ],
            [
                'key' => 'branches',
                'title' => 'Configurer au moins une agence',
                'description' => 'Les ventes, achats et stocks sont rattaches a une agence active.',
                'route' => route('branches.index'),
                'action' => 'Gerer les agences',
                'completed' => $branchCount > 0,
                'metric' => $branchCount.' agence(s)',
            ],
            [
                'key' => 'cash_accounts',
                'title' => 'Ajouter les comptes de tresorerie',
                'description' => 'Caisse, banque et mobile money servent aux encaissements et decaissements.',
                'route' => route('cash-accounts.index'),
                'action' => 'Configurer les comptes',
                'completed' => $cashAccountCount > 0,
                'metric' => $cashAccountCount.' compte(s)',
            ],
            [
                'key' => 'partners',
                'title' => 'Renseigner clients et fournisseurs',
                'description' => 'Le fichier tiers est necessaire pour les ventes, achats et depenses.',
                'route' => route('customers.index'),
                'action' => 'Voir les tiers',
                'completed' => $customerCount > 0 && $supplierCount > 0,
                'metric' => $customerCount.' client(s) / '.$supplierCount.' fournisseur(s)',
            ],
            [
                'key' => 'products',
                'title' => 'Creer le catalogue produits',
                'description' => 'Ajoute les articles vendus ou suivis en stock avant de facturer.',
                'route' => route('products.index'),
                'action' => 'Gerer les produits',
                'completed' => $productCount > 0,
                'metric' => $productCount.' produit(s)',
            ],
            [
                'key' => 'stock',
                'title' => 'Initialiser le stock',
                'description' => 'Saisir le stock de depart permet de vendre sans ecarts.',
                'route' => route('stock.opening.create'),
                'action' => 'Saisir le stock initial',
                'completed' => $stockMovementCount > 0,
                'metric' => $stockMovementCount.' mouvement(s)',
            ],
            [
                'key' => 'operations',
                'title' => 'Enregistrer les premieres operations',
                'description' => 'Valider au moins une vente ou un achat ou une depense pour lancer le cycle ERP.',
                'route' => route('dashboard'),
                'action' => 'Revenir au pilotage',
                'completed' => $salesCount > 0 || $purchaseCount > 0 || $expenseCount > 0,
                'metric' => $salesCount.' vente(s) / '.$purchaseCount.' achat(s) / '.$expenseCount.' depense(s)',
            ],
        ];
    }

    private function sectorStarterMetric(array $status): string
    {
        $counts = [
            $status['categories_count'].' cat.',
            $status['expense_categories_count'].' depenses',
            $status['price_lists_count'].' tarifs',
            $status['payment_terms_count'].' conditions',
        ];

        $gatewayPart = $status['recommended_gateways_count'] > 0
            ? $status['recommended_gateways_ready'].'/'.$status['recommended_gateways_count'].' paiements terrain'
            : '0 paiement terrain';

        if ($status['is_applied']) {
            return 'Pack pose : '.implode(' · ', $counts).' · '.$gatewayPart;
        }

        return 'A preparer : '.implode(' · ', $counts).' · '.$gatewayPart;
    }

    private function sectorDemoMetric(array $status): string
    {
        $created = is_array($status['created'] ?? null) ? $status['created'] : [];
        $supplierPart = $created['suppliers'] ?? $status['suppliers_count'];
        $customerPart = $created['customers'] ?? $status['customers_count'];
        $productPart = $created['products'] ?? $status['products_count'];
        $stockPart = $created['stock_entries'] ?? $status['stock_entries_count'];
        $playbookPart = $status['playbooks_count'] ?? 0;

        if ($status['is_applied']) {
            return 'Demo chargee : '.$supplierPart.' fournisseurs · '.$customerPart.' clients · '.$productPart.' produits · '.$stockPart.' stocks · '.$playbookPart.' parcours';
        }

        return 'A charger : '.$status['suppliers_count'].' fournisseurs · '.$status['customers_count'].' clients · '.$status['products_count'].' produits · '.$status['playbooks_count'].' parcours';
    }

    private function pilotReadinessMetric(array $status): string
    {
        if ($status['is_ready']) {
            return 'Pilote pret : score '.$status['score'].'% · agence '.($status['pilot_branch'] ?: 'non definie').' · 0 bloquant';
        }

        return 'Essai a verrouiller : score '.$status['score'].'% · '.$status['blockers_count'].' bloquant(s)';
    }
}
