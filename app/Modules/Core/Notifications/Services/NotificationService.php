<?php

namespace App\Modules\Core\Notifications\Services;

use App\Models\User;
use App\Modules\Accounting\Services\PeriodChecklistService;
use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Approvals\Models\ApprovalStep;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Notifications\Models\InternalNotification;
use App\Modules\Core\Ops\Services\ApplicationMonitoringService;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Inventory\Models\ProductLot;
use App\Modules\Inventory\Models\StockCount;
use App\Modules\Inventory\Models\StockCountItem;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Purchases\Models\PurchaseBill;
use App\Modules\Sales\Models\SalesInvoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class NotificationService
{
    public function __construct(
        private readonly PeriodChecklistService $periodChecklistService,
        private readonly ApplicationMonitoringService $applicationMonitoringService,
    ) {}

    public function syncCompanyAlerts(int $companyId, ?int $branchId = null): void
    {
        $summary = $this->periodChecklistService->currentPeriodSummary($companyId);

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

        return $notification;
    }

    private function resolveSystemAlert(int $companyId, string $code): void
    {
        InternalNotification::query()
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->whereNull('resolved_at')
            ->update([
                'resolved_at' => now(),
            ]);
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
