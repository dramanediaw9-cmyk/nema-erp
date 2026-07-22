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
use App\Modules\Inventory\Models\ProductLot;
use App\Modules\Inventory\Models\StockCount;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Partners\Models\Partner;
use App\Modules\Purchases\Models\GoodsReceipt;
use App\Modules\Purchases\Models\PurchaseBill;
use App\Modules\Purchases\Models\PurchaseOrder;
use App\Modules\Purchases\Models\PurchaseRequest;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Services\OrderCoverageService;
use App\Modules\Treasury\Models\CashAccount;
use App\Modules\Treasury\Models\Payment;
use App\Support\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
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
        private readonly OrderCoverageService $orderCoverageService,
    ) {}

    public function __invoke(CurrentWorkspace $workspace): View|RedirectResponse
    {
        $user = auth()->user();

        if ($this->isPureCashier($user)) {
            return redirect()->route('pos.index');
        }

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
            'references_disponibles' => $this->availableStockReferences($companyId, $branchScopeId),
            'mouvements_stock_jour' => StockMovement::query()->when($companyId, fn ($query) => $query->where('company_id', $companyId))->when($branchScopeId, fn ($query, $branchId) => $query->where('branch_id', $branchId))->whereDate('movement_date', $today)->count(),
            'inventaires_ouverts' => StockCount::query()->when($companyId, fn ($query) => $query->where('company_id', $companyId))->when($branchScopeId, fn ($query, $branchId) => $query->where('branch_id', $branchId))->where('status', 'draft')->count(),
            'transferts_jour' => StockTransfer::query()->when($companyId, fn ($query) => $query->where('company_id', $companyId))->when($branchScopeId, fn ($query, $branchId) => $query->where('branch_id', $branchId))->whereDate('transfer_date', $today)->count(),
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
        $stats = array_merge(
            $stats,
            $this->mobileMoneyWatch($companyId, $branchScopeId),
            $this->internalTransferDepositWatch($companyId, $branchScopeId),
        );
        $stats['approbations_en_attente_total'] = $stats['ventes_en_attente'] + $stats['achats_en_attente'] + $stats['depenses_en_attente'];

        $recentActivities = ActivityLog::query()
            ->with('user')
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->when($branchScopeId, fn ($query, $selectedBranchId) => $query->where('branch_id', $selectedBranchId))
            ->latest()
            ->limit(8)
            ->get();
        $recentStockMovements = $user?->hasPermission('stock.view')
            ? StockMovement::query()
                ->with(['product', 'warehouse'])
                ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
                ->when($branchScopeId, fn ($query, $selectedBranchId) => $query->where('branch_id', $selectedBranchId))
                ->latest('movement_date')
                ->latest('id')
                ->limit(6)
                ->get()
            : collect();

        $onboarding = $companyId ? $this->onboardingService->summary($companyId) : null;
        $showOnboardingBanner = $companyId
            && $onboarding
            && ! $onboarding['is_complete']
            && ! $this->onboardingService->isDashboardBannerDismissed($companyId);

        $currentPeriodSummary = $this->periodChecklistService->currentPeriodSummary($companyId);
        $appMonitoring = $this->applicationMonitoringService->summary();
        $sectorProfile = $this->sectorProfileService->profileForCompany($companyId);
        $businessVocabulary = $this->sectorProfileService->businessVocabularyForProfile($sectorProfile);
        $dashboardProfile = $this->dashboardProfile($user, $businessVocabulary);
        $stats = array_merge(
            $stats,
            $this->pharmacySafetyWatch($companyId, $branchScopeId, $sectorProfile),
            $this->foodStoreRetailWatch($companyId, $branchScopeId, $sectorProfile),
            $this->wholesaleDistributionWatch($companyId, $branchScopeId, $sectorProfile),
        );
        $dashboardKpis = $this->decorateDashboardItems($this->dashboardKpis($dashboardProfile['key'], $stats, $businessVocabulary), 'kpi');
        $roleSpotlight = $this->decorateDashboardItems($this->roleSpotlight($dashboardProfile['key'], $stats, $monthStart), 'spotlight');
        $sectorActionPlan = $this->decorateDashboardItems($this->sectorActionPlan($sectorProfile), 'sector');
        $sectorSignals = $this->decorateDashboardItems($this->sectorOperationalSignals($sectorProfile, $stats), 'signal');
        $premiumActionCenter = $this->decorateDashboardItems($this->premiumActionCenter($stats, $currentPeriodSummary, $onboarding, $appMonitoring), 'premium');
        $operationalWatchlist = $this->decorateDashboardItems($this->operationalWatchlist($stats, $monthStart, (int) ($notificationSummary['count'] ?? 0)), 'watch');
        $appCatalog = $this->decorateDashboardItems($this->appCatalog(), 'app');
        $executiveBrief = $this->executiveBriefingService->forDashboard($dashboardProfile['key'], $stats, $currentPeriodSummary, $appMonitoring, $onboarding);

        return view('dashboard.index', [
            'dashboardProfile' => $dashboardProfile,
            'dashboardKpis' => $dashboardKpis,
            'roleSpotlight' => $roleSpotlight,
            'stats' => $stats,
            'recentActivities' => $recentActivities,
            'recentStockMovements' => $recentStockMovements,
            'onboarding' => $onboarding,
            'showOnboardingBanner' => $showOnboardingBanner,
            'currentPeriodSummary' => $currentPeriodSummary,
            'sectorProfile' => $sectorProfile,
            'sectorActionPlan' => $sectorActionPlan,
            'sectorSignals' => $sectorSignals,
            'premiumBrief' => $this->premiumBrief($premiumActionCenter, $dashboardProfile['key']),
            'premiumActionCenter' => $premiumActionCenter,
            'operationalWatchlist' => $operationalWatchlist,
            'appCatalog' => $appCatalog,
        ]);
    }

    private function isPureCashier(?User $user): bool
    {
        if (! $user?->hasRole('cashier')) {
            return false;
        }

        foreach (['platform_admin', 'company_admin', 'director', 'manager', 'pos_supervisor'] as $role) {
            if ($user->hasRole($role)) {
                return false;
            }
        }

        return true;
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

    private function availableStockReferences(?int $companyId, ?int $branchId): int
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
            ->where('is_active', true)
            ->joinSub($balances, 'balances', fn ($join) => $join->on('products.id', '=', 'balances.product_id'))
            ->where('balances.current_stock', '>', 0)
            ->count();
    }

    private function mobileMoneyWatch(?int $companyId, ?int $branchId): array
    {
        if (! $companyId) {
            return [
                'mobile_money_unreconciled_count' => 0,
                'mobile_money_unreconciled_amount' => 0.0,
                'mobile_money_missing_reference_count' => 0,
            ];
        }

        $payments = Payment::query()
            ->where('company_id', $companyId)
            ->when($branchId, fn ($query, $selectedBranchId) => $query->where('branch_id', $selectedBranchId))
            ->whereIn('method', $this->mobileMoneyMethods())
            ->whereDoesntHave('reconciliationItem')
            ->get(['direction', 'amount', 'reference']);

        return [
            'mobile_money_unreconciled_count' => (int) $payments->count(),
            'mobile_money_unreconciled_amount' => round((float) $payments->sum(function (Payment $payment): float {
                return $payment->direction === 'out'
                    ? -1 * (float) $payment->amount
                    : (float) $payment->amount;
            }), 2),
            'mobile_money_missing_reference_count' => (int) $payments
                ->filter(fn (Payment $payment) => blank(trim((string) $payment->reference)))
                ->count(),
        ];
    }

    private function internalTransferDepositWatch(?int $companyId, ?int $branchId): array
    {
        if (! $companyId) {
            return [
                'internal_transfer_pending_bank_count' => 0,
                'internal_transfer_pending_bank_amount' => 0.0,
                'internal_transfer_pending_bank_stale_count' => 0,
                'internal_transfer_pending_bank_missing_reference_count' => 0,
                'internal_transfer_pending_bank_documented_count' => 0,
                'internal_transfer_pending_bank_documented_amount' => 0.0,
                'internal_transfer_pending_bank_documented_stale_count' => 0,
                'internal_transfer_pending_bank_oldest_date' => null,
            ];
        }

        $thresholdDate = Carbon::now()->subDays(2)->startOfDay();
        $payments = Payment::query()
            ->withCount('attachments')
            ->where('company_id', $companyId)
            ->when($branchId, fn ($query, $selectedBranchId) => $query->where('branch_id', $selectedBranchId))
            ->where('payment_type', 'internal_transfer')
            ->where('direction', 'in')
            ->whereHas('cashAccount', fn ($query) => $query->whereIn('type', $this->externalReconciliationAccountTypes()))
            ->whereDoesntHave('reconciliationItem')
            ->get(['amount', 'payment_date', 'reference']);
        $documentedPayments = $payments
            ->filter(fn (Payment $payment) => $this->paymentReadyForExternalReconciliation($payment))
            ->values();

        return [
            'internal_transfer_pending_bank_count' => (int) $payments->count(),
            'internal_transfer_pending_bank_amount' => round((float) $payments->sum('amount'), 2),
            'internal_transfer_pending_bank_stale_count' => (int) $payments
                ->filter(fn (Payment $payment) => $payment->payment_date && $payment->payment_date->startOfDay()->lte($thresholdDate))
                ->count(),
            'internal_transfer_pending_bank_missing_reference_count' => (int) $payments
                ->filter(fn (Payment $payment) => $this->paymentNeedsDepositProofAttention($payment))
                ->count(),
            'internal_transfer_pending_bank_documented_count' => (int) $documentedPayments->count(),
            'internal_transfer_pending_bank_documented_amount' => round((float) $documentedPayments->sum('amount'), 2),
            'internal_transfer_pending_bank_documented_stale_count' => (int) $documentedPayments
                ->filter(fn (Payment $payment) => $payment->payment_date && $payment->payment_date->startOfDay()->lte($thresholdDate))
                ->count(),
            'internal_transfer_pending_bank_oldest_date' => $payments
                ->sortBy(fn (Payment $payment) => $payment->payment_date?->timestamp ?? PHP_INT_MAX)
                ->first()?->payment_date,
        ];
    }

    private function mobileMoneyMethods(): array
    {
        return ['wave', 'orange_money', 'moov_money', 'mobile_money'];
    }

    private function externalReconciliationAccountTypes(): array
    {
        return ['bank', 'mobile_money'];
    }

    private function paymentNeedsDepositProofAttention(Payment $payment): bool
    {
        return blank(trim((string) $payment->reference))
            && ((int) ($payment->attachments_count ?? 0) === 0);
    }

    private function paymentReadyForExternalReconciliation(Payment $payment): bool
    {
        return filled(trim((string) $payment->reference))
            || ((int) ($payment->attachments_count ?? 0) > 0);
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

    private function dashboardProfile(?User $user, array $businessVocabulary = []): array
    {
        $salesLabel = $businessVocabulary['sales'] ?? 'Ventes';
        $stockLabel = $businessVocabulary['stock'] ?? 'Stock';
        $customersLabel = $businessVocabulary['clients'] ?? 'Clients';

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
                'priorities' => ['Point de vente', 'Encaissements du jour', $stockLabel.' critique'],
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
                'priorities' => [$salesLabel.' du jour', 'Receptions fournisseurs', 'Approvisionnement'],
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
                'priorities' => ['Resultat du mois', 'Recouvrement '.$customersLabel, 'Approbations a arbitrer'],
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

    private function dashboardKpis(string $profileKey, array $stats, array $businessVocabulary = []): array
    {
        $customerLabel = strtolower($businessVocabulary['client'] ?? 'client');
        $customersLabel = strtolower($businessVocabulary['clients'] ?? 'clients');
        $productLabel = strtolower($businessVocabulary['product'] ?? 'produit');
        $productsLabel = $businessVocabulary['products'] ?? 'Produits';
        $saleLabel = strtolower($businessVocabulary['sale'] ?? 'vente');
        $salesLabel = $businessVocabulary['sales'] ?? 'Ventes';
        $stockLabel = $businessVocabulary['stock'] ?? 'Stock';
        $suppliersLabel = strtolower($businessVocabulary['suppliers'] ?? 'fournisseurs');

        $kpis = match ($profileKey) {
            'cashier' => [
                ['label' => $salesLabel.' du jour', 'value' => $this->money($stats['ventes_jour']), 'description' => $stats['ventes_jour_count'].' ticket(s) valide(s) aujourd hui.'],
                ['label' => 'Encaissements du jour', 'value' => $this->money($stats['encaissements_jour']), 'description' => $stats['encaissements_jour_count'].' encaissement(s) enregistres.'],
                ['label' => 'Alertes '.$stockLabel, 'value' => $this->number($stats['alertes_stock']), 'description' => ucfirst(strtolower($productsLabel)).' au minimum sur l agence active.'],
                ['label' => 'Alertes non lues', 'value' => $this->number($stats['alertes_non_lues']), 'description' => 'Signaux d exploitation a lire.'],
                ['label' => $productsLabel.' suivis', 'value' => $this->number($stats['produits']), 'description' => 'Catalogue disponible pour la '.$saleLabel.'.'],
                ['label' => 'Comptes de caisse', 'value' => $this->number($stats['comptes_tresorerie']), 'description' => 'Comptes de tresorerie disponibles.'],
            ],
            'operations' => [
                ['label' => $salesLabel.' du jour', 'value' => $this->money($stats['ventes_jour']), 'description' => $stats['ventes_jour_count'].' facture(s) validee(s) aujourd hui.'],
                ['label' => 'Receptions du jour', 'value' => $this->number($stats['receptions_jour']), 'description' => 'Receptions fournisseurs enregistrees.'],
                ['label' => 'Achats en attente', 'value' => $this->number($stats['achats_en_attente']), 'description' => 'Documents a pousser vers validation.'],
                ['label' => 'Demandes d achat ouvertes', 'value' => $this->number($stats['demandes_achat_ouvertes']), 'description' => 'Demandes a convertir ou arbitrer.'],
                ['label' => 'Commandes '.$customersLabel.' ouvertes', 'value' => $this->number($stats['commandes_clients_ouvertes']), 'description' => 'Commandes encore en cours de livraison ou de conversion.'],
                ['label' => $stockLabel.' critique', 'value' => $this->number($stats['alertes_stock']), 'description' => ucfirst(strtolower($productsLabel)).' a surveiller sur l agence active.'],
            ],
            'direction' => [
                ['label' => 'Resultat du mois', 'value' => $this->money($stats['resultat_mois']), 'description' => 'Lecture comptable de la performance mensuelle.'],
                ['label' => $salesLabel.' du mois', 'value' => $this->money($stats['ventes_mois']), 'description' => 'Facturation '.$customerLabel.' validee sur la periode.'],
                ['label' => 'Reste a encaisser', 'value' => $this->money($stats['reste_a_encaisser']), 'description' => $stats['factures_impayees'].' facture(s) '.$customerLabel.' encore ouvertes.'],
                ['label' => 'Dettes '.$suppliersLabel, 'value' => $this->money($stats['dettes_fournisseurs']), 'description' => $stats['factures_fournisseurs_impayees'].' facture(s) a regler.'],
                ['label' => 'Approbations en attente', 'value' => $this->number($stats['approbations_en_attente_total']), 'description' => 'Ventes, achats et depenses a arbitrer.'],
                ['label' => 'Ecritures du mois', 'value' => $this->number($stats['ecritures_mois']), 'description' => 'Production comptable deja generee.'],
            ],
            default => [
                ['label' => 'Agences', 'value' => $this->number($stats['agences']), 'description' => 'Implantations actives dans la societe.'],
                ['label' => 'Utilisateurs', 'value' => $this->number($stats['utilisateurs']), 'description' => 'Equipe active sur l ERP.'],
                ['label' => $businessVocabulary['clients'] ?? 'Clients', 'value' => $this->number($stats['clients']), 'description' => 'Portefeuille '.$customerLabel.' disponible.'],
                ['label' => $productsLabel, 'value' => $this->number($stats['produits']), 'description' => 'Catalogue actuellement suivi.'],
                ['label' => $salesLabel.' du mois', 'value' => $this->money($stats['ventes_mois']), 'description' => 'Facturation validee sur la periode.'],
                ['label' => 'Resultat du mois', 'value' => $this->money($stats['resultat_mois']), 'description' => 'Lecture comptable du mois en cours.'],
                ['label' => 'Ecritures du mois', 'value' => $this->number($stats['ecritures_mois']), 'description' => 'Ecritures comptables generees.'],
                ['label' => 'Alertes '.$stockLabel, 'value' => $this->number($stats['alertes_stock']), 'description' => ucfirst(strtolower($productsLabel)).' a traiter rapidement.'],
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
                ['permission' => 'imports.manage', 'label' => 'Imports Excel/CSV', 'description' => 'Charger clients, fournisseurs, produits ou historiques.', 'url' => route('imports.index')],
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
        $actions = collect($sectorProfile['recommended_modules_full'] ?? [])
            ->map(function (array $item): array {
                $item['url'] = route($item['route_name'], $item['route_params'] ?? []);

                return $item;
            })
            ->all();

        return $this->filterAuthorizedItems($actions);
    }

    private function sectorOperationalSignals(array $sectorProfile, array $stats): array
    {
        $items = match ($sectorProfile['key'] ?? null) {
            'wholesale_distribution' => [
                [
                    'permission' => 'orders.view',
                    'label' => 'Commandes a risque stock',
                    'value' => $this->number((int) ($stats['wholesale_orders_at_risk_count'] ?? 0)),
                    'description' => ($stats['wholesale_orders_at_risk_count'] ?? 0).' commande(s) couvrent mal '.($stats['wholesale_order_lines_at_risk_count'] ?? 0).' ligne(s) pour '.number_format((float) ($stats['wholesale_at_risk_shortage_qty'] ?? 0), 3, ',', ' ').' unite(s) encore non couvertes.',
                    'url' => route('orders.index', ['coverage_state' => 'at_risk']),
                ],
                [
                    'permission' => 'orders.view',
                    'label' => 'Commandes couvertes par appro',
                    'value' => $this->number((int) ($stats['wholesale_orders_incoming_cover_count'] ?? 0)),
                    'description' => ($stats['wholesale_orders_incoming_cover_count'] ?? 0).' commande(s) dependent deja d achats confirmes sur '.($stats['wholesale_order_lines_incoming_cover_count'] ?? 0).' ligne(s).',
                    'url' => route('orders.index', ['coverage_state' => 'incoming']),
                ],
                [
                    'permission' => 'orders.view',
                    'label' => 'Engagements en retard',
                    'value' => $this->number((int) ($stats['wholesale_overdue_backlog_orders_count'] ?? 0)),
                    'description' => ($stats['wholesale_overdue_backlog_orders_count'] ?? 0).' commande(s) gardent un reliquat ouvert pour '.number_format((float) ($stats['wholesale_overdue_backlog_remaining_qty'] ?? 0), 3, ',', ' ').' unite(s) apres la date promise.',
                    'url' => route('orders.index', ['delivery_focus' => 'overdue']),
                ],
            ],
            'food_store' => [
                [
                    'permission' => 'stock.view',
                    'label' => 'Lots courts a ecouler',
                    'value' => $this->number((int) ($stats['food_short_dated_lots_count'] ?? 0)),
                    'description' => ($stats['food_short_dated_lots_count'] ?? 0).' lot(s) sur '.($stats['food_short_dated_products_count'] ?? 0).' produit(s) expirent sous 7 jours pour '.number_format((float) ($stats['food_short_dated_quantity'] ?? 0), 3, ',', ' ').' unite(s) encore a ecouler.',
                    'url' => route('stock.lots', ['status' => 'expiring', 'availability' => 'available', 'expiry_window_days' => 7]),
                ],
                [
                    'permission' => 'stock.view',
                    'label' => 'Ruptures rayon vendables',
                    'value' => $this->number((int) ($stats['food_saleable_stockout_count'] ?? 0)),
                    'description' => ($stats['food_saleable_stockout_count'] ?? 0).' reference(s) ont deja tourne en stock mais n ont plus rien de vendable au comptoir.',
                    'url' => route('stock.index', ['saleability_state' => 'zero']),
                ],
                [
                    'permission' => 'stock.view',
                    'label' => 'Rayon vendable critique',
                    'value' => $this->number((int) ($stats['food_saleable_critical_count'] ?? 0)),
                    'description' => ($stats['food_saleable_critical_count'] ?? 0).' reference(s) restent vendables mais sont deja au seuil mini rayon.',
                    'url' => route('stock.index', ['saleability_state' => 'critical']),
                ],
            ],
            'pharmacy_parapharmacy' => [
                [
                    'permission' => 'stock.view',
                    'label' => 'Lots proches de peremption',
                    'value' => $this->number((int) ($stats['pharmacy_expiring_lots_count'] ?? 0)),
                    'description' => ($stats['pharmacy_expiring_lots_count'] ?? 0).' lot(s) sur '.($stats['pharmacy_expiring_products_count'] ?? 0).' produit(s) expirent sous 30 jours.',
                    'url' => route('stock.lots', ['status' => 'expiring', 'availability' => 'available']),
                ],
                [
                    'permission' => 'stock.view',
                    'label' => 'Lots expires encore en stock',
                    'value' => $this->number((int) ($stats['pharmacy_expired_lots_count'] ?? 0)),
                    'description' => ($stats['pharmacy_expired_lots_count'] ?? 0).' lot(s) sur '.($stats['pharmacy_expired_products_count'] ?? 0).' produit(s) sont deja expires mais encore disponibles.',
                    'url' => route('stock.lots', ['status' => 'expired', 'availability' => 'available']),
                ],
                [
                    'permission' => 'stock.view',
                    'label' => 'Produits traces sans stock vendable',
                    'value' => $this->number((int) ($stats['pharmacy_tracked_products_saleable_zero_count'] ?? 0)),
                    'description' => ($stats['pharmacy_tracked_products_saleable_zero_count'] ?? 0).' reference(s) n ont plus aucun lot non expire disponible.',
                    'url' => route('stock.index', ['tracking_type' => 'tracked', 'saleability_state' => 'zero']),
                ],
            ],
            'restaurant_cafe' => [
                [
                    'permission' => 'pos.view',
                    'label' => 'Encaissements service',
                    'value' => $this->money($stats['encaissements_jour']),
                    'description' => $stats['encaissements_jour_count'].' encaissement(s) aujourd hui au comptoir.',
                    'url' => route('payments.index', ['date_from' => now()->toDateString(), 'date_to' => now()->toDateString()]),
                ],
                [
                    'permission' => 'sales.view',
                    'label' => 'Ventes restaurant',
                    'value' => $this->money($stats['ventes_jour']),
                    'description' => $stats['ventes_jour_count'].' ticket(s) ou facture(s) valide(s) aujourd hui.',
                    'url' => route('sales.index', ['date_from' => now()->toDateString(), 'date_to' => now()->toDateString()]),
                ],
                [
                    'permission' => 'stock.view',
                    'label' => 'Ingredients a surveiller',
                    'value' => $this->number($stats['alertes_stock']),
                    'description' => 'Produits au minimum avant blocage du service.',
                    'url' => route('stock.index', ['stock_state' => 'low']),
                ],
            ],
            'school_training' => [
                [
                    'permission' => 'sales.view',
                    'label' => 'Frais a encaisser',
                    'value' => $this->money($stats['reste_a_encaisser']),
                    'description' => $stats['factures_impayees'].' facture(s) scolaire(s) encore ouvertes.',
                    'url' => route('sales.index', ['payment_status' => 'unpaid']),
                ],
                [
                    'permission' => 'payments.view',
                    'label' => 'Encaissements du mois',
                    'value' => $this->money($stats['encaissements_mois']),
                    'description' => 'Paiements eleves et familles deja enregistres.',
                    'url' => route('payments.index', ['direction' => 'in']),
                ],
                [
                    'permission' => 'expenses.view',
                    'label' => 'Charges ecole',
                    'value' => $this->money($stats['depenses_mois']),
                    'description' => 'Depenses validees sur le mois courant.',
                    'url' => route('expenses.index'),
                ],
            ],
            'auto_parts_garage' => [
                [
                    'permission' => 'orders.view',
                    'label' => 'Dossiers atelier ouverts',
                    'value' => $this->number($stats['commandes_clients_ouvertes']),
                    'description' => 'Commandes ou travaux client encore en cours.',
                    'url' => route('orders.index'),
                ],
                [
                    'permission' => 'stock.view',
                    'label' => 'Pieces critiques',
                    'value' => $this->number($stats['alertes_stock']),
                    'description' => 'Pieces au minimum pouvant bloquer une intervention.',
                    'url' => route('stock.index', ['stock_state' => 'low']),
                ],
                [
                    'permission' => 'sales.view',
                    'label' => 'Factures garage impayees',
                    'value' => $this->number($stats['factures_impayees']),
                    'description' => $this->money($stats['reste_a_encaisser']).' reste(nt) a encaisser.',
                    'url' => route('sales.index', ['payment_status' => 'unpaid']),
                ],
            ],
            'services_agency' => [
                [
                    'permission' => 'sales.view',
                    'label' => 'Prestations facturees',
                    'value' => $this->money($stats['ventes_mois']),
                    'description' => 'Chiffre facture sur le mois courant.',
                    'url' => route('sales.index'),
                ],
                [
                    'permission' => 'sales.view',
                    'label' => 'Paiements clients attendus',
                    'value' => $this->money($stats['reste_a_encaisser']),
                    'description' => $stats['factures_impayees'].' facture(s) prestation encore ouvertes.',
                    'url' => route('sales.index', ['payment_status' => 'unpaid']),
                ],
                [
                    'permission' => 'expenses.view',
                    'label' => 'Frais de mission',
                    'value' => $this->money($stats['depenses_mois']),
                    'description' => 'Charges validees sur le mois.',
                    'url' => route('expenses.index'),
                ],
            ],
            'beauty_salon' => [
                [
                    'permission' => 'pos.view',
                    'label' => 'Caisse salon',
                    'value' => $this->money($stats['encaissements_jour']),
                    'description' => $stats['encaissements_jour_count'].' encaissement(s) client aujourd hui.',
                    'url' => route('payments.index', ['date_from' => now()->toDateString(), 'date_to' => now()->toDateString()]),
                ],
                [
                    'permission' => 'sales.view',
                    'label' => 'Prestations du jour',
                    'value' => $this->money($stats['ventes_jour']),
                    'description' => $stats['ventes_jour_count'].' vente(s) ou prestation(s) validee(s).',
                    'url' => route('sales.index', ['date_from' => now()->toDateString(), 'date_to' => now()->toDateString()]),
                ],
                [
                    'permission' => 'stock.view',
                    'label' => 'Consommables faibles',
                    'value' => $this->number($stats['alertes_stock']),
                    'description' => 'Produits salon a reapprovisionner.',
                    'url' => route('stock.index', ['stock_state' => 'low']),
                ],
            ],
            'workshop_manufacturing' => [
                [
                    'permission' => 'orders.view',
                    'label' => 'Commandes atelier',
                    'value' => $this->number($stats['commandes_clients_ouvertes']),
                    'description' => 'Demandes client encore en cours de production ou livraison.',
                    'url' => route('orders.index'),
                ],
                [
                    'permission' => 'stock.view',
                    'label' => 'Matieres critiques',
                    'value' => $this->number($stats['alertes_stock']),
                    'description' => 'Stocks minimum pouvant ralentir la production.',
                    'url' => route('stock.index', ['stock_state' => 'low']),
                ],
                [
                    'permission' => 'purchases.view',
                    'label' => 'Approvisionnements ouverts',
                    'value' => $this->number($stats['commandes_fournisseurs_ouvertes']),
                    'description' => 'Commandes fournisseurs encore en cours.',
                    'url' => route('purchase-orders.index'),
                ],
            ],
            'delivery_company' => [
                [
                    'permission' => 'sales.view',
                    'label' => 'Livraisons facturees',
                    'value' => $this->number($stats['ventes_jour_count']),
                    'description' => $this->money($stats['ventes_jour']).' facture(s) aujourd hui.',
                    'url' => route('sales.index', ['date_from' => now()->toDateString(), 'date_to' => now()->toDateString()]),
                ],
                [
                    'permission' => 'payments.view',
                    'label' => 'Encaissements livreurs',
                    'value' => $this->money($stats['encaissements_jour']),
                    'description' => $stats['encaissements_jour_count'].' paiement(s) enregistres aujourd hui.',
                    'url' => route('payments.index', ['date_from' => now()->toDateString(), 'date_to' => now()->toDateString()]),
                ],
                [
                    'permission' => 'expenses.view',
                    'label' => 'Frais operationnels',
                    'value' => $this->money($stats['depenses_mois']),
                    'description' => 'Carburant, charges et frais du mois.',
                    'url' => route('expenses.index'),
                ],
            ],
            default => [],
        };

        return $this->filterAuthorizedItems($items);
    }

    private function quickLinks(): array
    {
        return collect([
            ['permission' => 'sales.manage', 'label' => 'Nouvelle vente', 'description' => 'Creer une facture client', 'url' => route('sales.create')],
            ['permission' => 'purchases.manage', 'label' => 'Nouvel achat', 'description' => 'Creer une facture fournisseur', 'url' => route('purchases.create')],
            ['permission' => 'expenses.manage', 'label' => 'Nouvelle depense', 'description' => 'Saisir une charge', 'url' => route('expenses.create')],
            ['permission' => 'approvals.view', 'label' => 'Traiter les approbations', 'description' => 'Ouvrir la boite d approbation', 'url' => route('approvals.index')],
            ['permission' => 'reports.view', 'label' => 'Rapports dirigeants', 'description' => 'Voir les syntheses', 'url' => route('reports.index')],
            ['permission' => 'imports.manage', 'label' => 'Importer Excel/CSV', 'description' => 'Clients, fournisseurs, produits, stock', 'url' => route('imports.index')],
        ])->filter(fn (array $link) => auth()->user()?->hasPermission($link['permission']))->values()->all();
    }

    private function appCatalog(): array
    {
        return $this->filterAuthorizedItems([
            ['permission' => 'dashboard.view', 'label' => 'Dashboard', 'short_label' => 'Dashboard', 'description' => 'Vue generale', 'url' => route('dashboard'), 'icon' => 'gauge'],
            ['permission' => 'dashboard.view', 'label' => 'Demarrage', 'short_label' => 'Demarrage', 'description' => 'Parcours de mise en route', 'url' => route('onboarding.index'), 'icon' => 'rocket'],
            ['permission' => 'approvals.view', 'label' => 'Approbations', 'short_label' => 'Approbations', 'description' => 'Documents a valider', 'url' => route('approvals.index'), 'icon' => 'approval'],
            ['permission' => 'reports.view', 'label' => 'Rapports', 'short_label' => 'Rapports', 'description' => 'Chiffres et syntheses', 'url' => route('reports.index'), 'icon' => 'report'],
            ['permission' => 'budgets.view', 'label' => 'Budgets', 'short_label' => 'Budgets', 'description' => 'Suivi budgetaire', 'url' => route('budgets.index'), 'icon' => 'gauge'],
            ['permission' => 'notifications.view', 'label' => 'Alertes', 'short_label' => 'Alertes', 'description' => 'Alertes internes', 'url' => route('notifications.index'), 'icon' => 'alert'],
            ['permission' => 'automation.view', 'label' => 'Automatisation', 'short_label' => 'Automatisation', 'description' => 'Regles automatiques', 'url' => route('automation.index'), 'icon' => 'flash'],
            ['permission' => 'notifications.outbound.view', 'label' => 'Notif. sortantes', 'short_label' => 'Notif. sortantes', 'description' => 'SMS et emails', 'url' => route('notifications.outbound.index'), 'icon' => 'alert'],
            ['permission' => 'imports.manage', 'label' => 'Imports Excel/CSV', 'short_label' => 'Imports Excel', 'description' => 'Chargement de donnees', 'url' => route('imports.index'), 'icon' => 'import'],
            ['permission' => 'ops.view', 'label' => 'Operations', 'short_label' => 'Operations', 'description' => 'Controle technique', 'url' => route('ops.index'), 'icon' => 'ops'],
            ['permission' => 'platform.view', 'label' => 'Plateforme', 'short_label' => 'Plateforme', 'description' => 'Connecteurs et sante', 'url' => route('platform.index'), 'icon' => 'ops'],
            ['permission' => 'companies.view', 'label' => 'Entreprises', 'short_label' => 'Entreprises', 'description' => 'Societes', 'url' => route('companies.index'), 'icon' => 'building'],
            ['permission' => 'branches.view', 'label' => 'Agences', 'short_label' => 'Agences', 'description' => 'Sites et agences', 'url' => route('branches.index'), 'icon' => 'building'],
            ['permission' => 'users.view', 'label' => 'Utilisateurs', 'short_label' => 'Utilisateurs', 'description' => 'Comptes equipe', 'url' => route('users.index'), 'icon' => 'team'],
            ['permission' => 'roles.view', 'label' => 'Roles', 'short_label' => 'Roles', 'description' => 'Acces et droits', 'url' => route('roles.index'), 'icon' => 'team'],
            ['permission' => 'settings.view', 'label' => 'Parametres', 'short_label' => 'Parametres', 'description' => 'Reglages ERP', 'url' => route('settings.index'), 'icon' => 'settings'],
            ['permission' => 'customers.view', 'label' => 'Clients', 'short_label' => 'Clients', 'description' => 'Portefeuille client', 'url' => route('customers.index'), 'icon' => 'customer'],
            ['permission' => 'suppliers.view', 'label' => 'Fournisseurs', 'short_label' => 'Fournisseurs', 'description' => 'Base fournisseurs', 'url' => route('suppliers.index'), 'icon' => 'team'],
            ['permission' => 'categories.view', 'label' => 'Categories', 'short_label' => 'Categories', 'description' => 'Familles produits', 'url' => route('categories.index'), 'icon' => 'grid'],
            ['permission' => 'products.view', 'label' => 'Produits', 'short_label' => 'Produits', 'description' => 'Catalogue', 'url' => route('products.index'), 'icon' => 'stock'],
            ['permission' => 'stock.view', 'label' => 'Inventaire', 'short_label' => 'Inventaire', 'description' => 'Stock et mouvements', 'url' => route('stock.index'), 'icon' => 'stock'],
            ['permission' => 'stock.view', 'label' => 'Lots', 'short_label' => 'Lots', 'description' => 'Lots et peremption', 'url' => route('stock.lots'), 'icon' => 'stock'],
            ['permission' => 'stock_counts.view', 'label' => 'Inventaires physiques', 'short_label' => 'Inventaires', 'description' => 'Comptages', 'url' => route('stock-counts.index'), 'icon' => 'gauge'],
            ['permission' => 'crm.view', 'label' => 'CRM', 'short_label' => 'CRM', 'description' => 'Pipeline commercial', 'url' => route('crm.index'), 'icon' => 'customer'],
            ['permission' => 'quotes.view', 'label' => 'Devis', 'short_label' => 'Devis', 'description' => 'Offres clients', 'url' => route('quotes.index'), 'icon' => 'document'],
            ['permission' => 'orders.view', 'label' => 'Commandes', 'short_label' => 'Commandes', 'description' => 'Commandes clients', 'url' => route('orders.index'), 'icon' => 'orders'],
            ['permission' => 'delivery_notes.view', 'label' => 'Livraisons', 'short_label' => 'Livraisons', 'description' => 'Bons de livraison', 'url' => route('delivery-notes.index'), 'icon' => 'truck'],
            ['permission' => 'pos.view', 'label' => 'Point de Vente', 'short_label' => 'Point de Vente', 'description' => 'Caisse comptoir', 'url' => route('pos.index'), 'icon' => 'pos'],
            ['permission' => 'sales.view', 'label' => 'Ventes', 'short_label' => 'Ventes', 'description' => 'Factures clients', 'url' => route('sales.index'), 'icon' => 'sell'],
            ['permission' => 'credit_notes.view', 'label' => 'Avoirs', 'short_label' => 'Avoirs', 'description' => 'Credits clients', 'url' => route('credit-notes.index'), 'icon' => 'document'],
            ['permission' => 'collections.view', 'label' => 'Recouvrement', 'short_label' => 'Recouvrement', 'description' => 'Impayes clients', 'url' => route('collections.index'), 'icon' => 'wallet'],
            ['permission' => 'purchase_requests.view', 'label' => 'Demandes achat', 'short_label' => 'Demandes achat', 'description' => 'Demandes d appro', 'url' => route('purchase-requests.index'), 'icon' => 'buy'],
            ['permission' => 'purchase_requests.view', 'label' => 'Reappro', 'short_label' => 'Reappro', 'description' => 'Reappro automatique', 'url' => route('replenishments.index'), 'icon' => 'flash'],
            ['permission' => 'purchases.view', 'label' => 'Achats', 'short_label' => 'Achats', 'description' => 'Factures fournisseurs', 'url' => route('purchases.index'), 'icon' => 'buy'],
            ['permission' => 'supplier_credit_notes.view', 'label' => 'Avoirs fournisseurs', 'short_label' => 'Avoirs four.', 'description' => 'Credits fournisseurs', 'url' => route('purchase-credit-notes.index'), 'icon' => 'document'],
            ['permission' => 'purchase_orders.view', 'label' => 'Cmd fournisseurs', 'short_label' => 'Cmd fournisseurs', 'description' => 'Commandes fournisseurs', 'url' => route('purchase-orders.index'), 'icon' => 'orders'],
            ['permission' => 'goods_receipts.view', 'label' => 'Receptions', 'short_label' => 'Receptions', 'description' => 'Receptions fournisseurs', 'url' => route('goods-receipts.index'), 'icon' => 'truck'],
            ['permission' => 'cash_accounts.view', 'label' => 'Comptes', 'short_label' => 'Comptes', 'description' => 'Comptes de tresorerie', 'url' => route('cash-accounts.index'), 'icon' => 'bank'],
            ['permission' => 'payments.view', 'label' => 'Paiements', 'short_label' => 'Paiements', 'description' => 'Encaissements et reglements', 'url' => route('payments.index'), 'icon' => 'wallet'],
            ['permission' => 'reconciliations.view', 'label' => 'Rapprochements', 'short_label' => 'Rapprochements', 'description' => 'Rapprochement bancaire', 'url' => route('treasury-reconciliations.index'), 'icon' => 'bank'],
            ['permission' => 'expenses.view', 'label' => 'Depenses', 'short_label' => 'Depenses', 'description' => 'Charges et sorties', 'url' => route('expenses.index'), 'icon' => 'expense'],
            ['permission' => 'expense_categories.view', 'label' => 'Cat. depenses', 'short_label' => 'Cat. depenses', 'description' => 'Familles de charges', 'url' => route('expense-categories.index'), 'icon' => 'expense'],
            ['permission' => 'accounting.view', 'label' => 'Comptabilite', 'short_label' => 'Comptabilite', 'description' => 'Plan et journaux', 'url' => route('accounting.accounts.index'), 'icon' => 'report'],
            ['permission' => 'hr.view', 'label' => 'RH', 'short_label' => 'RH', 'description' => 'Capital humain', 'url' => route('hr.index'), 'icon' => 'team'],
            ['permission' => 'payroll.view', 'label' => 'Paie', 'short_label' => 'Paie', 'description' => 'Gestion paie', 'url' => route('payroll.index'), 'icon' => 'wallet'],
            ['permission' => 'projects.view', 'label' => 'Projets', 'short_label' => 'Projets', 'description' => 'Pilotage execution', 'url' => route('projects.index'), 'icon' => 'pulse'],
            ['permission' => 'manufacturing.view', 'label' => 'Production', 'short_label' => 'Production', 'description' => 'Ordres et BOM', 'url' => route('manufacturing.index'), 'icon' => 'stock'],
            ['permission' => 'commerce.view', 'label' => 'Commerce unifie', 'short_label' => 'Commerce', 'description' => 'Vue commerciale et stock', 'url' => route('commerce.index'), 'icon' => 'sell'],
            ['permission' => 'activity_logs.view', 'label' => 'Journal activite', 'short_label' => 'Journal activite', 'description' => 'Trace des actions', 'url' => route('activity-logs.index'), 'icon' => 'pulse'],
        ]);
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
                'permission' => 'payments.view',
                'label' => 'Mobile money a rapprocher',
                'count' => $stats['mobile_money_unreconciled_count'] ?? 0,
                'description' => $this->money((float) ($stats['mobile_money_unreconciled_amount'] ?? 0)).' encore ouverts'.(($stats['mobile_money_missing_reference_count'] ?? 0) > 0 ? ' · '.($stats['mobile_money_missing_reference_count']).' reference(s) manquante(s).' : '.'),
                'url' => route('payments.index', ['reconciliation_status' => 'unreconciled']),
            ],
            [
                'permission' => 'payments.view',
                'label' => 'Versements agence a rapprocher',
                'count' => $stats['internal_transfer_pending_bank_count'] ?? 0,
                'description' => $this->money((float) ($stats['internal_transfer_pending_bank_amount'] ?? 0)).' en attente de confirmation bancaire'.(($stats['internal_transfer_pending_bank_stale_count'] ?? 0) > 0 ? ' · '.($stats['internal_transfer_pending_bank_stale_count']).' depot(s) depuis 2+ jours.' : '.'),
                'url' => route('payments.index', ['payment_type' => 'internal_transfer', 'reconciliation_status' => 'unreconciled']),
            ],
            [
                'permission' => 'payments.view',
                'label' => 'Versements sans bordereau',
                'count' => $stats['internal_transfer_pending_bank_missing_reference_count'] ?? 0,
                'description' => ($stats['internal_transfer_pending_bank_missing_reference_count'] ?? 0).' depot(s) restent sans reference ni justificatif exploitable.',
                'url' => route('payments.index', ['deposit_missing_reference' => 1]),
            ],
            [
                'permission' => 'payments.view',
                'label' => 'Versements documentes a rapprocher',
                'count' => $stats['internal_transfer_pending_bank_documented_count'] ?? 0,
                'description' => $this->money((float) ($stats['internal_transfer_pending_bank_documented_amount'] ?? 0)).' disposent deja d une preuve de depot exploitable.',
                'url' => route('payments.index', ['deposit_documented' => 1]),
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

    private function pharmacySafetyWatch(?int $companyId, ?int $branchId, array $sectorProfile): array
    {
        if (! $companyId || ($sectorProfile['key'] ?? null) !== 'pharmacy_parapharmacy') {
            return [
                'pharmacy_expiring_lots_count' => 0,
                'pharmacy_expiring_products_count' => 0,
                'pharmacy_expired_lots_count' => 0,
                'pharmacy_expired_products_count' => 0,
                'pharmacy_tracked_products_saleable_zero_count' => 0,
            ];
        }

        $today = Carbon::today()->toDateString();
        $horizon = Carbon::today()->addDays(30)->toDateString();

        $lotScope = ProductLot::query()
            ->where('company_id', $companyId)
            ->when($branchId, fn ($query, $selectedBranchId) => $query->where('branch_id', $selectedBranchId))
            ->where('quantity_available', '>', 0.0001)
            ->whereHas('product', fn ($query) => $query
                ->where('type', 'stockable')
                ->where('is_active', true)
                ->where('sale_ok', true)
                ->whereIn('tracking_type', ['lot', 'serial']));

        $expiringLots = (clone $lotScope)
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '>', $today)
            ->whereDate('expires_at', '<=', $horizon);

        $expiredLots = (clone $lotScope)
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<=', $today);

        $saleableLotBalances = ProductLot::query()
            ->select('product_id')
            ->selectRaw('SUM(quantity_available) as saleable_qty')
            ->where('company_id', $companyId)
            ->when($branchId, fn ($query, $selectedBranchId) => $query->where('branch_id', $selectedBranchId))
            ->where(function ($query) use ($today) {
                $query->whereNull('expires_at')
                    ->orWhereDate('expires_at', '>=', $today);
            })
            ->groupBy('product_id');

        $trackedProducts = Product::query()
            ->where('products.company_id', $companyId)
            ->where('products.type', 'stockable')
            ->where('products.is_active', true)
            ->where('products.sale_ok', true)
            ->whereIn('products.tracking_type', ['lot', 'serial'])
            ->leftJoinSub($saleableLotBalances, 'saleable_balances', fn ($join) => $join->on('products.id', '=', 'saleable_balances.product_id'))
            ->select(['products.id'])
            ->selectRaw('COALESCE(saleable_balances.saleable_qty, 0) as saleable_qty')
            ->get();

        return [
            'pharmacy_expiring_lots_count' => (clone $expiringLots)->count(),
            'pharmacy_expiring_products_count' => (clone $expiringLots)->distinct('product_id')->count('product_id'),
            'pharmacy_expired_lots_count' => (clone $expiredLots)->count(),
            'pharmacy_expired_products_count' => (clone $expiredLots)->distinct('product_id')->count('product_id'),
            'pharmacy_tracked_products_saleable_zero_count' => (int) $trackedProducts
                ->filter(fn (Product $product) => (float) ($product->saleable_qty ?? 0) <= 0.0001)
                ->count(),
        ];
    }

    private function foodStoreRetailWatch(?int $companyId, ?int $branchId, array $sectorProfile): array
    {
        if (! $companyId || ($sectorProfile['key'] ?? null) !== 'food_store') {
            return [
                'food_short_dated_lots_count' => 0,
                'food_short_dated_products_count' => 0,
                'food_short_dated_quantity' => 0.0,
                'food_saleable_stockout_count' => 0,
                'food_saleable_critical_count' => 0,
            ];
        }

        $today = Carbon::today()->toDateString();
        $horizon = Carbon::today()->addDays(7)->toDateString();

        $shortDatedLots = ProductLot::query()
            ->where('company_id', $companyId)
            ->when($branchId, fn ($query, $selectedBranchId) => $query->where('branch_id', $selectedBranchId))
            ->where('quantity_available', '>', 0.0001)
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '>', $today)
            ->whereDate('expires_at', '<=', $horizon)
            ->whereHas('product', fn ($query) => $query
                ->where('type', 'stockable')
                ->where('is_active', true)
                ->where('sale_ok', true));

        $saleableProducts = $this->foodStoreSaleableShelfProducts($companyId, $branchId);

        return [
            'food_short_dated_lots_count' => (clone $shortDatedLots)->count(),
            'food_short_dated_products_count' => (clone $shortDatedLots)->distinct('product_id')->count('product_id'),
            'food_short_dated_quantity' => round((float) (clone $shortDatedLots)->sum('quantity_available'), 3),
            'food_saleable_stockout_count' => (int) $saleableProducts
                ->filter(fn (Product $product) => (float) ($product->saleable_stock ?? 0) <= 0.0001)
                ->count(),
            'food_saleable_critical_count' => (int) $saleableProducts
                ->filter(fn (Product $product) => (float) ($product->saleable_stock ?? 0) > 0.0001 && (float) ($product->saleable_stock ?? 0) <= (float) ($product->min_stock ?? 0))
                ->count(),
        ];
    }

    private function foodStoreSaleableShelfProducts(int $companyId, ?int $branchId = null)
    {
        $today = Carbon::today()->toDateString();
        $balances = StockMovement::query()
            ->select('product_id')
            ->selectRaw('SUM(quantity_in - quantity_out) as current_stock')
            ->where('company_id', $companyId)
            ->when($branchId, fn ($query, $selectedBranchId) => $query->where('branch_id', $selectedBranchId))
            ->groupBy('product_id');

        $saleableLotBalances = ProductLot::query()
            ->select('product_id')
            ->selectRaw('SUM(quantity_available) as saleable_qty')
            ->where('company_id', $companyId)
            ->when($branchId, fn ($query, $selectedBranchId) => $query->where('branch_id', $selectedBranchId))
            ->where('quantity_available', '>', 0.0001)
            ->where(function ($query) use ($today) {
                $query->whereNull('expires_at')
                    ->orWhereDate('expires_at', '>=', $today);
            })
            ->groupBy('product_id');

        $saleableStockExpression = "CASE WHEN products.tracking_type IN ('lot', 'serial') THEN COALESCE(saleable_balances.saleable_qty, 0) ELSE COALESCE(balances.current_stock, 0) END";

        return Product::query()
            ->where('products.company_id', $companyId)
            ->where('products.type', 'stockable')
            ->where('products.is_active', true)
            ->where('products.sale_ok', true)
            ->where('products.min_stock', '>', 0)
            ->where(function ($query) use ($companyId, $branchId) {
                $query->whereHas('stockMovements', fn ($movementQuery) => $movementQuery
                    ->where('company_id', $companyId)
                    ->when($branchId, fn ($scopedQuery, $selectedBranchId) => $scopedQuery->where('branch_id', $selectedBranchId)))
                    ->orWhereHas('lots', fn ($lotQuery) => $lotQuery
                        ->where('company_id', $companyId)
                        ->when($branchId, fn ($scopedQuery, $selectedBranchId) => $scopedQuery->where('branch_id', $selectedBranchId)));
            })
            ->leftJoinSub($balances, 'balances', fn ($join) => $join->on('products.id', '=', 'balances.product_id'))
            ->leftJoinSub($saleableLotBalances, 'saleable_balances', fn ($join) => $join->on('products.id', '=', 'saleable_balances.product_id'))
            ->select(['products.id', 'products.name', 'products.sku', 'products.min_stock', 'products.tracking_type'])
            ->selectRaw($saleableStockExpression.' as saleable_stock')
            ->orderBy('products.name')
            ->get();
    }

    private function wholesaleDistributionWatch(?int $companyId, ?int $branchId, array $sectorProfile): array
    {
        if (! $companyId || ($sectorProfile['key'] ?? null) !== 'wholesale_distribution') {
            return [
                'wholesale_orders_at_risk_count' => 0,
                'wholesale_order_lines_at_risk_count' => 0,
                'wholesale_at_risk_shortage_qty' => 0.0,
                'wholesale_orders_incoming_cover_count' => 0,
                'wholesale_order_lines_incoming_cover_count' => 0,
                'wholesale_overdue_backlog_orders_count' => 0,
                'wholesale_overdue_backlog_remaining_qty' => 0.0,
                'wholesale_oldest_overdue_target_date' => null,
            ];
        }

        $summary = $this->orderCoverageService->wholesalePortfolioSummary($companyId, $branchId);

        return [
            'wholesale_orders_at_risk_count' => (int) ($summary['orders_at_risk_count'] ?? 0),
            'wholesale_order_lines_at_risk_count' => (int) ($summary['order_lines_at_risk_count'] ?? 0),
            'wholesale_at_risk_shortage_qty' => (float) ($summary['at_risk_shortage_qty'] ?? 0),
            'wholesale_orders_incoming_cover_count' => (int) ($summary['orders_incoming_cover_count'] ?? 0),
            'wholesale_order_lines_incoming_cover_count' => (int) ($summary['order_lines_incoming_cover_count'] ?? 0),
            'wholesale_overdue_backlog_orders_count' => (int) ($summary['overdue_backlog_orders_count'] ?? 0),
            'wholesale_overdue_backlog_remaining_qty' => (float) ($summary['overdue_backlog_remaining_qty'] ?? 0),
            'wholesale_oldest_overdue_target_date' => $summary['oldest_overdue_target_date'] ?? null,
        ];
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
                'permission' => 'payments.view',
                'priority' => (($stats['mobile_money_unreconciled_count'] ?? 0) > 0 || ($stats['mobile_money_missing_reference_count'] ?? 0) > 0) ? 'high' : 'low',
                'eyebrow' => 'Tresorerie terrain',
                'label' => 'Rapprocher les wallets mobiles',
                'metric' => $this->money((float) ($stats['mobile_money_unreconciled_amount'] ?? 0)),
                'description' => ($stats['mobile_money_unreconciled_count'] ?? 0).' flux mobile money ouverts'.(($stats['mobile_money_missing_reference_count'] ?? 0) > 0 ? ' · '.($stats['mobile_money_missing_reference_count']).' sans reference exploitable.' : '.'),
                'url' => route('payments.index', ['reconciliation_status' => 'unreconciled']),
            ],
            [
                'permission' => 'payments.view',
                'priority' => (($stats['internal_transfer_pending_bank_stale_count'] ?? 0) > 0 || ($stats['internal_transfer_pending_bank_count'] ?? 0) > 0) ? 'high' : 'low',
                'eyebrow' => 'Depot terrain',
                'label' => 'Confirmer les depots agence',
                'metric' => $this->money((float) ($stats['internal_transfer_pending_bank_amount'] ?? 0)),
                'description' => ($stats['internal_transfer_pending_bank_count'] ?? 0).' versement(s) attendent encore le releve bancaire'.(($stats['internal_transfer_pending_bank_stale_count'] ?? 0) > 0 ? ' · '.($stats['internal_transfer_pending_bank_stale_count']).' depuis 2+ jours.' : '.'),
                'url' => route('payments.index', ['payment_type' => 'internal_transfer', 'reconciliation_status' => 'unreconciled']),
            ],
            [
                'permission' => 'payments.view',
                'priority' => ($stats['internal_transfer_pending_bank_missing_reference_count'] ?? 0) > 0 ? 'high' : 'low',
                'eyebrow' => 'Piece depot',
                'label' => 'Regulariser les bordereaux depot',
                'metric' => $this->number((int) ($stats['internal_transfer_pending_bank_missing_reference_count'] ?? 0)),
                'description' => ($stats['internal_transfer_pending_bank_missing_reference_count'] ?? 0).' versement(s) sans reference ni justificatif exploitable bloquent le rapprochement.',
                'url' => route('payments.index', ['deposit_missing_reference' => 1]),
            ],
            [
                'permission' => 'payments.view',
                'priority' => (($stats['internal_transfer_pending_bank_documented_stale_count'] ?? 0) > 0 || ($stats['internal_transfer_pending_bank_documented_count'] ?? 0) > 0) ? 'medium' : 'low',
                'eyebrow' => 'Depot documente',
                'label' => 'Rapprocher les depots documentes',
                'metric' => $this->money((float) ($stats['internal_transfer_pending_bank_documented_amount'] ?? 0)),
                'description' => ($stats['internal_transfer_pending_bank_documented_count'] ?? 0).' versement(s) ont deja une preuve de depot'.(($stats['internal_transfer_pending_bank_documented_stale_count'] ?? 0) > 0 ? ' · '.($stats['internal_transfer_pending_bank_documented_stale_count']).' depuis 2+ jours.' : '.'),
                'url' => route('payments.index', ['deposit_documented' => 1]),
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

    private function decorateDashboardItems(array $items, string $group = 'generic'): array
    {
        return collect($items)
            ->map(fn (array $item) => $this->decorateDashboardItem($item, $group))
            ->values()
            ->all();
    }

    private function decorateDashboardItem(array $item, string $group = 'generic'): array
    {
        $label = (string) ($item['label'] ?? $item['title'] ?? '');
        $resolvedGroup = $item['group'] ?? $group;

        $decorated = [
            ...$item,
            'group' => $resolvedGroup,
            'icon' => $item['icon'] ?? $this->dashboardIconKey($label, (string) ($item['description'] ?? ''), $group),
            'short_label' => $item['short_label'] ?? $this->dashboardShortLabel($label),
        ];

        if ($resolvedGroup === 'app') {
            $decorated = [
                ...$decorated,
                ...$this->dashboardAppVisuals($decorated),
            ];
        }

        return $decorated;
    }

    private function dashboardAppVisuals(array $item): array
    {
        $permission = (string) ($item['permission'] ?? '');
        $label = (string) ($item['label'] ?? '');
        $text = Str::lower(Str::ascii(trim($permission.' '.$label.' '.((string) ($item['description'] ?? '')).' '.((string) ($item['icon'] ?? '')))));

        $familyKey = match (true) {
            Str::contains($text, ['dashboard', 'demarrage', 'rocket']) => 'launch',
            Str::contains($text, ['approval', 'approb', 'alerte', 'notif', 'automation', 'import', 'operation', 'plateforme', 'journal activite']) => 'control',
            Str::contains($text, ['entreprise', 'agence', 'utilisateur', 'role', 'parametre']) => 'admin',
            Str::contains($text, ['client', 'crm', 'devis', 'commande', 'livraison', 'vente', 'point de vente', 'avoir', 'recouvrement', 'commerce']) => 'commerce',
            Str::contains($text, ['fournisseur', 'achat', 'reception', 'reappro']) => 'procurement',
            Str::contains($text, ['categorie', 'produit', 'inventaire', 'stock', 'lot', 'production']) => 'inventory',
            Str::contains($text, ['rapport', 'budget', 'compte', 'paiement', 'rapproch', 'depense', 'comptabilite']) => 'finance',
            Str::contains($text, ['rh', 'paie', 'projet']) => 'people',
            default => 'launch',
        };

        $familyLabels = [
            'launch' => 'Accueil',
            'control' => 'Pilotage',
            'admin' => 'Administration',
            'commerce' => 'Commerce',
            'procurement' => 'Achats',
            'inventory' => 'Stock',
            'finance' => 'Finance',
            'people' => 'Equipe',
        ];

        $variants = [
            'launch' => [
                ['app_accent' => '#0f766e', 'app_surface' => '#effaf8', 'app_soft' => '#d7f3ee', 'app_border' => '#b6e7de', 'app_ink' => '#0b4f56', 'app_muted' => '#4b6d70', 'app_shadow' => 'rgba(15, 118, 110, 0.16)', 'app_badge_start' => '#ffffff', 'app_badge_end' => '#bfece5'],
                ['app_accent' => '#dc7a24', 'app_surface' => '#fff8ef', 'app_soft' => '#fde8cf', 'app_border' => '#f6d1a2', 'app_ink' => '#8c4b09', 'app_muted' => '#8a6a49', 'app_shadow' => 'rgba(220, 122, 36, 0.18)', 'app_badge_start' => '#fffdf8', 'app_badge_end' => '#f9d6a5'],
                ['app_accent' => '#2563eb', 'app_surface' => '#f4f7ff', 'app_soft' => '#dce8ff', 'app_border' => '#bfd3ff', 'app_ink' => '#18439f', 'app_muted' => '#5d6d8c', 'app_shadow' => 'rgba(37, 99, 235, 0.16)', 'app_badge_start' => '#ffffff', 'app_badge_end' => '#ccdbff'],
            ],
            'control' => [
                ['app_accent' => '#d9485f', 'app_surface' => '#fff4f6', 'app_soft' => '#ffd9e0', 'app_border' => '#f8b4c1', 'app_ink' => '#8d2342', 'app_muted' => '#91636c', 'app_shadow' => 'rgba(217, 72, 95, 0.18)', 'app_badge_start' => '#fffafb', 'app_badge_end' => '#ffc7d1'],
                ['app_accent' => '#7c3aed', 'app_surface' => '#f7f2ff', 'app_soft' => '#e5d8ff', 'app_border' => '#cfb6ff', 'app_ink' => '#5322aa', 'app_muted' => '#6f5c91', 'app_shadow' => 'rgba(124, 58, 237, 0.18)', 'app_badge_start' => '#fcfbff', 'app_badge_end' => '#ddd0ff'],
                ['app_accent' => '#0891b2', 'app_surface' => '#f0fbff', 'app_soft' => '#d4f2fb', 'app_border' => '#afe3f4', 'app_ink' => '#0d5f72', 'app_muted' => '#52747d', 'app_shadow' => 'rgba(8, 145, 178, 0.16)', 'app_badge_start' => '#fbfeff', 'app_badge_end' => '#c1ebf6'],
            ],
            'admin' => [
                ['app_accent' => '#475569', 'app_surface' => '#f6f8fb', 'app_soft' => '#e5ebf4', 'app_border' => '#ccd7e6', 'app_ink' => '#334155', 'app_muted' => '#66758a', 'app_shadow' => 'rgba(71, 85, 105, 0.15)', 'app_badge_start' => '#ffffff', 'app_badge_end' => '#d7e0ee'],
                ['app_accent' => '#7c3f99', 'app_surface' => '#fbf5ff', 'app_soft' => '#ecdaf6', 'app_border' => '#d8b9eb', 'app_ink' => '#5b2a73', 'app_muted' => '#7a678a', 'app_shadow' => 'rgba(124, 63, 153, 0.16)', 'app_badge_start' => '#fffaff', 'app_badge_end' => '#e3d0f0'],
                ['app_accent' => '#0f4c81', 'app_surface' => '#f2f8fc', 'app_soft' => '#d5e9f7', 'app_border' => '#b5d7ee', 'app_ink' => '#123c61', 'app_muted' => '#5d768c', 'app_shadow' => 'rgba(15, 76, 129, 0.16)', 'app_badge_start' => '#fbfeff', 'app_badge_end' => '#c8e2f2'],
            ],
            'commerce' => [
                ['app_accent' => '#f97316', 'app_surface' => '#fff6ee', 'app_soft' => '#ffe0c7', 'app_border' => '#ffc896', 'app_ink' => '#9a4a09', 'app_muted' => '#8f6c4f', 'app_shadow' => 'rgba(249, 115, 22, 0.18)', 'app_badge_start' => '#fffaf5', 'app_badge_end' => '#ffd2ad'],
                ['app_accent' => '#e85d75', 'app_surface' => '#fff4f7', 'app_soft' => '#ffd7df', 'app_border' => '#f7b4c2', 'app_ink' => '#962d4e', 'app_muted' => '#8c6170', 'app_shadow' => 'rgba(232, 93, 117, 0.18)', 'app_badge_start' => '#fffafc', 'app_badge_end' => '#ffc8d4'],
                ['app_accent' => '#ec4899', 'app_surface' => '#fff3fb', 'app_soft' => '#fbd5ed', 'app_border' => '#f6b1df', 'app_ink' => '#9d2262', 'app_muted' => '#93677d', 'app_shadow' => 'rgba(236, 72, 153, 0.18)', 'app_badge_start' => '#fffafe', 'app_badge_end' => '#f8c7e7'],
            ],
            'procurement' => [
                ['app_accent' => '#d97706', 'app_surface' => '#fffbeb', 'app_soft' => '#fde7bf', 'app_border' => '#f7cf8e', 'app_ink' => '#8d4c05', 'app_muted' => '#8b7052', 'app_shadow' => 'rgba(217, 119, 6, 0.16)', 'app_badge_start' => '#fffdf7', 'app_badge_end' => '#f7d79f'],
                ['app_accent' => '#c17c00', 'app_surface' => '#fff8e8', 'app_soft' => '#f6e3b8', 'app_border' => '#ecd08a', 'app_ink' => '#805500', 'app_muted' => '#89704c', 'app_shadow' => 'rgba(193, 124, 0, 0.16)', 'app_badge_start' => '#fffdf7', 'app_badge_end' => '#f2d89e'],
                ['app_accent' => '#b45309', 'app_surface' => '#fff7ed', 'app_soft' => '#fed9aa', 'app_border' => '#f8bd7b', 'app_ink' => '#7c3f06', 'app_muted' => '#896551', 'app_shadow' => 'rgba(180, 83, 9, 0.16)', 'app_badge_start' => '#fffaf5', 'app_badge_end' => '#f8c795'],
            ],
            'inventory' => [
                ['app_accent' => '#059669', 'app_surface' => '#effcf7', 'app_soft' => '#cdf7e6', 'app_border' => '#a7ebd2', 'app_ink' => '#0c654d', 'app_muted' => '#4f756b', 'app_shadow' => 'rgba(5, 150, 105, 0.18)', 'app_badge_start' => '#fafffd', 'app_badge_end' => '#b9f0da'],
                ['app_accent' => '#16a34a', 'app_surface' => '#f2fcf3', 'app_soft' => '#d7f5dd', 'app_border' => '#b7e8c0', 'app_ink' => '#166534', 'app_muted' => '#54755a', 'app_shadow' => 'rgba(22, 163, 74, 0.18)', 'app_badge_start' => '#fbfffc', 'app_badge_end' => '#c7eecf'],
                ['app_accent' => '#65a30d', 'app_surface' => '#f8fceb', 'app_soft' => '#e6f7c8', 'app_border' => '#d3eb9f', 'app_ink' => '#4d7c0f', 'app_muted' => '#6f7e58', 'app_shadow' => 'rgba(101, 163, 13, 0.18)', 'app_badge_start' => '#fdfff8', 'app_badge_end' => '#dcf2b8'],
            ],
            'finance' => [
                ['app_accent' => '#2563eb', 'app_surface' => '#f5f8ff', 'app_soft' => '#dfe9ff', 'app_border' => '#c0d4ff', 'app_ink' => '#18439f', 'app_muted' => '#5d6d8c', 'app_shadow' => 'rgba(37, 99, 235, 0.18)', 'app_badge_start' => '#ffffff', 'app_badge_end' => '#cfddff'],
                ['app_accent' => '#4f46e5', 'app_surface' => '#f5f5ff', 'app_soft' => '#e3e0ff', 'app_border' => '#c6c2ff', 'app_ink' => '#3730a3', 'app_muted' => '#68658b', 'app_shadow' => 'rgba(79, 70, 229, 0.18)', 'app_badge_start' => '#ffffff', 'app_badge_end' => '#d5d2ff'],
                ['app_accent' => '#0284c7', 'app_surface' => '#f0f9ff', 'app_soft' => '#d5efff', 'app_border' => '#acdefd', 'app_ink' => '#0c5a84', 'app_muted' => '#5b7486', 'app_shadow' => 'rgba(2, 132, 199, 0.18)', 'app_badge_start' => '#fbfdff', 'app_badge_end' => '#c4e8ff'],
            ],
            'people' => [
                ['app_accent' => '#9333ea', 'app_surface' => '#faf5ff', 'app_soft' => '#ead7ff', 'app_border' => '#d6b4ff', 'app_ink' => '#6b21a8', 'app_muted' => '#7a668d', 'app_shadow' => 'rgba(147, 51, 234, 0.18)', 'app_badge_start' => '#fffaff', 'app_badge_end' => '#e1cbff'],
                ['app_accent' => '#db2777', 'app_surface' => '#fff5fa', 'app_soft' => '#ffd9ea', 'app_border' => '#f7b6d3', 'app_ink' => '#9d174d', 'app_muted' => '#946879', 'app_shadow' => 'rgba(219, 39, 119, 0.18)', 'app_badge_start' => '#fffafe', 'app_badge_end' => '#ffc8df'],
                ['app_accent' => '#8b5cf6', 'app_surface' => '#f7f4ff', 'app_soft' => '#e5dcff', 'app_border' => '#cebeff', 'app_ink' => '#5b21b6', 'app_muted' => '#756a91', 'app_shadow' => 'rgba(139, 92, 246, 0.18)', 'app_badge_start' => '#fefdff', 'app_badge_end' => '#dbd0ff'],
            ],
        ];

        $paletteOptions = $variants[$familyKey] ?? $variants['launch'];
        $variantIndex = abs((int) crc32($label !== '' ? $label : $permission)) % count($paletteOptions);

        return [
            'app_family' => $familyLabels[$familyKey] ?? 'Applications',
            ...$paletteOptions[$variantIndex],
        ];
    }

    private function simpleLaunchpad(
        string $profileKey,
        array $roleActionPlan,
        array $quickLinks,
        array $sectorActionPlan,
        array $premiumActionCenter,
    ): array {
        $roleItems = collect($roleActionPlan)->map(fn (array $item) => [
            ...$item,
            'source_label' => 'Routine',
        ]);
        $quickItems = collect($quickLinks)->map(fn (array $item) => [
            ...$item,
            'source_label' => 'Rapide',
        ]);
        $sectorItems = collect($sectorActionPlan)->map(fn (array $item) => [
            ...$item,
            'source_label' => 'Metier',
        ]);
        $priorityItems = collect($premiumActionCenter)
            ->filter(fn (array $item) => ($item['priority'] ?? 'low') !== 'low')
            ->map(fn (array $item) => [
                ...$item,
                'source_label' => 'Priorite',
            ]);

        $ordered = $profileKey === 'pilotage'
            ? $quickItems->concat($priorityItems)->concat($roleItems)->concat($sectorItems)
            : $roleItems->concat($quickItems)->concat($priorityItems)->concat($sectorItems);

        return $ordered
            ->unique('url')
            ->take(8)
            ->values()
            ->all();
    }

    private function filterAuthorizedItems(array $items): array
    {
        return collect($items)
            ->filter(fn (array $item) => ! isset($item['permission']) || auth()->user()?->hasPermission($item['permission']))
            ->values()
            ->all();
    }

    private function dashboardIconKey(string $label, string $description = '', string $group = 'generic'): string
    {
        $text = Str::lower(Str::ascii(trim($label.' '.$description)));

        return match (true) {
            Str::contains($text, ['caisse', 'pos', 'comptoir']) => 'pos',
            Str::contains($text, ['vente', 'encais', 'recouvr']) => 'sell',
            Str::contains($text, ['achat', 'appro', 'fournisseur', 'reception']) => 'buy',
            Str::contains($text, ['depense', 'charge']) => 'expense',
            Str::contains($text, ['approb', 'valid', 'arbitr']) => 'approval',
            Str::contains($text, ['rapport', 'resultat', 'journal', 'ecriture']) => 'report',
            Str::contains($text, ['import']) => 'import',
            Str::contains($text, ['stock', 'lot', 'produit', 'rayon']) => 'stock',
            Str::contains($text, ['alerte', 'risque', 'bloqu', 'retard']) => 'alert',
            Str::contains($text, ['wallet', 'mobile money', 'wave', 'orange money', 'moov']) => 'wallet',
            Str::contains($text, ['depot', 'bordereau', 'banque', 'rapproch', 'reconciliation']) => 'bank',
            Str::contains($text, ['parametre', 'reglage', 'societe']) => 'settings',
            Str::contains($text, ['sante', 'plateforme', 'technique']) => 'ops',
            Str::contains($text, ['demarrage', 'mise en route']) => 'rocket',
            Str::contains($text, ['client', 'creance']) => 'customer',
            Str::contains($text, ['commande']) => 'orders',
            Str::contains($text, ['periode', 'cloture']) => 'calendar',
            Str::contains($text, ['recherche']) => 'search',
            default => match ($group) {
                'kpi' => 'gauge',
                'premium' => 'flash',
                'watch' => 'alert',
                'spotlight' => 'pulse',
                'sector', 'signal' => 'grid',
                default => 'grid',
            },
        };
    }

    private function dashboardShortLabel(string $label): string
    {
        $text = Str::lower(Str::ascii($label));

        return match (true) {
            Str::contains($text, ['nouvelle vente pos']) => 'Encaisser',
            Str::contains($text, ['ouvrir la caisse']) => 'Caisse',
            Str::contains($text, ['nouvelle vente', 'creer une vente']) => 'Vendre',
            Str::contains($text, ['nouvel achat', 'creer un achat']) => 'Acheter',
            Str::contains($text, ['nouvelle depense']) => 'Depenser',
            Str::contains($text, ['approb', 'valid', 'arbitr']) => 'Valider',
            Str::contains($text, ['rapport']) => 'Rapports',
            Str::contains($text, ['import']) => 'Importer',
            Str::contains($text, ['stock']) => 'Stock',
            Str::contains($text, ['alerte']) => 'Alertes',
            Str::contains($text, ['reception']) => 'Reception',
            Str::contains($text, ['demande']) => 'Demandes',
            Str::contains($text, ['recouvr']) => 'Recouvrer',
            Str::contains($text, ['journal']) => 'Journaux',
            Str::contains($text, ['parametre', 'reglage']) => 'Reglages',
            Str::contains($text, ['wallet', 'mobile money']) => 'Wallets',
            Str::contains($text, ['depot', 'bordereau']) => 'Depots',
            Str::contains($text, ['sante', 'plateforme']) => 'Controle',
            default => Str::of($label)->words(2, '')->trim()->value(),
        };
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
