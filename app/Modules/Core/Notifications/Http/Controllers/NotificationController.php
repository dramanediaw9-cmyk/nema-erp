<?php

namespace App\Modules\Core\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Notifications\Models\InternalNotification;
use App\Modules\Core\Notifications\Services\NotificationService;
use App\Support\CurrentWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notificationService)
    {
    }

    public function index(Request $request, CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $user = $request->user();
        $branchScopeId = $user ? $user->resolvedBranchScope(null, $workspace->branchId()) : $workspace->branchId();

        $this->notificationService->syncCompanyAlerts($companyId, $branchScopeId);

        $scope = $request->string('scope')->value() ?: 'active';
        if (! in_array($scope, ['active', 'resolved', 'all'], true)) {
            $scope = 'active';
        }

        $level = $request->string('level')->trim()->value() ?: null;
        if (! in_array($level, ['danger', 'warning', 'info', 'success'], true)) {
            $level = null;
        }

        $readState = $request->string('read_state')->trim()->value() ?: 'all';
        if (! in_array($readState, ['all', 'unread', 'read'], true)) {
            $readState = 'all';
        }

        $search = $request->string('search')->trim()->value() ?: null;

        $notifications = $this->notificationService->indexQuery($companyId, $scope, $branchScopeId)
            ->when($level, fn (Builder $query, string $notificationLevel) => $query->where('level', $notificationLevel))
            ->when($readState === 'unread', fn (Builder $query) => $query->where('is_read', false))
            ->when($readState === 'read', fn (Builder $query) => $query->where('is_read', true))
            ->when($search, function (Builder $query, string $term) {
                $like = '%'.$term.'%';

                $query->where(function (Builder $nested) use ($like) {
                    $nested->where('title', 'like', $like)
                        ->orWhere('message', 'like', $like)
                        ->orWhere('code', 'like', $like)
                        ->orWhereHas('branch', fn (Builder $branchQuery) => $branchQuery->where('name', 'like', $like));
                });
            })
            ->paginate(20)
            ->withQueryString();

        return view('notifications.index', [
            'notifications' => $notifications,
            'filters' => [
                'scope' => $scope,
                'level' => $level,
                'read_state' => $readState,
                'search' => $search,
            ],
            'summary' => $this->summary($companyId, $branchScopeId),
        ]);
    }

    public function read(InternalNotification $notification, CurrentWorkspace $workspace, Request $request): RedirectResponse
    {
        abort_if($workspace->companyId() !== $notification->company_id, 403);

        $user = $request->user();
        $branchScopeId = $user ? $user->resolvedBranchScope(null, $workspace->branchId()) : $workspace->branchId();
        abort_if($branchScopeId && $notification->branch_id && $notification->branch_id !== $branchScopeId, 403);

        $this->notificationService->markAsRead($notification, $request->user());

        return back()->with('success', 'Alerte marquee comme lue.');
    }

    public function readAll(CurrentWorkspace $workspace, Request $request): RedirectResponse
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $user = $request->user();
        $branchScopeId = $user ? $user->resolvedBranchScope(null, $workspace->branchId()) : $workspace->branchId();

        $this->notificationService->markAllAsRead($companyId, $request->user(), $branchScopeId);

        return back()->with('success', 'Toutes les alertes actives ont ete marquees comme lues.');
    }

    private function summary(int $companyId, ?int $branchId = null): array
    {
        $query = InternalNotification::query()
            ->where('company_id', $companyId)
            ->when($branchId, function (Builder $builder, int $selectedBranchId) {
                $builder->where(function (Builder $scopeQuery) use ($selectedBranchId) {
                    $scopeQuery->whereNull('branch_id')
                        ->orWhere('branch_id', $selectedBranchId);
                });
            });

        return [
            'active' => (int) (clone $query)
                ->whereNull('resolved_at')
                ->count(),
            'unread' => (int) (clone $query)
                ->whereNull('resolved_at')
                ->where('is_read', false)
                ->count(),
            'critical' => (int) (clone $query)
                ->whereNull('resolved_at')
                ->where('level', 'danger')
                ->count(),
            'resolved' => (int) (clone $query)
                ->whereNotNull('resolved_at')
                ->count(),
        ];
    }
}



