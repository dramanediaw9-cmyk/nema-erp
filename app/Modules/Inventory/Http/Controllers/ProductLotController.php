<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\ProductLot;
use App\Modules\Inventory\Models\Warehouse;
use App\Support\CurrentWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductLotController extends Controller
{
    private const EXPIRY_HORIZON_DAYS = 30;

    public function index(Request $request, CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        $branchId = $workspace->branchId();
        abort_if(! $companyId || ! $branchId, 403);

        $filters = $this->filters($request);
        $warehouses = Warehouse::query()
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'is_default']);

        $query = ProductLot::query()
            ->with(['product.category', 'warehouse', 'goodsReceipt'])
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->when($filters['warehouse_id'], fn (Builder $builder, int $warehouseId) => $builder->where('warehouse_id', $warehouseId))
            ->when($filters['tracking_type'], fn (Builder $builder, string $trackingType) => $builder->where('tracking_type', $trackingType))
            ->when($filters['availability'] === 'available', fn (Builder $builder) => $builder->where('quantity_available', '>', 0))
            ->when($filters['availability'] === 'consumed', fn (Builder $builder) => $builder->where('quantity_available', '<=', 0))
            ->when($filters['status'], fn (Builder $builder, string $status) => $this->applyStatusFilter($builder, $status))
            ->when($filters['search'], fn (Builder $builder, string $search) => $this->applySearchFilter($builder, $search))
            ->orderByRaw('CASE WHEN quantity_available > 0 THEN 0 ELSE 1 END')
            ->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expires_at')
            ->latest('received_at')
            ->latest('id');

        $statsLots = (clone $query)->get(['id', 'product_id', 'expires_at', 'quantity_available']);
        $availableLots = $statsLots->filter(fn (ProductLot $lot) => (float) $lot->quantity_available > 0.0001);
        $lots = (clone $query)->paginate(20)->withQueryString();

        return view('stock.lots', [
            'lots' => $lots,
            'branch' => $workspace->branch(),
            'filters' => $filters,
            'warehouses' => $warehouses,
            'selectedWarehouse' => $filters['warehouse_id']
                ? $warehouses->firstWhere('id', $filters['warehouse_id'])
                : null,
            'expiryHorizonDays' => self::EXPIRY_HORIZON_DAYS,
            'stats' => [
                'count' => $statsLots->count(),
                'tracked_products' => $statsLots->pluck('product_id')->unique()->count(),
                'available_qty' => $availableLots->sum(fn (ProductLot $lot) => (float) $lot->quantity_available),
                'expired_count' => $availableLots->filter(fn (ProductLot $lot) => $lot->isExpired())->count(),
                'expiring_count' => $availableLots->filter(fn (ProductLot $lot) => $lot->isExpiringSoon(self::EXPIRY_HORIZON_DAYS))->count(),
            ],
        ]);
    }

    private function filters(Request $request): array
    {
        $trackingType = $request->string('tracking_type')->trim()->value() ?: null;
        if (! in_array($trackingType, ['lot', 'serial'], true)) {
            $trackingType = null;
        }

        $status = $request->string('status')->trim()->value() ?: null;
        if (! in_array($status, ['expired', 'expiring', 'healthy', 'no_expiry'], true)) {
            $status = null;
        }

        $availability = $request->string('availability')->trim()->value() ?: 'available';
        if (! in_array($availability, ['all', 'available', 'consumed'], true)) {
            $availability = 'available';
        }

        return [
            'search' => $request->string('search')->trim()->value() ?: null,
            'warehouse_id' => $request->integer('warehouse_id') ?: null,
            'tracking_type' => $trackingType,
            'status' => $status,
            'availability' => $availability,
        ];
    }

    private function applySearchFilter(Builder $query, string $search): void
    {
        $like = '%'.$search.'%';

        $query->where(function (Builder $builder) use ($like) {
            $builder->where('lot_number', 'like', $like)
                ->orWhere('serial_number', 'like', $like)
                ->orWhereHas('product', function (Builder $productQuery) use ($like) {
                    $productQuery->where('name', 'like', $like)
                        ->orWhere('sku', 'like', $like)
                        ->orWhere('barcode', 'like', $like);
                })
                ->orWhereHas('warehouse', function (Builder $warehouseQuery) use ($like) {
                    $warehouseQuery->where('name', 'like', $like)
                        ->orWhere('code', 'like', $like);
                })
                ->orWhereHas('goodsReceipt', fn (Builder $receiptQuery) => $receiptQuery->where('receipt_number', 'like', $like));
        });
    }

    private function applyStatusFilter(Builder $query, string $status): void
    {
        $today = now()->toDateString();
        $horizon = now()->addDays(self::EXPIRY_HORIZON_DAYS)->toDateString();

        match ($status) {
            'expired' => $query->whereNotNull('expires_at')->whereDate('expires_at', '<=', $today),
            'expiring' => $query->whereNotNull('expires_at')->whereDate('expires_at', '>', $today)->whereDate('expires_at', '<=', $horizon),
            'healthy' => $query->whereNotNull('expires_at')->whereDate('expires_at', '>', $horizon),
            'no_expiry' => $query->whereNull('expires_at'),
            default => null,
        };
    }
}
