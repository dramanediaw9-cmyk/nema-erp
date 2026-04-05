<?php

namespace App\Modules\Core\Audit\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Audit\Models\ActivityLog;
use App\Modules\Core\Branch\Models\Branch;
use App\Support\CurrentWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Request $request, CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        $filters = $this->filters($request);

        $logs = ActivityLog::query()
            ->with(['user', 'branch'])
            ->when($companyId, fn (Builder $query, int $value) => $query->where('company_id', $value))
            ->when($filters['action'], fn (Builder $query, string $action) => $query->where('action', $action))
            ->when($filters['branch_id'], fn (Builder $query, int $branchId) => $query->where('branch_id', $branchId))
            ->when($filters['search'], function (Builder $query, string $search): void {
                $like = '%'.$search.'%';

                $query->where(function (Builder $nested) use ($like): void {
                    $nested->where('description', 'like', $like)
                        ->orWhere('action', 'like', $like)
                        ->orWhere('ip_address', 'like', $like)
                        ->orWhere('user_agent', 'like', $like)
                        ->orWhereHas('user', function (Builder $userQuery) use ($like): void {
                            $userQuery->where('name', 'like', $like)
                                ->orWhere('email', 'like', $like);
                        })
                        ->orWhereHas('branch', fn (Builder $branchQuery) => $branchQuery->where('name', 'like', $like));
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('activity-logs.index', [
            'logs' => $logs,
            'filters' => $filters,
            'actions' => ActivityLog::query()
                ->when($companyId, fn (Builder $query, int $value) => $query->where('company_id', $value))
                ->distinct()
                ->orderBy('action')
                ->pluck('action'),
            'branches' => Branch::query()
                ->when($companyId, fn (Builder $query, int $value) => $query->where('company_id', $value))
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    private function filters(Request $request): array
    {
        return [
            'search' => $request->string('search')->trim()->value() ?: null,
            'action' => $request->string('action')->trim()->value() ?: null,
            'branch_id' => $request->integer('branch_id') ?: null,
        ];
    }
}
