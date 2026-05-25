<?php

namespace App\Modules\Core\Notifications\Services;

use App\Models\User;
use App\Modules\Accounting\Services\PeriodChecklistService;
use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Approvals\Models\ApprovalStep;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Services\SectorProfileService;
use App\Modules\Core\Notifications\Models\InternalNotification;
use App\Modules\Core\Ops\Services\ApplicationMonitoringService;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Inventory\Models\ProductLot;
use App\Modules\Inventory\Models\StockCount;
use App\Modules\Inventory\Models\StockCountItem;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Purchases\Models\PurchaseBill;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Services\OrderCoverageService;
use App\Modules\Treasury\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class NotificationService
{
    public function __construct(
        private readonly PeriodChecklistService $periodChecklistService,
        private readonly ApplicationMonitoringService $applicationMonitoringService,
        private readonly SectorProfileService $sectorProfileService,
        private readonly OrderCoverageService $orderCoverageService,
    ) {}

    public function syncCompanyAlerts(int $companyId, ?int $branchId = null): void
    {
        $summary = $this->periodChecklistService->currentPeriodSummary($companyId);
        $sectorProfile = $this->sectorProfileService->profileForCompany($companyId);

        $pendingSalesCount = SalesInvoice::query()->where('company_id', $companyId)->where('status', 'pending_approval')->count();
        $pendingPurchasesCount = PurchaseBill::query()->where('company_id', $companyId)->where('status', 'pending_approval')->count();
        $pendingExpensesCount = Expense::query()->where('company_id', $companyId)->where('status', 'pending_approval')->count();
        $stockAlertsCount = $this->stockAlerts($companyId, $branchId);

        if ($pendingSalesCount > 0) {
            $this->upsertSystemAlert($companyId, 'pending-sales-approval', [
                'branch_id' => null,
                'level' => 'warning',
                'title' => 'Ventes en attente d approbation',
                'message' => $pendingSalesCount.' vente(s) attendent encore une validation.',
                'action_url' => route('sales.index'),
                'meta' => ['count' => $pendingSalesCount],
            ]);
        } else {
            $this->resolveSystemAlert($companyId, 'pending-sales-approval');
        }

        if ($pendingPurchasesCount > 0) {
            $this->upsertSystemAlert($companyId, 'pending-purchases-approval', [
                'branch_id' => null,
                'level' => 'warning',
                'title' => 'Achats en attente d approbation',
                'message' => $pendingPurchasesCount.' achat(s) attendent encore une validation.',
                'action_url' => route('purchases.index'),
                'meta' => ['count' => $pendingPurchasesCount],
            ]);
        } else {
            $this->resolveSystemAlert($companyId, 'pending-purchases-approval');
        }

        if ($pendingExpensesCount > 0) {
            $this->upsertSystemAlert($companyId, 'pending-expenses-approval', [
                'branch_id' => null,
                'level' => 'warning',
                'title' => 'Depenses en attente d approbation',
                'message' => $pendingExpensesCount.' depense(s) attendent encore une validation.',
                'action_url' => route('expenses.index'),
                'meta' => ['count' => $pendingExpensesCount],
            ]);
        } else {
            $this->resolveSystemAlert($companyId, 'pending-expenses-approval');
        }

        if ($summary && ! $summary['can_close'] && ! $summary['period']?->isClosed()) {
            $this->upsertSystemAlert($companyId, 'current-period-close-blocked', [
                'branch_id' => null,
                'level' => 'danger',
                'title' => 'Cloture du mois bloquee',
                'message' => 'La periode courante '.$summary['period']?->name.' ne peut pas etre cloturee tant que des documents attendent une approbation.',
                'action_url' => route('accounting.periods.index'),
                'meta' => [
                    'period_id' => $summary['period']?->id,
                    'blockers' => $summary['blockers'],
                ],
            ]);
        } else {
            $this->resolveSystemAlert($companyId, 'current-period-close-blocked');
        }

        $stockCode = 'stock-alerts'.($branchId ? '-'.$branchId : '');
        if ($stockAlertsCount > 0) {
            $this->upsertSystemAlert($companyId, $stockCode, [
                'branch_id' => $branchId,
                'level' => 'warning',
                'title' => 'Stock sous minimum',
                'message' => $stockAlertsCount.' produit(s) sont au niveau du stock minimum ou en dessous dans l agence active.',
                'action_url' => route('stock.index'),
                'meta' => ['count' => $stockAlertsCount],
            ]);
        } else {
            $this->resolveSystemAlert($companyId, $stockCode);
        }

        $this->syncOverdueSalesAlert($companyId);
        $this->syncOverduePurchaseAlert($companyId);
        $this->syncStaleApprovalAlert($companyId);
        $this->syncExpenseSpikeAlert($companyId);
        $this->syncStockCountVarianceAlert($companyId);
        $this->syncLotExpiryAlerts($companyId, $branchId);
        $this->syncMobileMoneyReconciliationAlert($companyId, $branchId);
        $this->syncInternalTransferDepositAlert($companyId, $branchId);
        $this->syncDocumentedInternalTransferDepositAlert($companyId, $branchId);
        $this->syncTrackedSaleableStockAlert($companyId, $branchId);
        $this->syncFoodStoreShortDatedLotsAlert($companyId, $branchId, $sectorProfile);
        $this->syncFoodStoreSaleableStockoutAlert($companyId, $branchId, $sectorProfile);
        $this->syncWholesaleOrderCoverageRiskAlert($companyId, $branchId, $sectorProfile);
        $this->syncWholesaleOverdueCommitmentsAlert($companyId, $branchId, $sectorProfile);
        $this->syncBranchSalesDropAlerts($companyId);
        $this->syncTechnicalMonitoringAlerts($companyId);
    }

    public function syncCompanyAlertsIfStale(int $companyId, ?int $branchId = null, int $ttlSeconds = 120): void
    {
        $cacheKey = sprintf('notifications:sync:%d:%s', $companyId, $branchId ?: 'global');

        if (! Cache::add($cacheKey, now()->timestamp, max($ttlSeconds, 30))) {
            return;
        }

        $this->syncCompanyAlerts($companyId, $branchId);
    }

    public function summaryForCompany(int $companyId, ?int $branchId = null, int $limit = 5): array
    {
        $query = $this->activeQuery($companyId, $branchId)
            ->orderByRaw('CASE WHEN is_read = 0 THEN 0 ELSE 1 END')
            ->latest();

        return [
            'count' => (clone $query)->where('is_read', false)->count(),
            'items' => $query->limit($limit)->get(),
        ];
    }

    public function cachedSummaryForCompany(int $companyId, ?int $branchId = null, int $limit = 5, int $ttlSeconds = 30): array
    {
        return Cache::remember(
            $this->summaryCacheKey($companyId, $branchId, $limit),
            now()->addSeconds(max($ttlSeconds, 5)),
            fn (): array => $this->summaryForCompany($companyId, $branchId, $limit),
        );
    }

    public function indexQuery(int $companyId, string $scope = 'active', ?int $branchId = null): Builder
    {
        return $this->scopedQuery($companyId, $branchId)
            ->when($scope === 'active', fn (Builder $query) => $query->whereNull('resolved_at'))
            ->when($scope === 'resolved', fn (Builder $query) => $query->whereNotNull('resolved_at'))
            ->orderByRaw('CASE WHEN resolved_at IS NULL THEN 0 ELSE 1 END')
            ->orderByRaw('CASE WHEN is_read = 0 THEN 0 ELSE 1 END')
            ->latest();
    }

    public function markAsRead(InternalNotification $notification, User $user): InternalNotification
    {
        if (! $notification->is_read) {
            $notification->update([
                'is_read' => true,
                'read_at' => now(),
                'read_by' => $user->id,
            ]);

            $this->forgetSummaryCache($notification->company_id, $notification->branch_id);
        }

        return $notification->fresh(['reader', 'branch']);
    }

    public function markAllAsRead(int $companyId, User $user, ?int $branchId = null): void
    {
        $this->activeQuery($companyId, $branchId)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
                'read_by' => $user->id,
            ]);

        $this->forgetSummaryCache($companyId, $branchId);
    }

    private function syncOverdueSalesAlert(int $companyId): void
    {
        $today = now()->toDateString();
        $query = SalesInvoice::query()
            ->where('company_id', $companyId)
            ->where('status', 'validated')
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today);

        $count = (clone $query)->count();

        if ($count === 0) {
            $this->resolveSystemAlert($companyId, 'overdue-sales-balance');

            return;
        }

        $balance = (float) (clone $query)->sum('balance_due');
        $oldestDueDate = (clone $query)->min('due_date');
        $daysOverdue = $oldestDueDate ? Carbon::parse($oldestDueDate)->diffInDays(now()) : 0;

        $this->upsertSystemAlert($companyId, 'overdue-sales-balance', [
            'branch_id' => null,
            'level' => 'danger',
            'title' => 'Factures clients en retard',
            'message' => $count.' facture(s) client cumulent '.$this->money($balance).' de retard. Plus ancienne echeance : '.$this->formatDate($oldestDueDate).'.',
            'action_url' => route('sales.index', ['due_state' => 'overdue']),
            'meta' => [
                'count' => $count,
                'balance' => $balance,
                'days_overdue' => $daysOverdue,
                'oldest_due_date' => $oldestDueDate,
            ],
        ]);
    }

    private function syncOverduePurchaseAlert(int $companyId): void
    {
        $today = now()->toDateString();
        $query = PurchaseBill::query()
            ->where('company_id', $companyId)
            ->where('status', 'validated')
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today);

        $count = (clone $query)->count();

        if ($count === 0) {
            $this->resolveSystemAlert($companyId, 'overdue-purchase-balance');

            return;
        }

        $balance = (float) (clone $query)->sum('balance_due');
        $oldestDueDate = (clone $query)->min('due_date');
        $daysOverdue = $oldestDueDate ? Carbon::parse($oldestDueDate)->diffInDays(now()) : 0;

        $this->upsertSystemAlert($companyId, 'overdue-purchase-balance', [
            'branch_id' => null,
            'level' => 'warning',
            'title' => 'Factures fournisseurs en retard',
            'message' => $count.' facture(s) fournisseur restent a regler pour '.$this->money($balance).'. Plus ancienne echeance : '.$this->formatDate($oldestDueDate).'.',
            'action_url' => route('purchases.index', ['due_state' => 'overdue']),
            'meta' => [
                'count' => $count,
                'balance' => $balance,
                'days_overdue' => $daysOverdue,
                'oldest_due_date' => $oldestDueDate,
            ],
        ]);
    }

    private function syncStaleApprovalAlert(int $companyId): void
    {
        $threshold = now()->subHours(48);
        $query = ApprovalStep::query()
            ->where('company_id', $companyId)
            ->where('status', 'pending')
            ->where('created_at', '<=', $threshold);

        $count = (clone $query)->count();

        if ($count === 0) {
            $this->resolveSystemAlert($companyId, 'stale-approvals');

            return;
        }

        $oldestCreatedAt = (clone $query)->oldest('created_at')->value('created_at');
        $breakdown = (clone $query)
            ->select('module')
            ->selectRaw('COUNT(*) as aggregate_count')
            ->groupBy('module')
            ->orderByDesc('aggregate_count')
            ->get()
            ->map(fn (ApprovalStep $step) => $this->moduleLabel((string) $step->module).': '.$step->aggregate_count)
            ->implode(', ');

        $this->upsertSystemAlert($companyId, 'stale-approvals', [
            'branch_id' => null,
            'level' => 'warning',
            'title' => 'Approbations bloquees depuis plus de 48 h',
            'message' => $count.' etape(s) d approbation restent ouvertes depuis plus de 48 h'.($breakdown !== '' ? ' ('.$breakdown.').' : '.'),
            'action_url' => route('approvals.index'),
            'meta' => [
                'count' => $count,
                'oldest_created_at' => $oldestCreatedAt,
                'breakdown' => $breakdown,
            ],
        ]);
    }

    private function syncExpenseSpikeAlert(int $companyId): void
    {
        $recentExpenses = Expense::query()
            ->with(['category', 'branch'])
            ->where('company_id', $companyId)
            ->where('status', 'validated')
            ->whereDate('expense_date', '>=', now()->subDays(7)->toDateString())
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->get();

        $anomaly = null;
        $baselineAverage = 0.0;
        $baselineCount = 0;

        foreach ($recentExpenses as $expense) {
            $baselineQuery = Expense::query()
                ->where('company_id', $companyId)
                ->where('status', 'validated')
                ->where('expense_category_id', $expense->expense_category_id)
                ->where('branch_id', $expense->branch_id)
                ->whereDate('expense_date', '<', $expense->expense_date?->toDateString())
                ->whereDate('expense_date', '>=', $expense->expense_date?->copy()->subDays(30)->toDateString());

            $baselineCount = (clone $baselineQuery)->count();
            $baselineAverage = (float) ((clone $baselineQuery)->avg('total') ?? 0);

            if ($baselineCount < 4 || $baselineAverage <= 0) {
                continue;
            }

            $threshold = max($baselineAverage * 2.5, $baselineAverage + 50000);
            if ((float) $expense->total >= $threshold) {
                $anomaly = $expense;
                break;
            }
        }

        if (! $anomaly) {
            $this->resolveSystemAlert($companyId, 'expense-spike');

            return;
        }

        $this->upsertSystemAlert($companyId, 'expense-spike', [
            'branch_id' => $anomaly->branch_id,
            'level' => 'warning',
            'title' => 'Depense inhabituelle detectee',
            'message' => 'La depense '.$anomaly->expense_number.' atteint '.$this->money((float) $anomaly->total).' contre une moyenne recente de '.$this->money($baselineAverage).' pour '.($anomaly->category?->name ?? 'la categorie suivie').'.',
            'action_url' => route('expenses.index', ['search' => $anomaly->expense_number]),
            'meta' => [
                'expense_id' => $anomaly->id,
                'expense_number' => $anomaly->expense_number,
                'count' => $baselineCount,
                'amount' => (float) $anomaly->total,
                'baseline_average' => $baselineAverage,
            ],
        ]);
    }

    private function syncStockCountVarianceAlert(int $companyId): void
    {
        $stockCount = StockCount::query()
            ->with('branch')
            ->where('company_id', $companyId)
            ->where('status', 'posted')
            ->whereDate('count_date', '>=', now()->subDays(30)->toDateString())
            ->whereHas('items', fn (Builder $query) => $query->whereRaw('ABS(variance_qty) > 0.0001'))
            ->latest('posted_at')
            ->latest('id')
            ->first();

        if (! $stockCount) {
            $this->resolveSystemAlert($companyId, 'stock-count-variance');

            return;
        }

        $varianceSummary = StockCountItem::query()
            ->where('stock_count_id', $stockCount->id)
            ->whereRaw('ABS(variance_qty) > 0.0001')
            ->selectRaw('COUNT(*) as line_count, COALESCE(SUM(ABS(variance_qty) * unit_cost), 0) as impact_value')
            ->first();

        $lineCount = (int) ($varianceSummary?->line_count ?? 0);
        $impactValue = (float) ($varianceSummary?->impact_value ?? 0);

        $this->upsertSystemAlert($companyId, 'stock-count-variance', [
            'branch_id' => $stockCount->branch_id,
            'level' => 'warning',
            'title' => 'Ecart inventaire a verifier',
            'message' => 'L inventaire '.$stockCount->count_number.' remonte '.$lineCount.' ligne(s) en ecart pour un impact estime de '.$this->money($impactValue).'.',
            'action_url' => route('stock-counts.show', $stockCount),
            'meta' => [
                'stock_count_id' => $stockCount->id,
                'count' => $lineCount,
                'impact_value' => $impactValue,
            ],
        ]);
    }

    private function syncMobileMoneyReconciliationAlert(int $companyId, ?int $branchId = null): void
    {
        $scopeSuffix = $branchId ? '-'.$branchId : '';
        $query = Payment::query()
            ->where('company_id', $companyId)
            ->when($branchId, fn (Builder $builder, int $selectedBranchId) => $builder->where('branch_id', $selectedBranchId))
            ->whereIn('method', $this->mobileMoneyMethods())
            ->whereDoesntHave('reconciliationItem');

        $payments = (clone $query)->get(['id', 'direction', 'amount', 'reference', 'payment_date']);
        $count = (int) $payments->count();

        if ($count === 0) {
            $this->resolveSystemAlert($companyId, 'mobile-money-reconciliation-risk'.$scopeSuffix);

            return;
        }

        $balance = round((float) $payments->sum(function (Payment $payment): float {
            return $payment->direction === 'out'
                ? -1 * (float) $payment->amount
                : (float) $payment->amount;
        }), 2);
        $missingReferenceCount = (int) $payments->filter(fn (Payment $payment) => blank(trim((string) $payment->reference)))->count();
        $oldestPaymentDate = $payments->sortBy('payment_date')->first()?->payment_date;

        $this->upsertSystemAlert($companyId, 'mobile-money-reconciliation-risk'.$scopeSuffix, [
            'branch_id' => $branchId,
            'level' => $missingReferenceCount > 0 ? 'danger' : 'warning',
            'title' => 'Mobile money a rapprocher',
            'message' => $count.' flux mobile money restent ouverts pour '.$this->money($balance).'.'.($missingReferenceCount > 0 ? ' '.$missingReferenceCount.' sans reference exploitable.' : '').($oldestPaymentDate ? ' Plus ancienne date : '.$this->formatDate($oldestPaymentDate).'.' : ''),
            'action_url' => route('payments.index', ['reconciliation_status' => 'unreconciled']),
            'meta' => [
                'count' => $count,
                'balance' => $balance,
                'missing_reference_count' => $missingReferenceCount,
                'oldest_payment_date' => $oldestPaymentDate,
            ],
        ]);
    }

    private function syncInternalTransferDepositAlert(int $companyId, ?int $branchId = null): void
    {
        $scopeSuffix = $branchId ? '-'.$branchId : '';
        $thresholdDate = now()->subDays(2)->startOfDay();
        $query = Payment::query()
            ->withCount('attachments')
            ->where('company_id', $companyId)
            ->when($branchId, fn (Builder $builder, int $selectedBranchId) => $builder->where('branch_id', $selectedBranchId))
            ->where('payment_type', 'internal_transfer')
            ->where('direction', 'in')
            ->whereHas('cashAccount', fn (Builder $cashAccountQuery) => $cashAccountQuery->whereIn('type', $this->externalReconciliationAccountTypes()))
            ->whereDoesntHave('reconciliationItem');

        $payments = (clone $query)->get(['id', 'amount', 'payment_date', 'reference']);
        $count = (int) $payments->count();

        if ($count === 0) {
            $this->resolveSystemAlert($companyId, 'internal-transfer-deposit-risk'.$scopeSuffix);

            return;
        }

        $balance = round((float) $payments->sum('amount'), 2);
        $staleCount = (int) $payments
            ->filter(fn (Payment $payment) => $payment->payment_date && $payment->payment_date->startOfDay()->lte($thresholdDate))
            ->count();
        $missingReferenceCount = (int) $payments
            ->filter(fn (Payment $payment) => $this->paymentNeedsDepositProofAttention($payment))
            ->count();
        $oldestPaymentDate = $payments->sortBy('payment_date')->first()?->payment_date;

        $this->upsertSystemAlert($companyId, 'internal-transfer-deposit-risk'.$scopeSuffix, [
            'branch_id' => $branchId,
            'level' => ($staleCount > 0 || $missingReferenceCount > 0) ? 'danger' : 'warning',
            'title' => 'Versements agence a rapprocher',
            'message' => $count.' versement(s) agence attendent encore le releve bancaire pour '.$this->money($balance).'.'.($staleCount > 0 ? ' '.$staleCount.' depot(s) depuis 2+ jours.' : '').($missingReferenceCount > 0 ? ' '.$missingReferenceCount.' sans bordereau exploitable.' : '').($oldestPaymentDate ? ' Plus ancienne date : '.$this->formatDate($oldestPaymentDate).'.' : ''),
            'action_url' => route('payments.index', ['payment_type' => 'internal_transfer', 'reconciliation_status' => 'unreconciled']),
            'meta' => [
                'count' => $count,
                'balance' => $balance,
                'stale_count' => $staleCount,
                'missing_reference_count' => $missingReferenceCount,
                'oldest_payment_date' => $oldestPaymentDate,
            ],
        ]);
    }

    private function syncDocumentedInternalTransferDepositAlert(int $companyId, ?int $branchId = null): void
    {
        $scopeSuffix = $branchId ? '-'.$branchId : '';
        $thresholdDate = now()->subDays(2)->startOfDay();
        $query = Payment::query()
            ->withCount('attachments')
            ->where('company_id', $companyId)
            ->when($branchId, fn (Builder $builder, int $selectedBranchId) => $builder->where('branch_id', $selectedBranchId))
            ->where('payment_type', 'internal_transfer')
            ->where('direction', 'in')
            ->whereHas('cashAccount', fn (Builder $cashAccountQuery) => $cashAccountQuery->whereIn('type', $this->externalReconciliationAccountTypes()))
            ->whereDoesntHave('reconciliationItem');

        $payments = (clone $query)->get(['id', 'amount', 'payment_date', 'reference']);
        $documentedPayments = $payments
            ->filter(fn (Payment $payment) => $this->paymentReadyForExternalReconciliation($payment))
            ->values();
        $count = (int) $documentedPayments->count();

        if ($count === 0) {
            $this->resolveSystemAlert($companyId, 'internal-transfer-documented-deposit-risk'.$scopeSuffix);

            return;
        }

        $balance = round((float) $documentedPayments->sum('amount'), 2);
        $staleCount = (int) $documentedPayments
            ->filter(fn (Payment $payment) => $payment->payment_date && $payment->payment_date->startOfDay()->lte($thresholdDate))
            ->count();
        $oldestPaymentDate = $documentedPayments->sortBy('payment_date')->first()?->payment_date;

        $this->upsertSystemAlert($companyId, 'internal-transfer-documented-deposit-risk'.$scopeSuffix, [
            'branch_id' => $branchId,
            'level' => $staleCount > 0 ? 'danger' : 'warning',
            'title' => 'Versements documentes a rapprocher',
            'message' => $count.' versement(s) documentes attendent encore le rapprochement pour '.$this->money($balance).'.'.($staleCount > 0 ? ' '.$staleCount.' depot(s) depuis 2+ jours.' : '').($oldestPaymentDate ? ' Plus ancienne date : '.$this->formatDate($oldestPaymentDate).'.' : ''),
            'action_url' => route('payments.index', ['deposit_documented' => 1]),
            'meta' => [
                'count' => $count,
                'balance' => $balance,
                'stale_count' => $staleCount,
                'oldest_payment_date' => $oldestPaymentDate,
            ],
        ]);
    }

    private function syncLotExpiryAlerts(int $companyId, ?int $branchId = null): void
    {
        $scopeSuffix = $branchId ? '-'.$branchId : '';
        $today = now()->toDateString();
        $horizonDays = 30;
        $horizon = now()->addDays($horizonDays)->toDateString();

        $baseQuery = ProductLot::query()
            ->with(['product'])
            ->where('company_id', $companyId)
            ->when($branchId, fn (Builder $query, int $selectedBranchId) => $query->where('branch_id', $selectedBranchId))
            ->where('quantity_available', '>', 0.0001)
            ->whereNotNull('expires_at');

        $expiredQuery = (clone $baseQuery)->whereDate('expires_at', '<=', $today);
        $expiredCount = (clone $expiredQuery)->count();

        if ($expiredCount === 0) {
            $this->resolveSystemAlert($companyId, 'expired-product-lots'.$scopeSuffix);
        } else {
            $oldestExpiredAt = (clone $expiredQuery)->min('expires_at');
            $expiredHighlights = (clone $expiredQuery)
                ->orderBy('expires_at')
                ->limit(3)
                ->get()
                ->map(fn (ProductLot $lot) => ($lot->product?->display_name ?? $lot->product?->name ?? 'Produit').' · '.$lot->displayCode())
                ->implode(', ');

            $this->upsertSystemAlert($companyId, 'expired-product-lots'.$scopeSuffix, [
                'branch_id' => $branchId,
                'level' => 'danger',
                'title' => 'Lots expires encore disponibles',
                'message' => $expiredCount.' lot(s) encore disponibles sont deja expires. Plus ancienne echeance : '.$this->formatDate($oldestExpiredAt).($expiredHighlights !== '' ? ' Exemples : '.$expiredHighlights.'.' : '.'),
                'action_url' => route('stock.lots', ['status' => 'expired', 'availability' => 'available']),
                'meta' => [
                    'count' => $expiredCount,
                    'oldest_expiry_date' => $oldestExpiredAt,
                    'highlights' => $expiredHighlights,
                ],
            ]);
        }

        $expiringQuery = (clone $baseQuery)
            ->whereDate('expires_at', '>', $today)
            ->whereDate('expires_at', '<=', $horizon);
        $expiringCount = (clone $expiringQuery)->count();

        if ($expiringCount === 0) {
            $this->resolveSystemAlert($companyId, 'expiring-product-lots'.$scopeSuffix);

            return;
        }

        $nearestExpiryAt = (clone $expiringQuery)->min('expires_at');
        $expiringHighlights = (clone $expiringQuery)
            ->orderBy('expires_at')
            ->limit(3)
            ->get()
            ->map(fn (ProductLot $lot) => ($lot->product?->display_name ?? $lot->product?->name ?? 'Produit').' · '.$lot->displayCode())
            ->implode(', ');

        $this->upsertSystemAlert($companyId, 'expiring-product-lots'.$scopeSuffix, [
            'branch_id' => $branchId,
            'level' => 'warning',
            'title' => 'Lots bientot a echeance',
            'message' => $expiringCount.' lot(s) disponibles expirent sous '.$horizonDays.' jours. Prochaine echeance : '.$this->formatDate($nearestExpiryAt).($expiringHighlights !== '' ? ' A surveiller : '.$expiringHighlights.'.' : '.'),
            'action_url' => route('stock.lots', ['status' => 'expiring', 'availability' => 'available']),
            'meta' => [
                'count' => $expiringCount,
                'nearest_expiry_date' => $nearestExpiryAt,
                'horizon_days' => $horizonDays,
                'highlights' => $expiringHighlights,
            ],
        ]);
    }

    private function syncTrackedSaleableStockAlert(int $companyId, ?int $branchId = null): void
    {
        $scopeSuffix = $branchId ? '-'.$branchId : '';
        $products = $this->trackedProductsWithoutSaleableStock($companyId, $branchId);
        $count = (int) $products->count();

        if ($count === 0) {
            $this->resolveSystemAlert($companyId, 'tracked-saleable-stock-risk'.$scopeSuffix);

            return;
        }

        $highlights = $products
            ->take(3)
            ->map(fn (Product $product) => $product->display_name ?? $product->name ?? $product->sku ?? 'Produit')
            ->implode(', ');

        $this->upsertSystemAlert($companyId, 'tracked-saleable-stock-risk'.$scopeSuffix, [
            'branch_id' => $branchId,
            'level' => 'danger',
            'title' => 'Produits traces sans stock vendable',
            'message' => $count.' produit(s) traces n ont plus aucun lot non expire disponible pour la vente.'.($highlights !== '' ? ' Exemples : '.$highlights.'.' : ''),
            'action_url' => route('stock.index', ['tracking_type' => 'tracked', 'saleability_state' => 'zero']),
            'meta' => [
                'count' => $count,
                'highlights' => $highlights,
            ],
        ]);
    }

    private function syncFoodStoreShortDatedLotsAlert(int $companyId, ?int $branchId = null, array $sectorProfile = []): void
    {
        $scopeSuffix = $branchId ? '-'.$branchId : '';

        if (($sectorProfile['key'] ?? null) !== 'food_store') {
            $this->resolveSystemAlert($companyId, 'food-store-short-dated-lots'.$scopeSuffix);

            return;
        }

        $lots = $this->foodStoreShortDatedLots($companyId, $branchId);
        $count = (int) $lots->count();

        if ($count === 0) {
            $this->resolveSystemAlert($companyId, 'food-store-short-dated-lots'.$scopeSuffix);

            return;
        }

        $productCount = $lots->pluck('product_id')->unique()->count();
        $quantity = round((float) $lots->sum(fn (ProductLot $lot) => (float) $lot->quantity_available), 3);
        $nearestExpiryAt = $lots->sortBy('expires_at')->first()?->expires_at;
        $highlights = $lots
            ->take(3)
            ->map(fn (ProductLot $lot) => ($lot->product?->display_name ?? $lot->product?->name ?? 'Produit').' · '.$lot->displayCode())
            ->implode(', ');

        $this->upsertSystemAlert($companyId, 'food-store-short-dated-lots'.$scopeSuffix, [
            'branch_id' => $branchId,
            'level' => 'warning',
            'title' => 'Lots courts a ecouler',
            'message' => $count.' lot(s) sur '.$productCount.' produit(s) expirent sous 7 jours pour '.number_format($quantity, 3, ',', ' ').' unite(s) encore a vendre.'.($nearestExpiryAt ? ' Prochaine date : '.$this->formatDate($nearestExpiryAt).'.' : '').($highlights !== '' ? ' Exemples : '.$highlights.'.' : ''),
            'action_url' => route('stock.lots', ['status' => 'expiring', 'availability' => 'available', 'expiry_window_days' => 7]),
            'meta' => [
                'count' => $count,
                'product_count' => $productCount,
                'quantity' => $quantity,
                'nearest_expiry_date' => $nearestExpiryAt,
                'highlights' => $highlights,
            ],
        ]);
    }

    private function syncFoodStoreSaleableStockoutAlert(int $companyId, ?int $branchId = null, array $sectorProfile = []): void
    {
        $scopeSuffix = $branchId ? '-'.$branchId : '';

        if (($sectorProfile['key'] ?? null) !== 'food_store') {
            $this->resolveSystemAlert($companyId, 'food-store-saleable-stockouts'.$scopeSuffix);

            return;
        }

        $products = $this->foodStoreSaleableShelfProducts($companyId, $branchId)
            ->filter(fn (Product $product) => (float) ($product->saleable_stock ?? 0) <= 0.0001)
            ->values();
        $count = (int) $products->count();

        if ($count === 0) {
            $this->resolveSystemAlert($companyId, 'food-store-saleable-stockouts'.$scopeSuffix);

            return;
        }

        $highlights = $products
            ->take(3)
            ->map(fn (Product $product) => $product->display_name ?? $product->name ?? $product->sku ?? 'Produit')
            ->implode(', ');

        $this->upsertSystemAlert($companyId, 'food-store-saleable-stockouts'.$scopeSuffix, [
            'branch_id' => $branchId,
            'level' => 'danger',
            'title' => 'Ruptures rayon vendables',
            'message' => $count.' reference(s) ont deja tourne en stock mais n ont plus rien de vendable au comptoir.'.($highlights !== '' ? ' Exemples : '.$highlights.'.' : ''),
            'action_url' => route('stock.index', ['saleability_state' => 'zero']),
            'meta' => [
                'count' => $count,
                'highlights' => $highlights,
            ],
        ]);
    }

    private function syncWholesaleOrderCoverageRiskAlert(int $companyId, ?int $branchId = null, array $sectorProfile = []): void
    {
        $scopeSuffix = $branchId ? '-'.$branchId : '';

        if (($sectorProfile['key'] ?? null) !== 'wholesale_distribution') {
            $this->resolveSystemAlert($companyId, 'wholesale-order-coverage-risk'.$scopeSuffix);

            return;
        }

        $summary = $this->orderCoverageService->wholesalePortfolioSummary($companyId, $branchId);
        $count = (int) ($summary['orders_at_risk_count'] ?? 0);

        if ($count === 0) {
            $this->resolveSystemAlert($companyId, 'wholesale-order-coverage-risk'.$scopeSuffix);

            return;
        }

        $lines = (int) ($summary['order_lines_at_risk_count'] ?? 0);
        $shortageQty = (float) ($summary['at_risk_shortage_qty'] ?? 0);
        $highlights = collect($summary['highlights'] ?? [])->implode(', ');

        $this->upsertSystemAlert($companyId, 'wholesale-order-coverage-risk'.$scopeSuffix, [
            'branch_id' => $branchId,
            'level' => 'danger',
            'title' => 'Commandes grossiste a risque',
            'message' => $count.' commande(s) couvrent mal '.$lines.' ligne(s) pour '.number_format($shortageQty, 3, ',', ' ').' unite(s) encore non couvertes.'.($highlights !== '' ? ' Exemples : '.$highlights.'.' : ''),
            'action_url' => route('orders.index', ['coverage_state' => 'at_risk']),
            'meta' => [
                'count' => $count,
                'lines' => $lines,
                'shortage_qty' => $shortageQty,
                'highlights' => $summary['highlights'] ?? [],
            ],
        ]);
    }

    private function syncWholesaleOverdueCommitmentsAlert(int $companyId, ?int $branchId = null, array $sectorProfile = []): void
    {
        $scopeSuffix = $branchId ? '-'.$branchId : '';

        if (($sectorProfile['key'] ?? null) !== 'wholesale_distribution') {
            $this->resolveSystemAlert($companyId, 'wholesale-overdue-commitments'.$scopeSuffix);

            return;
        }

        $summary = $this->orderCoverageService->wholesalePortfolioSummary($companyId, $branchId);
        $count = (int) ($summary['overdue_backlog_orders_count'] ?? 0);

        if ($count === 0) {
            $this->resolveSystemAlert($companyId, 'wholesale-overdue-commitments'.$scopeSuffix);

            return;
        }

        $remainingQty = (float) ($summary['overdue_backlog_remaining_qty'] ?? 0);
        $highlights = collect($summary['overdue_highlights'] ?? [])->implode(', ');
        $oldestTargetDate = $summary['oldest_overdue_target_date'] ?? null;
        $message = $count.' commande(s) gardent encore '.number_format($remainingQty, 3, ',', ' ').' unite(s) en reliquat apres la date promise.';

        if ($oldestTargetDate instanceof Carbon) {
            $message .= ' Plus ancien engagement : '.$oldestTargetDate->format('d/m/Y').'.';
        }

        if ($highlights !== '') {
            $message .= ' Exemples : '.$highlights.'.';
        }

        $this->upsertSystemAlert($companyId, 'wholesale-overdue-commitments'.$scopeSuffix, [
            'branch_id' => $branchId,
            'level' => 'warning',
            'title' => 'Engagements grossiste en retard',
            'message' => $message,
            'action_url' => route('orders.index', ['delivery_focus' => 'overdue']),
            'meta' => [
                'count' => $count,
                'remaining_qty' => $remainingQty,
                'oldest_target_date' => $oldestTargetDate?->toDateString(),
                'highlights' => $summary['overdue_highlights'] ?? [],
            ],
        ]);
    }

    private function syncBranchSalesDropAlerts(int $companyId): void
    {
        $currentStart = now()->copy()->subDays(6)->toDateString();
        $currentEnd = now()->toDateString();
        $previousStart = now()->copy()->subDays(13)->toDateString();
        $previousEnd = now()->copy()->subDays(7)->toDateString();

        $activeBranchIds = Branch::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        InternalNotification::query()
            ->where('company_id', $companyId)
            ->where('code', 'like', 'branch-sales-drop-%')
            ->get()
            ->each(function (InternalNotification $notification) use ($companyId, $activeBranchIds): void {
                $branchId = (int) str_replace('branch-sales-drop-', '', $notification->code);

                if (! in_array($branchId, $activeBranchIds, true)) {
                    $this->resolveSystemAlert($companyId, $notification->code);
                }
            });

        $branches = Branch::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        foreach ($branches as $branch) {
            $previousQuery = SalesInvoice::query()
                ->where('company_id', $companyId)
                ->where('branch_id', $branch->id)
                ->where('status', 'validated')
                ->whereDate('invoice_date', '>=', $previousStart)
                ->whereDate('invoice_date', '<=', $previousEnd);

            $previousTotal = (float) (clone $previousQuery)->sum('total');
            $previousCount = (int) (clone $previousQuery)->count();
            $currentTotal = (float) SalesInvoice::query()
                ->where('company_id', $companyId)
                ->where('branch_id', $branch->id)
                ->where('status', 'validated')
                ->whereDate('invoice_date', '>=', $currentStart)
                ->whereDate('invoice_date', '<=', $currentEnd)
                ->sum('total');

            $code = 'branch-sales-drop-'.$branch->id;

            if ($previousCount < 2 || $previousTotal < 100000 || $currentTotal > ($previousTotal * 0.7)) {
                $this->resolveSystemAlert($companyId, $code);

                continue;
            }

            $declinePercent = (int) round((($previousTotal - $currentTotal) / $previousTotal) * 100);

            $this->upsertSystemAlert($companyId, $code, [
                'branch_id' => $branch->id,
                'level' => $declinePercent >= 50 ? 'danger' : 'warning',
                'title' => 'Baisse de ventes agence',
                'message' => $branch->name.' vend '.$this->money($currentTotal).' sur les 7 derniers jours contre '.$this->money($previousTotal).' sur la periode precedente (baisse de '.$declinePercent.'%).',
                'action_url' => route('sales.index', [
                    'branch_id' => $branch->id,
                    'date_from' => $currentStart,
                    'date_to' => $currentEnd,
                ]),
                'meta' => [
                    'current_total' => $currentTotal,
                    'previous_total' => $previousTotal,
                    'decline_percent' => $declinePercent,
                ],
            ]);
        }
    }

    private function syncTechnicalMonitoringAlerts(int $companyId): void
    {
        $monitoring = $this->applicationMonitoringService->summary();
        $logs = $monitoring['logs'];
        $failedJobs = $monitoring['failed_jobs'];

        if (($logs['status'] ?? 'ok') === 'ok') {
            $this->resolveSystemAlert($companyId, 'ops-log-health');
        } else {
            $this->upsertSystemAlert($companyId, 'ops-log-health', [
                'branch_id' => null,
                'level' => ($logs['status'] ?? 'warning') === 'fail' ? 'danger' : 'warning',
                'title' => 'Surveillance logs applicatifs',
                'message' => $logs['message'] ?? 'Des signaux techniques sont remontes dans les logs applicatifs.',
                'action_url' => route('ops.index'),
                'meta' => $logs,
            ]);
        }

        if (($failedJobs['status'] ?? 'ok') === 'ok') {
            $this->resolveSystemAlert($companyId, 'ops-failed-jobs');

            return;
        }

        $this->upsertSystemAlert($companyId, 'ops-failed-jobs', [
            'branch_id' => null,
            'level' => ($failedJobs['status'] ?? 'warning') === 'fail' ? 'danger' : 'warning',
            'title' => 'Jobs techniques en echec',
            'message' => $failedJobs['message'] ?? 'Des jobs techniques sont en echec.',
            'action_url' => route('ops.index'),
            'meta' => $failedJobs,
        ]);
    }

    private function activeQuery(int $companyId, ?int $branchId = null): Builder
    {
        return $this->scopedQuery($companyId, $branchId)
            ->whereNull('resolved_at');
    }

    private function scopedQuery(int $companyId, ?int $branchId = null): Builder
    {
        $query = InternalNotification::query()
            ->with(['reader', 'branch'])
            ->where('company_id', $companyId);

        return $this->applyBranchScope($query, $branchId);
    }

    private function applyBranchScope(Builder $query, ?int $branchId = null): Builder
    {
        if (! $branchId) {
            return $query;
        }

        return $query->where(function (Builder $scopedQuery) use ($branchId): void {
            $scopedQuery->whereNull('branch_id')
                ->orWhere('branch_id', $branchId);
        });
    }

    private function upsertSystemAlert(int $companyId, string $code, array $payload): InternalNotification
    {
        $notification = InternalNotification::query()->firstOrNew([
            'company_id' => $companyId,
            'code' => $code,
        ]);
        $originalBranchId = $notification->branch_id;

        $wasResolved = $notification->exists && $notification->resolved_at !== null;

        $notification->fill([
            'branch_id' => $payload['branch_id'] ?? null,
            'type' => 'system',
            'level' => $payload['level'] ?? 'info',
            'title' => $payload['title'],
            'message' => $payload['message'],
            'action_url' => $payload['action_url'] ?? null,
            'meta' => $payload['meta'] ?? null,
            'resolved_at' => null,
        ]);

        if (! $notification->exists || $wasResolved) {
            $notification->is_read = false;
            $notification->read_at = null;
            $notification->read_by = null;
        }

        $notification->save();
        $this->forgetSummaryCache($companyId, $originalBranchId);
        $this->forgetSummaryCache($companyId, $notification->branch_id);

        return $notification;
    }

    private function resolveSystemAlert(int $companyId, string $code): void
    {
        $notification = InternalNotification::query()
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->whereNull('resolved_at')
            ->first();

        if (! $notification) {
            return;
        }

        $notification->forceFill([
            'resolved_at' => now(),
        ])->save();

        $this->forgetSummaryCache($companyId, $notification->branch_id);
    }

    private function summaryCacheKey(int $companyId, ?int $branchId = null, int $limit = 5): string
    {
        return sprintf('notifications:summary:%d:%s:%d', $companyId, $branchId ?: 'global', $limit);
    }

    private function forgetSummaryCache(int $companyId, ?int $branchId = null, array $limits = [5]): void
    {
        foreach ($limits as $limit) {
            Cache::forget($this->summaryCacheKey($companyId, null, $limit));

            if ($branchId) {
                Cache::forget($this->summaryCacheKey($companyId, $branchId, $limit));
            }
        }
    }

    private function stockAlerts(int $companyId, ?int $branchId): int
    {
        $balances = StockMovement::query()
            ->select('product_id')
            ->selectRaw('SUM(quantity_in - quantity_out) as current_stock')
            ->where('company_id', $companyId)
            ->when($branchId, fn (Builder $query, int $selectedBranchId) => $query->where('branch_id', $selectedBranchId))
            ->groupBy('product_id');

        return Product::query()
            ->where('company_id', $companyId)
            ->where('type', 'stockable')
            ->leftJoinSub($balances, 'balances', fn ($join) => $join->on('products.id', '=', 'balances.product_id'))
            ->whereRaw('COALESCE(balances.current_stock, 0) <= products.min_stock')
            ->count();
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

    private function trackedProductsWithoutSaleableStock(int $companyId, ?int $branchId = null)
    {
        $today = now()->toDateString();
        $saleableLotBalances = ProductLot::query()
            ->select('product_id')
            ->selectRaw('SUM(quantity_available) as saleable_qty')
            ->where('company_id', $companyId)
            ->when($branchId, fn (Builder $query, int $selectedBranchId) => $query->where('branch_id', $selectedBranchId))
            ->where('quantity_available', '>', 0.0001)
            ->where(function (Builder $query) use ($today) {
                $query->whereNull('expires_at')
                    ->orWhereDate('expires_at', '>=', $today);
            })
            ->groupBy('product_id');

        return Product::query()
            ->where('products.company_id', $companyId)
            ->where('products.type', 'stockable')
            ->where('products.is_active', true)
            ->where('products.sale_ok', true)
            ->whereIn('products.tracking_type', ['lot', 'serial'])
            ->whereHas('lots', fn (Builder $query) => $query
                ->where('company_id', $companyId)
                ->when($branchId, fn (Builder $branchQuery, int $selectedBranchId) => $branchQuery->where('branch_id', $selectedBranchId)))
            ->leftJoinSub($saleableLotBalances, 'saleable_balances', fn ($join) => $join->on('products.id', '=', 'saleable_balances.product_id'))
            ->select(['products.id', 'products.name', 'products.sku'])
            ->whereRaw('COALESCE(saleable_balances.saleable_qty, 0) <= 0.0001')
            ->orderBy('products.name')
            ->get();
    }

    private function foodStoreShortDatedLots(int $companyId, ?int $branchId = null)
    {
        $today = now()->toDateString();
        $horizon = now()->addDays(7)->toDateString();

        return ProductLot::query()
            ->with('product')
            ->where('company_id', $companyId)
            ->when($branchId, fn (Builder $query, int $selectedBranchId) => $query->where('branch_id', $selectedBranchId))
            ->where('quantity_available', '>', 0.0001)
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '>', $today)
            ->whereDate('expires_at', '<=', $horizon)
            ->whereHas('product', fn (Builder $query) => $query
                ->where('type', 'stockable')
                ->where('is_active', true)
                ->where('sale_ok', true))
            ->orderBy('expires_at')
            ->orderBy('product_id')
            ->get();
    }

    private function foodStoreSaleableShelfProducts(int $companyId, ?int $branchId = null)
    {
        $today = now()->toDateString();
        $balances = StockMovement::query()
            ->select('product_id')
            ->selectRaw('SUM(quantity_in - quantity_out) as current_stock')
            ->where('company_id', $companyId)
            ->when($branchId, fn (Builder $query, int $selectedBranchId) => $query->where('branch_id', $selectedBranchId))
            ->groupBy('product_id');
        $saleableLotBalances = ProductLot::query()
            ->select('product_id')
            ->selectRaw('SUM(quantity_available) as saleable_qty')
            ->where('company_id', $companyId)
            ->when($branchId, fn (Builder $query, int $selectedBranchId) => $query->where('branch_id', $selectedBranchId))
            ->where('quantity_available', '>', 0.0001)
            ->where(function (Builder $query) use ($today) {
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
            ->where(function (Builder $query) use ($companyId, $branchId) {
                $query->whereHas('stockMovements', fn (Builder $movementQuery) => $movementQuery
                    ->where('company_id', $companyId)
                    ->when($branchId, fn (Builder $scopedQuery, int $selectedBranchId) => $scopedQuery->where('branch_id', $selectedBranchId)))
                    ->orWhereHas('lots', fn (Builder $lotQuery) => $lotQuery
                        ->where('company_id', $companyId)
                        ->when($branchId, fn (Builder $scopedQuery, int $selectedBranchId) => $scopedQuery->where('branch_id', $selectedBranchId)));
            })
            ->leftJoinSub($balances, 'balances', fn ($join) => $join->on('products.id', '=', 'balances.product_id'))
            ->leftJoinSub($saleableLotBalances, 'saleable_balances', fn ($join) => $join->on('products.id', '=', 'saleable_balances.product_id'))
            ->select(['products.id', 'products.name', 'products.sku', 'products.min_stock', 'products.tracking_type'])
            ->selectRaw($saleableStockExpression.' as saleable_stock')
            ->orderBy('products.name')
            ->get();
    }

    private function moduleLabel(string $module): string
    {
        return match ($module) {
            'sales' => 'ventes',
            'purchases' => 'achats',
            'expenses' => 'depenses',
            default => $module,
        };
    }

    private function formatDate(string|Carbon|null $value): string
    {
        if (! $value) {
            return 'n/a';
        }

        return Carbon::parse($value)->format('d/m/Y');
    }

    private function money(float $value): string
    {
        return number_format($value, 0, ',', ' ').' XOF';
    }
}
