<?php

namespace App\Modules\Core\Audit\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Audit\Models\ActivityLog;
use App\Modules\Core\Branch\Models\Branch;
use App\Support\CurrentWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Request $request, CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        $filters = $this->filters($request);
        $categories = $this->categories();

        $query = ActivityLog::query()
            ->with(['user', 'branch', 'subject'])
            ->when($companyId, fn (Builder $query, int $value) => $query->where('company_id', $value))
            ->when($filters['action'], fn (Builder $query, string $action) => $query->where('action', $action))
            ->when($filters['branch_id'], fn (Builder $query, int $branchId) => $query->where('branch_id', $branchId))
            ->when($filters['category'], fn (Builder $query, string $category) => $this->applyCategoryFilter($query, $category, $categories))
            ->when($filters['date_from'], fn (Builder $query, string $date) => $query->where('created_at', '>=', Carbon::parse($date)->startOfDay()))
            ->when($filters['date_to'], fn (Builder $query, string $date) => $query->where('created_at', '<=', Carbon::parse($date)->endOfDay()))
            ->when($filters['search'], function (Builder $query, string $search): void {
                $like = '%'.$search.'%';

                $query->where(function (Builder $nested) use ($like): void {
                    $nested->where('description', 'like', $like)
                        ->orWhere('action', 'like', $like)
                        ->orWhere('properties', 'like', $like)
                        ->orWhere('ip_address', 'like', $like)
                        ->orWhere('user_agent', 'like', $like)
                        ->orWhereHas('user', function (Builder $userQuery) use ($like): void {
                            $userQuery->where('name', 'like', $like)
                                ->orWhere('email', 'like', $like);
                        })
                        ->orWhereHas('branch', fn (Builder $branchQuery) => $branchQuery->where('name', 'like', $like));
                });
            });

        $summaryQuery = clone $query;

        $logs = $query
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('activity-logs.index', [
            'logs' => $logs,
            'filters' => $filters,
            'categories' => $categories,
            'auditSummary' => [
                'total' => (clone $summaryQuery)->count(),
                'sensitive' => tap(clone $summaryQuery, fn (Builder $query) => $this->applySensitiveFilter($query))->count(),
                'users' => (clone $summaryQuery)->whereNotNull('user_id')->distinct()->count('user_id'),
                'ips' => (clone $summaryQuery)->whereNotNull('ip_address')->distinct()->count('ip_address'),
                'latest' => (clone $summaryQuery)->max('created_at'),
            ],
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
            'category' => $request->string('category')->trim()->value() ?: null,
            'date_from' => $request->date('date_from')?->format('Y-m-d'),
            'date_to' => $request->date('date_to')?->format('Y-m-d'),
        ];
    }

    private function categories(): array
    {
        return [
            'pos' => ['label' => 'Caisse / POS', 'prefixes' => ['pos.']],
            'stock' => ['label' => 'Stock et inventaire', 'prefixes' => ['stock.', 'stock_counts.', 'transfers.']],
            'products' => ['label' => 'Produits et prix', 'prefixes' => ['products.', 'catalog.']],
            'purchases' => ['label' => 'Achats et fournisseurs', 'prefixes' => ['purchases.', 'suppliers.', 'replenishments.']],
            'sales' => ['label' => 'Ventes et factures', 'prefixes' => ['sales.', 'invoices.', 'payments.']],
            'users' => ['label' => 'Utilisateurs et droits', 'prefixes' => ['users.', 'roles.', 'permissions.']],
            'settings' => ['label' => 'Parametres societe', 'prefixes' => ['settings.', 'companies.', 'branches.', 'warehouses.', 'cash_registers.', 'taxes.']],
            'imports' => ['label' => 'Imports et donnees', 'prefixes' => ['imports.', 'exports.']],
            'sensitive' => ['label' => 'Actions sensibles', 'prefixes' => []],
        ];
    }

    private function applyCategoryFilter(Builder $query, string $category, array $categories): void
    {
        if ($category === 'sensitive') {
            $this->applySensitiveFilter($query);

            return;
        }

        $prefixes = $categories[$category]['prefixes'] ?? [];

        if ($prefixes === []) {
            return;
        }

        $query->where(function (Builder $nested) use ($prefixes): void {
            foreach ($prefixes as $prefix) {
                $nested->orWhere('action', 'like', $prefix.'%');
            }
        });
    }

    private function applySensitiveFilter(Builder $query): void
    {
        $sensitivePrefixes = [
            'users.',
            'roles.',
            'permissions.',
            'settings.',
            'companies.',
            'branches.',
            'warehouses.',
            'cash_registers.',
            'taxes.',
        ];

        $sensitiveActions = [
            'products.update',
            'products.delete',
            'products.archive',
            'products.restore',
            'pos.session.open',
            'pos.session.close',
            'pos.session.unlock',
            'pos.sale.return',
            'stock.adjustment',
            'stock.opening',
            'stock_counts.post',
            'transfers.create',
            'replenishments.generate',
            'replenishments.activate_products',
        ];

        $query->where(function (Builder $nested) use ($sensitivePrefixes, $sensitiveActions): void {
            $nested->whereIn('action', $sensitiveActions);

            foreach ($sensitivePrefixes as $prefix) {
                $nested->orWhere('action', 'like', $prefix.'%');
            }
        });
    }
}
