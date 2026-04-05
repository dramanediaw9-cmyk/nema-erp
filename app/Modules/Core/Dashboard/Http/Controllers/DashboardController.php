<?php

namespace App\Modules\Core\Dashboard\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\JournalLine;
use App\Modules\Accounting\Services\PeriodChecklistService;
use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Access\Models\Role;
use App\Modules\Core\Audit\Models\ActivityLog;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Core\Company\Services\SectorProfileService;
use App\Modules\Core\Dashboard\Services\ExecutiveBriefingService;
use App\Modules\Core\Notifications\Services\NotificationService;
use App\Modules\Core\Onboarding\Services\OnboardingService;
use App\Modules\Core\Ops\Services\ApplicationMonitoringService;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Partners\Models\Partner;
use App\Modules\Purchases\Models\GoodsReceipt;
use App\Modules\Purchases\Models\PurchaseBill;
use App\Modules\Purchases\Models\PurchaseOrder;
use App\Modules\Purchases\Models\PurchaseRequest;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Treasury\Models\CashAccount;
use App\Modules\Treasury\Models\Payment;
use App\Support\CurrentWorkspace;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly OnboardingService $onboardingService,
        private readonly PeriodChecklistService $periodChecklistService,
        private readonly NotificationService $notificationService,
        private readonly SectorProfileService $sectorProfileService,
        private readonly ApplicationMonitoringService $applicationMonitoringService,
        private readonly ExecutiveBriefingService $executiveBriefingService,
    ) {}

    public function __invoke(CurrentWorkspace $workspace): View
    {
        $user = auth()->user();
        $companyId = $workspace->companyId();
        $branchId = $workspace->branchId();
        $branchScopeId = $user ? $user->resolvedBranchScope(null, $branchId) : $branchId;
        $canAccessAllBranches = (bool) $user?->canAccessAllBranches();
        $monthStart = Carbon::now()->startOfMonth()->toDateString();
        $today = Carbon::today()->toDateString();

        if ($companyId) {
            $this->notificationService->syncCompanyAlerts($companyId, $branchScopeId);
        }

        $validatedSales = SalesInvoice::query()
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->when($branchScopeId, fn ($query, $selectedBranchId) => $query->where('branch_id', $selectedBranchId))
            ->where('status', 'validated');

        $notificationSummary = $companyId
            ? $this->notificationService->summaryForCompany($companyId)
            : ['count' => 0, 'items' => collect()];

        $stats = [
            'agences' => Branch::query()->when($companyId, fn ($query) => $query->where('company_id', $companyId))->count(),
            'utilisateurs' => User::query()->when($companyId, fn ($query) => $query->where('company_id', $companyId))->count(),
            'roles' => Role::query()->when($companyId, fn ($query) => $query->where(fn ($q) => $q->where('company_id', $companyId)->orWhereNull('company_id')))->count(),
            'clients' => Partner::query()->customers()->when($companyId, fn ($query) => $query->where('company_id', $companyId))->count(),
            'produits' => Product::query()->when($companyId, fn ($query) => $query->where('company_id', $companyId))->count(),
            'ventes_mois' => (float) (clone $validatedSales)->whereDate('invoice_date', '>=', $monthStart)->sum('total'),
            'ventes_jour' => (float) (clone $validatedSales)->whereDate('invoice_date', $today)->sum('total'),
            'ventes_jour_count' => (clone $validatedSales)->whereDate('invoice_date', $today)->count(),
            'achats_mois' => (float) PurchaseBill::query()->when($companyId, fn ($query) => $query->where('company_id', $companyId))->when($branchScopeId, fn ($query, $branchId) => $query->where('branch_id', $branchId))->where('status', 'validated')->whereDate('bill_date', '>=', $monthStart)->sum('total'),
            'encaissements_mois' => (float) Payment::query()->when($companyId, fn ($query) => $query->where('company_id', $companyId))->when($branchScopeId, fn ($query, $branchId) => $query->where('branch_id', $branchId))->where('direction', 'in')->whereDate('payment_date', '>=', $monthStart)->sum('amount'),
            'encaissements_jour' => (float) Payment::query()->when($companyId, fn ($query) => $query->where('company_id', $companyId))->when($branchScopeId, fn ($query, $branchId) => $query->where('branch_id', $branchId))->where('direction', 'in')->whereDate('payment_date', $today)->sum('amount'),
            'encaissements_jour_count' => Payment::query()->when($companyId, fn ($query) => $query->where('company_id', $companyId))->when($branchScopeId, fn ($query, $branchId) => $query->where('branch_id', $branchId))->where('direction', 'in')->whereDate('payment_date', $today)->count(),
            'depenses_mois' => (float) Expense::query()->when($companyId, fn ($query) => $query->where('company_id', $companyId))->when($branchScopeId, fn ($query, $branchId) => $query->where('branch_id', $branchId))->where('status', 'validated')->whereDate('expense_date', '>=', $monthStart)->sum('total'),
            'reste_a_encaisser' => (float) (clone $validatedSales)->sum('balance_due'),
            'dettes_fournisseurs' => (float) PurchaseBill::query()->when($companyId, fn ($query) => $query->where('company_id', $companyId))->when($branchScopeId, fn ($query, $branchId) => $query->where('branch_id', $branchId))->where('status', 'validated')->sum('balance_due'),
            'factures_impayees' => (clone $validatedSales)->whereIn('payment_status', ['unpaid', 'partial'])->count(),
            'ventes_en_attente' => SalesInvoice::query()->when($companyId, fn ($query) => $query->where('company_id', $companyId))->when($branchScopeId, fn ($query, $branchId) => $query->where('branch_id', $branchId))->where('status', 'pending_approval')->count(),
            'factures_fournisseurs_impayees' => PurchaseBill::query()->when($companyId, fn ($query) => $query->where('company_id', $companyId))->when($branchScopeId, fn ($query, $branchId) => $query->where('branch_id', $branchId))->where('status', 'validated')->whereIn('payment_status', ['unpaid', 'partial'])->count(),
            'depenses_non_reglees' => Expense::query()->when($companyId, fn ($query) => $query->where('company_id', $companyId))->when($branchScopeId, fn ($query, $branchId) => $query->where('branch_id', $branchId))->where('status', 'validated')->where('payment_status', 'unpaid')->count(),
            'achats_en_attente' => PurchaseBill::query()->when($companyId, fn ($query) => $query->where('company_id', $companyId))->when($branchScopeId, fn ($query, $branchId) => $query->where('branch_id', $branchId))->where('status', 'pending_approval')->count(),
            'depenses_en_attente' => Expense::query()->when($companyId, fn ($query) => $query->where('company_id', $companyId))->when($branchScopeId, fn ($query, $branchId) => $query->where('branch_id', $branchId))->where('status', 'pending_approval')->count(),
            'alertes_stock' => $this->stockAlerts($companyId, $branchScopeId),
            'comptes_tresorerie' => CashAccount::query()->when($companyId, fn ($query) => $query->where('company_id', $companyId))->when($branchScopeId, fn ($query, $branchId) => $query->where('branch_id', $branchId))->count(),
            'entreprises' => Company::query()->count(),
            'ecritures_mois' => JournalEntry::query()->when($companyId, fn ($query) => $query->where('company_id', $companyId))->when($branchScopeId, fn ($query, $branchId) => $query->where('branch_id', $branchId))->whereDate('entry_date', '>=', $monthStart)->count(),
            'resultat_mois' => $this->monthlyResult($companyId, $monthStart, $branchScopeId),
            'receptions_jour' => GoodsReceipt::query()->when($companyId, fn ($query) => $query->where('company_id', $companyId))->when($branchScopeId, fn ($query, $branchId) => $query->where('branch_id', $branchId))->whereDate('receipt_date', $today)->count(),
            'commandes_clients_ouvertes' => SalesOrder::query()->when($companyId, fn ($query) => $query->where('company_id', $companyId))->when($branchScopeId, fn ($query, $branchId) => $query->where('branch_id', $branchId))->whereIn('status', ['draft', 'confirmed', 'partial_delivered'])->count(),
            'commandes_fournisseurs_ouvertes' => PurchaseOrder::query()->when($companyId, fn ($query) => $query->where('company_id', $companyId))->when($branchScopeId, fn ($query, $branchId) => $query->where('branch_id', $branchId))->whereIn('status', ['draft', 'confirmed', 'partial_received'])->count(),
            'demandes_achat_ouvertes' => PurchaseRequest::query()->when($companyId, fn ($query) => $query->where('company_id', $companyId))->when($branchScopeId, fn ($query, $branchId) => $query->where('branch_id', $branchId))->whereNull('converted_purchase_order_id')->whereIn('status', ['draft', 'pending_approval', 'approved'])->count(),
            'alertes_non_lues' => (int) ($notificationSummary['count'] ?? 0),
        ];
        $stats['approbations_en_attente_total'] = $stats['ventes_en_attente'] + $stats['achats_en_attente'] + $stats['depenses_en_attente'];

        $recentActivities = ActivityLog::query()
            ->with('user')
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->when($branchScopeId, fn ($query, $selectedBranchId) => $query->where('branch_id', $selectedBranchId))
            ->latest()
            ->limit(8)
            ->get();

        $onboarding = $companyId ? $this->onboardingService->summary($companyId) : null;
        $showOnboardingBanner = $companyId
            && $onboarding
            && ! $onboarding['is_complete']
            && ! $this->onboardingService->isDashboardBannerDismissed($companyId);

        $currentPeriodSummary = $this->periodChecklistService->currentPeriodSummary($companyId);
        $appMonitoring = $this->applicationMonitoringService->summary();
        $dashboardProfile = $this->dashboardProfile($user);
        $sectorProfile = $this->sectorProfileService->profileForCompany($companyId);
        $premiumActionCenter = $this->premiumActionCenter($stats, $currentPeriodSummary, $onboarding, $appMonitoring);
        $executiveBrief = $this->executiveBriefingService->forDashboard($dashboardProfile['key'], $stats, $currentPeriodSummary, $appMonitoring, $onboarding);

        return view('dashboard.index', [
            'dashboardProfile' => $dashboardProfile,
            'dashboardKpis' => $this->dashboardKpis($dashboardProfile['key'], $stats),
            'roleActionPlan' => $this->roleActionPlan($dashboardProfile['key']),
            'roleSpotlight' => $this->roleSpotlight($dashboardProfile['key'], $stats, $monthStart),
            'stats' => $stats,
            'recentActivities' => $recentActivities,
            'onboarding' => $onboarding,
            'showOnboardingBanner' => $showOnboardingBanner,
            'currentPeriodSummary' => $currentPeriodSummary,
            'quickLinks' => $this->quickLinks(),
            'sectorProfile' => $sectorProfile,
            'sectorActionPlan' => $this->sectorActionPlan($sectorProfile),
            'premiumBrief' => $this->premiumBrief($premiumActionCenter, $dashboardProfile['key']),
            'premiumActionCenter' => $premiumActionCenter,
            'operationalWatchlist' => $this->operationalWatchlist($stats, $monthStart, (int) ($notificationSummary['count'] ?? 0)),
        ]);
    }

    private function stockAlerts(?int $companyId, ?int $branchId): int
    {
        if (! $companyId) {
            return 0;
        }

        $balances = StockMovement::query()
            ->select('product_id')
            ->selectRaw('SUM(quantity_in - quantity_out) as current_stock')
            ->where('company_id', $companyId)
            ->when($branchId, fn ($query, $selectedBranchId) => $query->where('branch_id', $selectedBranchId))
            ->groupBy('product_id');

        return Product::query()
            ->where('company_id', $companyId)
            ->where('type', 'stockable')
            ->leftJoinSub($balances, 'balances', fn ($join) => $join->on('products.id', '=', 'balances.product_id'))
            ->whereRaw('COALESCE(balances.current_stock, 0) <= products.min_stock')
            ->count();
    }

    private function monthlyResult(?int $companyId, string $monthStart, ?int $branchId = null): float
    {
        if (! $companyId) {
            return 0;
        }

        $income = (float) JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journal_entries.company_id', $companyId)
            ->when($branchId, fn ($query, $selectedBranchId) => $query->where('journal_entries.branch_id', $selectedBranchId))
            ->whereDate('journal_entries.entry_date', '>=', $monthStart)
            ->where('accounts.type', 'income')
            ->selectRaw('COALESCE(SUM(journal_lines.credit - journal_lines.debit), 0) as total')
            ->value('total');

        $expenses = (float) JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journal_entries.company_id', $companyId)
            ->when($branchId, fn ($query, $selectedBranchId) => $query->where('journal_entries.branch_id', $selectedBranchId))
            ->whereDate('journal_entries.entry_date', '>=', $monthStart)
            ->where('accounts.type', 'expense')
            ->selectRaw('COALESCE(SUM(journal_lines.debit - journal_lines.credit), 0) as total')
            ->value('total');

        return round($income - $expenses, 2);
    }

    private function dashboardProfile(?User $user): array
    {
        if ($user?->hasRole('cashier')) {
            return [
                'key' => 'cashier',
                'page_title' => 'Dashboard caisse',
                'badge' => 'Caisse comptoir',
                'headline' => 'Routine caisse',
                'description' => 'Retrouve ici les indicateurs de caisse, l acces POS et les alertes utiles pour travailler vite au comptoir.',
                'focus_title' => 'Routine caisse',
                'focus_description' => 'Les gestes les plus frequents pour ouvrir, encaisser et suivre le comptoir.',
                'analysis_title' => 'Lecture caisse',
                'analysis_description' => 'La caisse reste orientee action : tickets, encaissements, alertes et stock critique.',
                'priorities' => ['Point de vente', 'Encaissements du jour', 'Stock critique'],
                'search_examples' => ['PRD-0001', 'Sahel Market', 'REC-DEMO-001'],
            ];
        }

        if ($user?->hasRole('operations_officer')) {
            return [
                'key' => 'operations',
                'page_title' => 'Dashboard exploitation',
                'badge' => 'Exploitation terrain',
                'headline' => 'Operations du jour',
                'description' => 'Ce cockpit met en avant les documents a faire avancer, les receptions du jour et les points de blocage terrain.',
                'focus_title' => 'Plan de la journee',
                'focus_description' => 'Les actions a lancer rapidement pour garder les ventes, achats et stocks synchronises.',
                'analysis_title' => 'Lecture exploitation',
                'analysis_description' => 'Les signaux ci-dessous te disent ou intervenir d abord pour fluidifier l activite.',
                'priorities' => ['Ventes du jour', 'Receptions fournisseurs', 'Approvisionnement'],
                'search_examples' => ['FAC-', 'BON-', 'PRD-0007'],
            ];
        }

        if ($user?->hasRole('director')) {
            return [
                'key' => 'direction',
                'page_title' => 'Dashboard direction',
                'badge' => 'Direction generale',
                'headline' => 'Pilotage executif',
                'description' => 'La vue direction rassemble le resultat, le cash a recuperer et les arbitrages qui peuvent ralentir l entreprise.',
                'focus_title' => 'Priorites direction',
                'focus_description' => 'Les arbitrages a regarder en premier avant d entrer dans les modules detailles.',
                'analysis_title' => 'Lecture direction',
                'analysis_description' => 'Le dashboard met en avant les montants qui aident a piloter cash, marge et validations.',
                'priorities' => ['Resultat du mois', 'Recouvrement client', 'Approbations a arbitrer'],
                'search_examples' => ['Sahel Market', 'FAC-', 'BIL-'],
            ];
        }

        return [
            'key' => 'pilotage',
            'page_title' => 'Dashboard pilotage',
            'badge' => 'Administration entreprise',
            'headline' => 'Cockpit entreprise',
            'description' => 'Vue transversale pour piloter les operations, les reglages et la supervision quotidienne de la societe active.',
            'focus_title' => 'Cockpit entreprise',
            'focus_description' => 'Les priorites de pilotage pour faire avancer exploitation, parametres et suivi global.',
            'analysis_title' => 'Lecture transversale',
            'analysis_description' => 'Tu as ici une vue melangeant business, exploitation et administration de la societe.',
            'priorities' => ['Actions rapides', 'Suivi operationnel', 'Recherche globale'],
            'search_examples' => ['Sahel Market', 'PRD-0001', 'REC-DEMO-001'],
        ];
    }

    private function dashboardKpis(string $profileKey, array $stats): array
    {
        $kpis = match ($profileKey) {
            'cashier' => [
                ['label' => 'Ventes du jour', 'value' => $this->money($stats['ventes_jour']), 'description' => $stats['ventes_jour_count'].' ticket(s) valide(s) aujourd hui.'],
                ['label' => 'Encaissements du jour', 'value' => $this->money($stats['encaissements_jour']), 'description' => $stats['encaissements_jour_count'].' encaissement(s) enregistres.'],
                ['label' => 'Alertes stock', 'value' => $this->number($stats['alertes_stock']), 'description' => 'Articles au minimum sur l agence active.'],
                ['label' => 'Alertes non lues', 'value' => $this->number($stats['alertes_non_lues']), 'description' => 'Signaux d exploitation a lire.'],
                ['label' => 'Produits suivis', 'value' => $this->number($stats['produits']), 'description' => 'Catalogue disponible pour la vente.'],
                ['label' => 'Comptes de caisse', 'value' => $this->number($stats['comptes_tresorerie']), 'description' => 'Comptes de tresorerie disponibles.'],
            ],
            'operations' => [
                ['label' => 'Ventes du jour', 'value' => $this->money($stats['ventes_jour']), 'description' => $stats['ventes_jour_count'].' facture(s) validee(s) aujourd hui.'],
                ['label' => 'Receptions du jour', 'value' => $this->number($stats['receptions_jour']), 'description' => 'Receptions fournisseurs enregistrees.'],
                ['label' => 'Achats en attente', 'value' => $this->number($stats['achats_en_attente']), 'description' => 'Documents a pousser vers validation.'],
                ['label' => 'Demandes d achat ouvertes', 'value' => $this->number($stats['demandes_achat_ouvertes']), 'description' => 'Demandes a convertir ou arbitrer.'],
                ['label' => 'Commandes clients ouvertes', 'value' => $this->number($stats['commandes_clients_ouvertes']), 'description' => 'Commandes encore en cours de livraison ou de conversion.'],
                ['label' => 'Stock critique', 'value' => $this->number($stats['alertes_stock']), 'description' => 'Produits a surveiller sur l agence active.'],
            ],
            'direction' => [
                ['label' => 'Resultat du mois', 'value' => $this->money($stats['resultat_mois']), 'description' => 'Lecture comptable de la performance mensuelle.'],
                ['label' => 'Ventes du mois', 'value' => $this->money($stats['ventes_mois']), 'description' => 'Facturation client validee sur la periode.'],
                ['label' => 'Reste a encaisser', 'value' => $this->money($stats['reste_a_encaisser']), 'description' => $stats['factures_impayees'].' facture(s) client encore ouvertes.'],
                ['label' => 'Dettes fournisseurs', 'value' => $this->money($stats['dettes_fournisseurs']), 'description' => $stats['factures_fournisseurs_impayees'].' facture(s) fournisseur a regler.'],
                ['label' => 'Approbations en attente', 'value' => $this->number($stats['approbations_en_attente_total']), 'description' => 'Ventes, achats et depenses a arbitrer.'],
                ['label' => 'Ecritures du mois', 'value' => $this->number($stats['ecritures_mois']), 'description' => 'Production comptable deja generee.'],
            ],
            default => [
                ['label' => 'Agences', 'value' => $this->number($stats['agences']), 'description' => 'Implantations actives dans la societe.'],
                ['label' => 'Utilisateurs', 'value' => $this->number($stats['utilisateurs']), 'description' => 'Equipe active sur l ERP.'],
                ['label' => 'Clients', 'value' => $this->number($stats['clients']), 'description' => 'Portefeuille client disponible.'],
                ['label' => 'Produits', 'value' => $this->number($stats['produits']), 'description' => 'Catalogue actuellement suivI.'],
                ['label' => 'Ventes du mois', 'value' => $this->money($stats['ventes_mois']), 'description' => 'Facturation validee sur la periode.'],
                ['label' => 'Resultat du mois', 'value' => $this->money($stats['resultat_mois']), 'description' => 'Lecture comptable du mois en cours.'],
                ['label' => 'Ecritures du mois', 'value' => $this->number($stats['ecritures_mois']), 'description' => 'Ecritures comptables generees.'],
                ['label' => 'Alertes stock', 'value' => $this->number($stats['alertes_stock']), 'description' => 'Produits a traiter rapidement.'],
            ],
        };

        return $kpis;
    }

    private function roleActionPlan(string $profileKey): array
    {
        $actions = match ($profileKey) {
            'cashier' => [
                ['permission' => 'pos.view', 'label' => 'Ouvrir la caisse', 'description' => 'Acceder a la session POS et aux tickets recents.', 'url' => route('pos.index')],
                ['permission' => 'pos.manage', 'label' => 'Nouvelle vente POS', 'description' => 'Lancer rapidement une vente comptoir.', 'url' => route('pos.sales.create')],
                ['permission' => 'stock.view', 'label' => 'Verifier le stock critique', 'description' => 'Voir les produits a risque avant la rupture.', 'url' => route('stock.index', ['stock_state' => 'low'])],
                ['permission' => 'notifications.view', 'label' => 'Traiter les alertes', 'description' => 'Lire les alertes internes non lues.', 'url' => route('notifications.index', ['scope' => 'active', 'read_state' => 'unread'])],
            ],
            'operations' => [
                ['permission' => 'sales.manage', 'label' => 'Creer une vente', 'description' => 'Ouvrir une nouvelle facture client.', 'url' => route('sales.create')],
                ['permission' => 'purchases.manage', 'label' => 'Creer un achat', 'description' => 'Saisir une facture fournisseur.', 'url' => route('purchases.create')],
                ['permission' => 'goods_receipts.view', 'label' => 'Suivre les receptions', 'description' => 'Ouvrir les receptions fournisseurs du jour.', 'url' => route('goods-receipts.index')],
                ['permission' => 'purchase_requests.view', 'label' => 'Voir les demandes d achat', 'description' => 'Surveiller les demandes encore ouvertes.', 'url' => route('purchase-requests.index')],
            ],
            'direction' => [
                ['permission' => 'reports.view', 'label' => 'Ouvrir les rapports', 'description' => 'Analyser resultat, bilan et activite.', 'url' => route('reports.index')],
                ['permission' => 'approvals.view', 'label' => 'Arbitrer les approbations', 'description' => 'Traiter les documents encore bloques.', 'url' => route('approvals.index')],
                ['permission' => 'collections.view', 'label' => 'Suivre le recouvrement', 'description' => 'Prioriser les impayes clients.', 'url' => route('collections.index')],
                ['permission' => 'accounting.view', 'label' => 'Ouvrir les journaux', 'description' => 'Controler les ecritures du mois en cours.', 'url' => route('accounting.journal-entries.index')],
            ],
            default => [
                ['permission' => 'settings.view', 'label' => 'Parametres', 'description' => 'Ajuster societe, sequences et integrations.', 'url' => route('settings.index')],
                ['permission' => 'imports.manage', 'label' => 'Imports CSV', 'description' => 'Charger clients, produits ou historiques.', 'url' => route('imports.index')],
                ['permission' => 'reports.view', 'label' => 'Rapports dirigeants', 'description' => 'Visualiser les syntheses de pilotage.', 'url' => route('reports.index')],
                ['permission' => 'ops.view', 'label' => 'Sante et exploitation', 'description' => 'Voir l outbox, les checks et l etat systeme.', 'url' => route('ops.index')],
            ],
        };

        return $this->filterAuthorizedItems($actions);
    }

    private function roleSpotlight(string $profileKey, array $stats, string $monthStart): array
    {
        $items = match ($profileKey) {
            'cashier' => [
                ['permission' => 'pos.view', 'label' => 'Caisse du jour', 'value' => $this->money($stats['encaissements_jour']), 'description' => 'Encaissements saisis aujourd hui sur l agence active.', 'url' => route('payments.index', ['date_from' => now()->toDateString(), 'date_to' => now()->toDateString()])],
                ['permission' => 'stock.view', 'label' => 'Produits a risque', 'value' => $this->number($stats['alertes_stock']), 'description' => 'Produits au minimum de stock pour eviter un blocage au comptoir.', 'url' => route('stock.index', ['stock_state' => 'low'])],
                ['permission' => 'notifications.view', 'label' => 'Alertes a lire', 'value' => $this->number($stats['alertes_non_lues']), 'description' => 'Alertes internes non lues a traiter rapidement.', 'url' => route('notifications.index', ['scope' => 'active', 'read_state' => 'unread'])],
            ],
            'operations' => [
                ['permission' => 'sales.view', 'label' => 'File ventes', 'value' => $this->number($stats['ventes_en_attente']), 'description' => 'Factures en attente avant impact stock et comptable.', 'url' => route('sales.index', ['status' => 'pending_approval'])],
                ['permission' => 'goods_receipts.view', 'label' => 'Receptions du jour', 'value' => $this->number($stats['receptions_jour']), 'description' => 'Receptions fournisseurs deja saisies aujourd hui.', 'url' => route('goods-receipts.index')],
                ['permission' => 'purchase_orders.view', 'label' => 'Commandes fournisseurs ouvertes', 'value' => $this->number($stats['commandes_fournisseurs_ouvertes']), 'description' => 'Commandes encore en cours de reception ou de suivi.', 'url' => route('purchase-orders.index')],
                ['permission' => 'purchase_requests.view', 'label' => 'Demandes a suivre', 'value' => $this->number($stats['demandes_achat_ouvertes']), 'description' => 'Demandes d achat encore non converties.', 'url' => route('purchase-requests.index')],
            ],
            'direction' => [
                ['permission' => 'reports.view', 'label' => 'Resultat du mois', 'value' => $this->money($stats['resultat_mois']), 'description' => 'Lecture direction immediate de la performance du mois.', 'url' => route('reports.index')],
                ['permission' => 'sales.view', 'label' => 'Reste a encaisser', 'value' => $this->money($stats['reste_a_encaisser']), 'description' => 'Creances clients encore ouvertes.', 'url' => route('sales.index', ['payment_status' => 'unpaid'])],
                ['permission' => 'purchases.view', 'label' => 'Dettes fournisseurs', 'value' => $this->money($stats['dettes_fournisseurs']), 'description' => 'Factures fournisseur encore a regler.', 'url' => route('purchases.index', ['payment_status' => 'unpaid'])],
                ['permission' => 'approvals.view', 'label' => 'Arbitrages a trancher', 'value' => $this->number($stats['approbations_en_attente_total']), 'description' => 'Documents attendant une decision de validation.', 'url' => route('approvals.index')],
            ],
            default => [
                ['permission' => 'settings.view', 'label' => 'Societe active', 'value' => $this->number($stats['agences']).' agence(s)', 'description' => 'Pilotage de la structure et du perimetre actif.', 'url' => route('settings.index')],
                ['permission' => 'reports.view', 'label' => 'Resultat du mois', 'value' => $this->money($stats['resultat_mois']), 'description' => 'Lecture rapide business et comptable.', 'url' => route('reports.index')],
                ['permission' => 'approvals.view', 'label' => 'Documents a arbitrer', 'value' => $this->number($stats['approbations_en_attente_total']), 'description' => 'Flux encore en attente de validation.', 'url' => route('approvals.index')],
                ['permission' => 'accounting.view', 'label' => 'Journaux du mois', 'value' => $this->number($stats['ecritures_mois']), 'description' => 'Production comptable deja enregistree sur la periode.', 'url' => route('accounting.journal-entries.index', ['date_from' => $monthStart])],
            ],
        };

        return $this->filterAuthorizedItems($items);
    }

    private function sectorActionPlan(array $sectorProfile): array
    {
        $actions = collect($sectorProfile['recommended_modules'] ?? [])
            ->map(function (array $item): array {
                $item['url'] = route($item['route_name'], $item['route_params'] ?? []);

                return $item;
            })
            ->all();

        return $this->filterAuthorizedItems($actions);
    }

    private function quickLinks(): array
    {
        return collect([
            ['permission' => 'sales.manage', 'label' => 'Nouvelle vente', 'description' => 'Creer une facture client', 'url' => route('sales.create')],
            ['permission' => 'purchases.manage', 'label' => 'Nouvel achat', 'description' => 'Creer une facture fournisseur', 'url' => route('purchases.create')],
            ['permission' => 'expenses.manage', 'label' => 'Nouvelle depense', 'description' => 'Saisir une charge', 'url' => route('expenses.create')],
            ['permission' => 'approvals.view', 'label' => 'Traiter les approbations', 'description' => 'Ouvrir la boite d approbation', 'url' => route('approvals.index')],
            ['permission' => 'reports.view', 'label' => 'Rapports dirigeants', 'description' => 'Voir les syntheses', 'url' => route('reports.index')],
            ['permission' => 'imports.manage', 'label' => 'Importer des donnees', 'description' => 'Clients, produits, stock, historiques', 'url' => route('imports.index')],
        ])->filter(fn (array $link) => auth()->user()?->hasPermission($link['permission']))->values()->all();
    }

    private function operationalWatchlist(array $stats, string $monthStart, int $unreadAlerts): array
    {
        return collect([
            [
                'permission' => 'approvals.view',
                'label' => 'Ventes en attente',
                'count' => $stats['ventes_en_attente'],
                'description' => 'Factures client a approuver avant impact stock et comptable.',
                'url' => route('sales.index', ['status' => 'pending_approval']),
            ],
            [
                'permission' => 'approvals.view',
                'label' => 'Achats en attente',
                'count' => $stats['achats_en_attente'],
                'description' => 'Approvisionnements en attente de validation.',
                'url' => route('purchases.index', ['status' => 'pending_approval']),
            ],
            [
                'permission' => 'expenses.view',
                'label' => 'Depenses non payees',
                'count' => $stats['depenses_non_reglees'],
                'description' => 'Charges validees restant a regler.',
                'url' => route('expenses.index', ['payment_status' => 'unpaid']),
            ],
            [
                'permission' => 'stock.view',
                'label' => 'Stock a surveiller',
                'count' => $stats['alertes_stock'],
                'description' => 'Produits au minimum ou en dessous sur l agence active.',
                'url' => route('stock.index', ['stock_state' => 'low']),
            ],
            [
                'permission' => 'notifications.view',
                'label' => 'Alertes non lues',
                'count' => $unreadAlerts,
                'description' => 'Blocages et signaux d exploitation a traiter.',
                'url' => route('notifications.index', ['scope' => 'active', 'read_state' => 'unread']),
            ],
            [
                'permission' => 'accounting.view',
                'label' => 'Journaux du mois',
                'count' => $stats['ecritures_mois'],
                'description' => 'Ecritures comptables generees sur la periode en cours.',
                'url' => route('accounting.journal-entries.index', ['date_from' => $monthStart]),
            ],
        ])->filter(fn (array $item) => auth()->user()?->hasPermission($item['permission']))->values()->all();
    }

    private function premiumActionCenter(array $stats, ?array $currentPeriodSummary, ?array $onboarding, array $appMonitoring): array
    {
        $items = [
            [
                'permission' => 'approvals.view',
                'priority' => ($stats['approbations_en_attente_total'] ?? 0) > 0 ? 'high' : 'low',
                'eyebrow' => 'Flux a arbitrer',
                'label' => 'Arbitrer les validations',
                'metric' => $this->number((int) ($stats['approbations_en_attente_total'] ?? 0)),
                'description' => 'Ventes, achats et depenses attendent encore une decision metier.',
                'url' => route('approvals.index'),
            ],
            [
                'permission' => 'sales.view',
                'priority' => ($stats['reste_a_encaisser'] ?? 0) > 0 ? 'high' : 'low',
                'eyebrow' => 'Cash client',
                'label' => 'Recouvrer le cash client',
                'metric' => $this->money((float) ($stats['reste_a_encaisser'] ?? 0)),
                'description' => ($stats['factures_impayees'] ?? 0).' facture(s) restent a encaisser.',
                'url' => route('sales.index', ['payment_status' => 'unpaid']),
            ],
            [
                'permission' => 'stock.view',
                'priority' => ($stats['alertes_stock'] ?? 0) > 0 ? 'high' : 'medium',
                'eyebrow' => 'Disponibilite',
                'label' => 'Traiter le stock critique',
                'metric' => $this->number((int) ($stats['alertes_stock'] ?? 0)),
                'description' => 'Produits au minimum ou en dessous sur le perimetre actif.',
                'url' => route('stock.index', ['stock_state' => 'low']),
            ],
            [
                'permission' => 'purchase_requests.view',
                'priority' => ($stats['demandes_achat_ouvertes'] ?? 0) > 0 ? 'medium' : 'low',
                'eyebrow' => 'Approvisionnement',
                'label' => 'Debloquer les demandes d achat',
                'metric' => $this->number((int) ($stats['demandes_achat_ouvertes'] ?? 0)),
                'description' => 'Demandes ouvertes a convertir ou escalader rapidement.',
                'url' => route('purchase-requests.index'),
            ],
            [
                'permission' => 'accounting.view',
                'priority' => ($currentPeriodSummary && ! ($currentPeriodSummary['period']?->isClosed()) && ! ($currentPeriodSummary['can_close'] ?? false)) ? 'high' : 'low',
                'eyebrow' => 'Cloture',
                'label' => 'Debloquer la periode courante',
                'metric' => $currentPeriodSummary && ! ($currentPeriodSummary['period']?->isClosed()) && ! ($currentPeriodSummary['can_close'] ?? false) ? 'Bloquee' : 'Sous controle',
                'description' => $currentPeriodSummary && ! ($currentPeriodSummary['period']?->isClosed()) && ! ($currentPeriodSummary['can_close'] ?? false)
                    ? 'Des bloqueurs empechent encore une cloture propre de la periode.'
                    : 'La periode ne remonte pas de blocage critique a ce stade.',
                'url' => route('accounting.periods.index'),
            ],
            [
                'permission' => 'ops.view',
                'priority' => ($appMonitoring['status'] ?? 'ok') === 'fail' ? 'high' : (($appMonitoring['status'] ?? 'ok') === 'warning' ? 'medium' : 'low'),
                'eyebrow' => 'Sante technique',
                'label' => 'Stabiliser la plateforme',
                'metric' => strtoupper((string) ($appMonitoring['status'] ?? 'ok')),
                'description' => ((int) data_get($appMonitoring, 'logs.signals_count', 0)).' signal(s) log et '.((int) data_get($appMonitoring, 'failed_jobs.count', 0)).' job(s) en echec a surveiller.',
                'url' => route('ops.index'),
            ],
            [
                'priority' => ($onboarding && ! ($onboarding['is_complete'] ?? true)) ? 'medium' : 'low',
                'eyebrow' => 'Demarrage',
                'label' => 'Finaliser la mise en route',
                'metric' => $onboarding ? ((int) ($onboarding['completed'] ?? 0)).'/'.((int) ($onboarding['total'] ?? 0)) : '0/0',
                'description' => $onboarding && ! ($onboarding['is_complete'] ?? true)
                    ? 'Le pilote peut encore gagner en robustesse avec les derniers prerequis de demarrage.'
                    : 'Le socle de demarrage est deja bien pose pour la societe active.',
                'url' => route('onboarding.index'),
            ],
        ];

        $priorityRank = ['high' => 0, 'medium' => 1, 'low' => 2];

        $filtered = collect($items)
            ->filter(function (array $item): bool {
                $permission = $item['permission'] ?? null;

                return ! $permission || auth()->user()?->hasPermission($permission);
            })
            ->sortBy(fn (array $item): int => $priorityRank[$item['priority']] ?? 99)
            ->values()
            ->take(4)
            ->all();

        if ($filtered !== []) {
            return $filtered;
        }

        return [[
            'priority' => 'low',
            'eyebrow' => 'Pilotage',
            'label' => 'Rythme stable',
            'metric' => 'OK',
            'description' => 'Aucune tension majeure n a ete detectee sur le perimetre courant.',
            'url' => route('reports.index'),
        ]];
    }

    private function premiumBrief(array $items, string $profileKey): array
    {
        $count = count($items);
        $highCount = collect($items)->where('priority', 'high')->count();
        $topLabels = collect($items)->pluck('label')->take(3)->implode(' | ');

        if ($highCount > 0) {
            $headline = $highCount.' priorite(s) immediate(s) a traiter aujourd hui';
            $description = 'Le cockpit premium remonte d abord les leviers qui protegent cash, cloture, stock et execution.';
        } elseif ($count > 0) {
            $headline = 'Le cockpit premium isole les prochains leviers de pilotage';
            $description = 'Tout n est pas urgent, mais ces points te feront gagner le plus de temps et de controle.';
        } else {
            $headline = 'Le cockpit premium ne remonte aucun blocage majeur';
            $description = 'Le perimetre courant est stable. Tu peux te concentrer sur le developpement business.';
        }

        return [
            'headline' => $headline,
            'description' => $description,
            'focus' => $topLabels,
            'profile' => $profileKey,
        ];
    }

    private function filterAuthorizedItems(array $items): array
    {
        return collect($items)
            ->filter(fn (array $item) => ! isset($item['permission']) || auth()->user()?->hasPermission($item['permission']))
            ->values()
            ->all();
    }

    private function money(float $value): string
    {
        return number_format($value, 0, ',', ' ').' XOF';
    }

    private function number(int|float $value): string
    {
        return number_format((float) $value, 0, ',', ' ');
    }
}
