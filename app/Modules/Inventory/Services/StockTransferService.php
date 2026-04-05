<?php

namespace App\Modules\Inventory\Services;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Company\Services\DocumentNumberService;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockTransferService
{
    public function __construct(
        private readonly DocumentNumberService $documentNumberService,
        private readonly StockService $stockService,
    ) {
    }

    public function normalizeItems(int $companyId, array $items): Collection
    {
        $filteredItems = collect($items)
            ->filter(fn (array $item) => filled($item['product_id'] ?? null))
            ->values();

        $productIds = $filteredItems->pluck('product_id')->map(fn ($id) => (int) $id)->all();
        $products = Product::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        return $filteredItems->map(function (array $item) use ($products) {
            /** @var Product $product */
            $product = $products->get((int) $item['product_id']);
            $qty = (float) $item['qty'];
            $unitCost = (float) ($item['unit_cost'] ?? $product->purchase_price);

            return [
                'product' => $product,
                'product_id' => $product->id,
                'description' => $item['description'] ?: $product->name,
                'qty' => $qty,
                'unit_cost' => $unitCost,
                'line_total' => round($qty * $unitCost, 2),
            ];
        });
    }

    public function create(int $companyId, int $branchId, Warehouse $sourceWarehouse, Warehouse $destinationWarehouse, array $payload, Collection $items, User $user): StockTransfer
    {
        if ($sourceWarehouse->id === $destinationWarehouse->id) {
            throw ValidationException::withMessages([
                'destination_warehouse_id' => 'Le depot source et le depot destination doivent etre differents.',
            ]);
        }

        return DB::transaction(function () use ($companyId, $branchId, $sourceWarehouse, $destinationWarehouse, $payload, $items, $user) {
            $transfer = StockTransfer::query()->create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'source_warehouse_id' => $sourceWarehouse->id,
                'destination_warehouse_id' => $destinationWarehouse->id,
                'transfer_number' => $this->documentNumberService->nextNumber(
                    companyId: $companyId,
                    documentType: 'stock_transfer',
                    branchId: $branchId,
                    date: $payload['transfer_date'],
                ),
                'transfer_date' => $payload['transfer_date'],
                'status' => 'completed',
                'notes' => $payload['notes'] ?? null,
                'completed_at' => now(),
                'created_by' => $user->id,
            ]);

            foreach ($items as $item) {
                $product = $item['product'];

                $transfer->items()->create([
                    'product_id' => $item['product_id'],
                    'description' => $item['description'],
                    'qty' => $item['qty'],
                    'unit_cost' => $item['unit_cost'],
                    'line_total' => $item['line_total'],
                ]);

                if ($product->type === 'stockable') {
                    $this->stockService->recordTransferOut(
                        product: $product,
                        companyId: $companyId,
                        branchId: $branchId,
                        warehouseId: $sourceWarehouse->id,
                        quantity: (float) $item['qty'],
                        unitCost: (float) $item['unit_cost'],
                        referenceType: StockTransfer::class,
                        referenceId: $transfer->id,
                        movementDate: $payload['transfer_date'],
                        user: $user,
                        notes: 'Vers '.$destinationWarehouse->name,
                    );

                    $this->stockService->recordTransferIn(
                        product: $product,
                        companyId: $companyId,
                        branchId: $branchId,
                        warehouseId: $destinationWarehouse->id,
                        quantity: (float) $item['qty'],
                        unitCost: (float) $item['unit_cost'],
                        referenceType: StockTransfer::class,
                        referenceId: $transfer->id,
                        movementDate: $payload['transfer_date'],
                        user: $user,
                        notes: 'Depuis '.$sourceWarehouse->name,
                    );
                }
            }

            return $transfer->load(['sourceWarehouse', 'destinationWarehouse', 'items.product', 'creator']);
        });
    }
}
