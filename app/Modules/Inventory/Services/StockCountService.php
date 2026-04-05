<?php

namespace App\Modules\Inventory\Services;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Company\Services\DocumentNumberService;
use App\Modules\Inventory\Models\StockCount;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockCountService
{
    public function __construct(
        private readonly DocumentNumberService $documentNumberService,
        private readonly StockService $stockService,
    ) {
    }

    public function create(int $companyId, int $branchId, Warehouse $warehouse, array $payload, Collection $items, User $user): StockCount
    {
        return DB::transaction(function () use ($companyId, $branchId, $warehouse, $payload, $items, $user) {
            $count = StockCount::query()->create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'warehouse_id' => $warehouse->id,
                'count_number' => $this->documentNumberService->nextNumber(
                    companyId: $companyId,
                    documentType: 'stock_count',
                    branchId: $branchId,
                    date: $payload['count_date'],
                ),
                'count_date' => $payload['count_date'],
                'status' => 'draft',
                'notes' => $payload['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            foreach ($items as $item) {
                $count->items()->create([
                    'product_id' => $item['product_id'],
                    'expected_qty' => $item['expected_qty'],
                    'counted_qty' => $item['counted_qty'],
                    'variance_qty' => $item['variance_qty'],
                    'unit_cost' => $item['unit_cost'],
                ]);
            }

            return $count->load(['warehouse', 'branch', 'creator', 'items.product']);
        });
    }

    public function post(StockCount $stockCount, User $user): StockCount
    {
        return DB::transaction(function () use ($stockCount, $user) {
            $count = StockCount::query()
                ->with(['items.product'])
                ->whereKey($stockCount->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($count->status !== 'draft') {
                throw ValidationException::withMessages([
                    'count' => 'Cet inventaire ne peut plus etre valide.',
                ]);
            }

            foreach ($count->items as $item) {
                $variance = (float) $item->variance_qty;
                if (abs($variance) < 0.0001) {
                    continue;
                }

                $this->stockService->recordAdjustment(
                    product: $item->product,
                    companyId: $count->company_id,
                    branchId: $count->branch_id,
                    direction: $variance > 0 ? 'in' : 'out',
                    quantity: abs($variance),
                    unitCost: (float) $item->unit_cost,
                    reason: 'Inventaire '.$count->count_number,
                    notes: $count->notes,
                    user: $user,
                    movementDate: $count->count_date,
                    warehouseId: $count->warehouse_id,
                );
            }

            $count->update([
                'status' => 'posted',
                'posted_at' => now(),
                'posted_by' => $user->id,
            ]);

            return $count->fresh(['warehouse', 'branch', 'creator', 'poster', 'items.product']);
        });
    }

    public function prepareItems(int $companyId, int $branchId, int $warehouseId, array $rows): Collection
    {
        $filteredRows = collect($rows)
            ->filter(fn (array $row) => filled($row['product_id'] ?? null) && ($row['counted_qty'] ?? '') !== '')
            ->values();

        $productIds = $filteredRows->pluck('product_id')->map(fn ($id) => (int) $id)->all();
        $products = Product::query()->where('company_id', $companyId)->whereIn('id', $productIds)->get()->keyBy('id');

        return $filteredRows->map(function (array $row) use ($products, $companyId, $branchId, $warehouseId) {
            /** @var Product|null $product */
            $product = $products->get((int) $row['product_id']);

            if (! $product) {
                throw ValidationException::withMessages([
                    'items' => 'Un produit de l inventaire est introuvable.',
                ]);
            }

            $expectedQty = $this->stockService->availableQuantity($companyId, $branchId, $product->id, $warehouseId);
            $countedQty = (float) $row['counted_qty'];

            return [
                'product_id' => $product->id,
                'expected_qty' => round($expectedQty, 3),
                'counted_qty' => round($countedQty, 3),
                'variance_qty' => round($countedQty - $expectedQty, 3),
                'unit_cost' => (float) $product->purchase_price,
            ];
        });
    }
}
