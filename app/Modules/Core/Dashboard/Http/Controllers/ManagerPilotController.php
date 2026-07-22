<?php

namespace App\Modules\Core\Dashboard\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Audit\Models\ActivityLog;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Notifications\Models\InternalNotification;
use App\Modules\Core\Notifications\Services\NotificationService;
use App\Modules\Pos\Models\PosSession;
use App\Modules\Pos\Services\PosService;
use App\Modules\Sales\Models\SalesInvoice;
use App\Support\CurrentWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ManagerPilotController extends Controller
{
    public function __construct(
        private readonly PosService $posService,
        private readonly NotificationService $notificationService,
    ) {
    }

    public function __invoke(CurrentWorkspace $workspace, Request $request): View
    {
        return view('dashboard.manager-pilot', $this->payload($workspace, $request));
    }

    public function print(CurrentWorkspace $workspace, Request $request): View
    {
        return view('dashboard.manager-pilot-print', $this->payload($workspace, $request));
    }

    private function payload(CurrentWorkspace $workspace, Request $request): array
    {
        $companyId = $workspace->companyId();
        $user = $request->user();
        abort_if(! $companyId || ! $user, 403);

        $requestedBranchId = $request->integer('branch_id') ?: null;
        $branchScopeId = $user->resolvedBranchScope($requestedBranchId, $workspace->branchId());
        $branches = $this->branchesForUser($companyId, $branchScopeId, (bool) $user->canAccessAllBranches());

        $branchId = $branchScopeId ?: (int) ($branches->first()?->id ?? 0);
        abort_if(! $branchId, 403);

        $date = $request->date('date')?->format('Y-m-d') ?: now()->toDateString();
        $filters = ['date' => $date];
        $this->notificationService->syncCompanyAlertsIfStale($companyId, $branchId);

        $report = $this->posService->dailyReport($companyId, $branchId, $filters);
        $activeAlerts = InternalNotification::query()
            ->with('branch')
            ->where('company_id', $companyId)
            ->whereNull('resolved_at')
            ->where(function (Builder $query) use ($branchId): void {
                $query->whereNull('branch_id')
                    ->orWhere('branch_id', $branchId);
            })
            ->orderByRaw("CASE level WHEN 'danger' THEN 0 WHEN 'warning' THEN 1 ELSE 2 END")
            ->latest()
            ->limit(8)
            ->get();

        $openSessions = PosSession::query()
            ->with(['opener', 'cashAccount', 'warehouse'])
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('status', 'open')
            ->latest('opened_at')
            ->limit(8)
            ->get();

        $recentTickets = SalesInvoice::query()
            ->with(['customer', 'posSession.opener'])
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('sale_channel', 'pos')
            ->whereDate('invoice_date', $date)
            ->latest('id')
            ->limit(8)
            ->get();

        $sensitiveActions = ActivityLog::query()
            ->with('user')
            ->where('company_id', $companyId)
            ->where(function (Builder $query) use ($branchId): void {
                $query->whereNull('branch_id')
                    ->orWhere('branch_id', $branchId);
            })
            ->where('created_at', '>=', now()->subDay())
            ->where(function (Builder $query): void {
                $query->whereIn('action', [
                    'products.update',
                    'products.delete',
                    'products.archive',
                    'pos.session.unlock',
                    'pos.sale.return',
                    'stock.adjustment',
                    'stock_counts.post',
                    'transfers.create',
                ])
                    ->orWhere('action', 'like', 'users.%')
                    ->orWhere('action', 'like', 'roles.%')
                    ->orWhere('action', 'like', 'settings.%');
            })
            ->latest()
            ->limit(6)
            ->get();

        return [
            'company' => $workspace->company(),
            'branch' => $branches->firstWhere('id', $branchId),
            'branches' => $branches,
            'branchId' => $branchId,
            'date' => $date,
            'report' => $report,
            'activeAlerts' => $activeAlerts,
            'openSessions' => $openSessions,
            'recentTickets' => $recentTickets,
            'sensitiveActions' => $sensitiveActions,
            'actionPlan' => $this->actionPlan($report, $activeAlerts, $openSessions, $sensitiveActions),
        ];
    }

    private function branchesForUser(int $companyId, ?int $branchScopeId, bool $canAccessAllBranches)
    {
        return Branch::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->when(! $canAccessAllBranches && $branchScopeId, fn (Builder $query) => $query->whereKey($branchScopeId))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function actionPlan(array $report, $activeAlerts, $openSessions, $sensitiveActions): array
    {
        $stockAlerts = $report['stock_alerts'] ?? ['out_of_stock_count' => 0, 'low_stock_count' => 0];
        $settlementWatch = $report['settlement_watch'] ?? [];
        $hasCashVariance = abs((float) ($settlementWatch['cash_variance_total'] ?? 0)) > 0.009;
        $hasMobileRisk = abs((float) ($settlementWatch['mobile_unreconciled_amount'] ?? 0)) > 0.009
            || (int) ($settlementWatch['missing_reference_count'] ?? 0) > 0;

        return collect([
            [
                'level' => $activeAlerts->where('level', 'danger')->isNotEmpty() ? 'danger' : 'ok',
                'title' => 'Traiter les alertes critiques',
                'value' => $activeAlerts->where('level', 'danger')->count(),
                'message' => 'Priorite aux alertes rouges avant la cloture de journee.',
                'url' => route('notifications.index', ['scope' => 'active', 'level' => 'danger']),
            ],
            [
                'level' => ((int) ($stockAlerts['out_of_stock_count'] ?? 0)) > 0 ? 'danger' : (((int) ($stockAlerts['low_stock_count'] ?? 0)) > 0 ? 'warning' : 'ok'),
                'title' => 'Verifier les ruptures',
                'value' => (int) ($stockAlerts['out_of_stock_count'] ?? 0) + (int) ($stockAlerts['low_stock_count'] ?? 0),
                'message' => 'Les references critiques doivent partir en inventaire rapide ou reappro.',
                'url' => route('stock.index', ['stock_state' => 'low']),
            ],
            [
                'level' => ($hasCashVariance || $hasMobileRisk) ? 'warning' : 'ok',
                'title' => 'Controler les encaissements',
                'value' => $hasCashVariance || $hasMobileRisk ? 1 : 0,
                'message' => 'Ecart caisse ou mobile money non rapproche a verifier.',
                'url' => route('pos.report', ['date' => $report['date'] ?? now()->toDateString()]),
            ],
            [
                'level' => $openSessions->isNotEmpty() ? 'warning' : 'ok',
                'title' => 'Suivre les sessions ouvertes',
                'value' => $openSessions->count(),
                'message' => 'Une session ouverte trop longtemps doit etre cloturee ou justifiee.',
                'url' => route('pos.sessions.index'),
            ],
            [
                'level' => $sensitiveActions->isNotEmpty() ? 'warning' : 'ok',
                'title' => 'Relire les actions sensibles',
                'value' => $sensitiveActions->count(),
                'message' => 'Prix, stock, droits et caisse doivent rester tracables.',
                'url' => route('activity-logs.index', ['category' => 'sensitive']),
            ],
        ])->values()->all();
    }
}
