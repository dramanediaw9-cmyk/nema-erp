<?php

namespace App\Modules\Inventory\Services;

use App\Models\User;
use App\Modules\Accounting\Services\PeriodLockService;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\ProductLot;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockService
{
    public function __construct(
        private readonly PeriodLockService $periodLockService,
    ) {}

    public function defaultWarehouseId(int $companyId, int $branchId): int
    {
        $warehouse = Warehouse::query()
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->first();

        if (! $warehouse) {
            $warehouse = Warehouse::query()->create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'name' => 'Depot principal',
                'code' => 'DEP-'.$branchId,
                'is_default' => true,
                'is_active' => true,
            ]);
        }

        return $warehouse->id;
    }

    public function availableQuantity(int $companyId, int $branchId, int $productId, ?int $warehouseId = null): float
    {
        $query = StockMovement::query()
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('product_id', $productId);

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        $balance = $query
            ->selectRaw('COALESCE(SUM(quantity_in - quantity_out), 0) as balance')
            ->value('balance');

        return (float) $balance;
    }

    public function saleableQuantity(Product $product, int $companyId, int $branchId, ?int $warehouseId = null): float
    {
        if (! $this->usesTrackedLots($product)) {
            return $this->availableQuantity($companyId, $branchId, $product->id, $warehouseId);
        }

        $balance = (clone $this->saleableLotsQuery($product, $companyId, $branchId, $warehouseId))
            ->reorder()
            ->selectRaw('COALESCE(SUM(quantity_available), 0) as balance')
            ->value('balance');

        return (float) $balance;
    }

    public function reservedQuantity(int $companyId, int $branchId, int $productId, ?int $warehouseId = null, ?int $excludeOrderId = null): float
    {
        $query = DB::table('sales_order_items')
            ->join('sales_orders', 'sales_order_items.sales_order_id', '=', 'sales_orders.id')
            ->where('sales_orders.company_id', $companyId)
            ->where('sales_orders.branch_id', $branchId)
            ->whereIn('sales_orders.status', ['confirmed', 'partial_delivered'])
            ->whereNull('sales_orders.converted_sales_invoice_id')
            ->where('sales_order_items.product_id', $productId)
            ->whereRaw('sales_order_items.qty > sales_order_items.delivered_qty');

        if ($warehouseId) {
            $query->where('sales_orders.warehouse_id', $warehouseId);
        }

        if ($excludeOrderId) {
            $query->where('sales_orders.id', '!=', $excludeOrderId);
        }

        $reserved = $query
            ->selectRaw('COALESCE(SUM(sales_order_items.qty - sales_order_items.delivered_qty), 0) as reserved_qty')
            ->value('reserved_qty');

        return (float) $reserved;
    }

    public function reservableQuantity(Product $product, int $companyId, int $branchId, ?int $warehouseId = null, ?int $excludeOrderId = null): float
    {
        $physical = $this->saleableQuantity($product, $companyId, $branchId, $warehouseId);
        $reserved = $this->reservedQuantity($companyId, $branchId, $product->id, $warehouseId, $excludeOrderId);

        return max(0, round($physical - $reserved, 3));
    }

    public function incomingPurchaseQuantity(int $companyId, int $branchId, int $productId, ?int $warehouseId = null): float
    {
        $query = DB::table('purchase_order_items')
            ->join('purchase_orders', 'purchase_order_items.purchase_order_id', '=', 'purchase_orders.id')
            ->where('purchase_orders.company_id', $companyId)
            ->where('purchase_orders.branch_id', $branchId)
            ->whereIn('purchase_orders.status', ['confirmed', 'partial_received'])
            ->where('purchase_order_items.product_id', $productId)
            ->whereRaw('purchase_order_items.qty > purchase_order_items.received_qty');

        if ($warehouseId) {
            $query->where('purchase_orders.warehouse_id', $warehouseId);
        }

        $incoming = $query
            ->selectRaw('COALESCE(SUM(purchase_order_items.qty - purchase_order_items.received_qty), 0) as incoming_qty')
            ->value('incoming_qty');

        return (float) $incoming;
    }

    public function nextIncomingPurchaseDate(int $companyId, int $branchId, int $productId, ?int $warehouseId = null): ?Carbon
    {
        $query = DB::table('purchase_order_items')
            ->join('purchase_orders', 'purchase_order_items.purchase_order_id', '=', 'purchase_orders.id')
            ->where('purchase_orders.company_id', $companyId)
            ->where('purchase_orders.branch_id', $branchId)
            ->whereIn('purchase_orders.status', ['confirmed', 'partial_received'])
            ->where('purchase_order_items.product_id', $productId)
            ->whereRaw('purchase_order_items.qty > purchase_order_items.received_qty');

        if ($warehouseId) {
            $query->where('purchase_orders.warehouse_id', $warehouseId);
        }

        $date = $query
            ->orderByRaw('CASE WHEN purchase_orders.expected_receipt_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('purchase_orders.expected_receipt_date')
            ->orderBy('purchase_orders.order_date')
            ->value('purchase_orders.expected_receipt_date');

        return $date ? Carbon::parse($date) : null;
    }

    public function forecastCoverage(Product $product, int $companyId, int $branchId, float $requiredQty, ?int $warehouseId = null, ?int $excludeOrderId = null): array
    {
        $availableNow = $this->reservableQuantity($product, $companyId, $branchId, $warehouseId, $excludeOrderId);
        $incomingQty = $this->incomingPurchaseQuantity($companyId, $branchId, $product->id, $warehouseId);
        $coverableQty = round($availableNow + $incomingQty, 3);

        return [
            'required_qty' => round($requiredQty, 3),
            'available_now' => round($availableNow, 3),
            'incoming_qty' => round($incomingQty, 3),
            'coverable_qty' => round($coverableQty, 3),
            'shortage_qty' => max(0, round($requiredQty - $coverableQty, 3)),
            'next_incoming_date' => $this->nextIncomingPurchaseDate($companyId, $branchId, $product->id, $warehouseId),
        ];
    }

    public function assertReservableQuantity(Product $product, int $companyId, int $branchId, float $quantity, ?int $warehouseId = null, string $context = 'commande client', ?int $excludeOrderId = null): void
    {
        if ($product->tracking_type === 'serial' && abs($quantity - round($quantity)) > 0.0001) {
            throw ValidationException::withMessages([
                'items' => 'Le produit '.$product->display_name.' est suivi par numero de serie et doit etre reserve en quantite entiere.',
            ]);
        }

        $available = $this->reservableQuantity($product, $companyId, $branchId, $warehouseId, $excludeOrderId);

        if ($quantity > $available) {
            throw ValidationException::withMessages([
                'items' => 'Le stock disponible, apres reservations, est insuffisant pour finaliser cette '.$context.'.',
            ]);
        }
    }

    public function assertSaleableQuantity(Product $product, int $companyId, int $branchId, float $quantity, ?int $warehouseId = null, string $context = 'operation'): void
    {
        if (! $this->usesTrackedLots($product)) {
            $this->ensureAvailableStock($companyId, $branchId, $product->id, $quantity, $warehouseId, $context);

            return;
        }

        if ($product->tracking_type === 'serial' && abs($quantity - round($quantity)) > 0.0001) {
            throw ValidationException::withMessages([
                'items' => 'Le produit '.$product->display_name.' est suivi par numero de serie et doit etre sorti en quantite entiere.',
            ]);
        }

        $available = $this->saleableQuantity($product, $companyId, $branchId, $warehouseId);

        if ($quantity > $available) {
            $expiredQuantity = $this->expiredTrackedQuantity($product, $companyId, $branchId, $warehouseId);
            $message = $expiredQuantity > 0
                ? 'Le stock disponible pour le produit '.$product->display_name.' est insuffisant en lots non expires pour finaliser cette '.$context.'.'
                : 'Le stock disponible est insuffisant pour finaliser cette '.$context.'.';

            throw ValidationException::withMessages([
                'items' => $message,
            ]);
        }
    }

    public function recordOpening(Product $product, int $companyId, int $branchId, float $quantity, float $unitCost, ?string $notes, ?User $user, CarbonInterface|string|null $movementDate = null, ?int $warehouseId = null): StockMovement
    {
        return $this->recordMovement(
            product: $product,
            companyId: $companyId,
            branchId: $branchId,
            warehouseId: $warehouseId,
            type: 'opening',
            quantityIn: $quantity,
            quantityOut: 0,
            unitCost: $unitCost,
            reason: 'Stock initial',
            notes: $notes,
            movementDate: $movementDate,
            user: $user,
        );
    }

    public function recordAdjustment(Product $product, int $companyId, int $branchId, string $direction, float $quantity, float $unitCost, string $reason, ?string $notes, ?User $user, CarbonInterface|string|null $movementDate = null, ?int $warehouseId = null, ?string $referenceType = null, ?int $referenceId = null): StockMovement
    {
        if ($direction === 'out') {
            $this->ensureAvailableStock($companyId, $branchId, $product->id, $quantity, $warehouseId);
        }

        return $this->recordMovement(
            product: $product,
            companyId: $companyId,
            branchId: $branchId,
            warehouseId: $warehouseId,
            type: $direction === 'in' ? 'adjustment_in' : 'adjustment_out',
            quantityIn: $direction === 'in' ? $quantity : 0,
            quantityOut: $direction === 'out' ? $quantity : 0,
            unitCost: $unitCost,
            reason: $reason,
            notes: $notes,
            referenceType: $referenceType,
            referenceId: $referenceId,
            movementDate: $movementDate,
            user: $user,
        );
    }

    public function recordSale(Product $product, int $companyId, int $branchId, float $quantity, float $unitCost, ?string $referenceType, ?int $referenceId, ?User $user, CarbonInterface|string|null $movementDate = null, ?int $warehouseId = null, bool $allowNegative = false): Collection
    {
        return $this->recordStockOut(
            product: $product,
            companyId: $companyId,
            branchId: $branchId,
            quantity: $quantity,
            unitCost: $unitCost,
            referenceType: $referenceType,
            referenceId: $referenceId,
            user: $user,
            movementDate: $movementDate,
            warehouseId: $warehouseId,
            reason: 'Facture de vente',
            context: 'vente',
            allowNegative: $allowNegative,
        );
    }

    public function recordDelivery(Product $product, int $companyId, int $branchId, float $quantity, float $unitCost, ?string $referenceType, ?int $referenceId, ?User $user, CarbonInterface|string|null $movementDate = null, ?int $warehouseId = null): Collection
    {
        return $this->recordStockOut(
            product: $product,
            companyId: $companyId,
            branchId: $branchId,
            quantity: $quantity,
            unitCost: $unitCost,
            referenceType: $referenceType,
            referenceId: $referenceId,
            user: $user,
            movementDate: $movementDate,
            warehouseId: $warehouseId,
            reason: 'Bon de livraison',
            context: 'livraison',
        );
    }

    public function recordPurchase(Product $product, int $companyId, int $branchId, float $quantity, float $unitCost, ?string $referenceType, ?int $referenceId, ?User $user, CarbonInterface|string|null $movementDate = null, ?int $warehouseId = null, string $reason = 'Facture fournisseur', ?string $notes = null, ?int $productLotId = null): StockMovement
    {
        return $this->recordMovement(
            product: $product,
            companyId: $companyId,
            branchId: $branchId,
            warehouseId: $warehouseId,
            type: 'purchase',
            quantityIn: $quantity,
            quantityOut: 0,
            unitCost: $unitCost,
            reason: $reason,
            notes: $notes,
            referenceType: $referenceType,
            referenceId: $referenceId,
            movementDate: $movementDate,
            user: $user,
            productLotId: $productLotId,
        );
    }

    public function recordTransferOut(Product $product, int $companyId, int $branchId, int $warehouseId, float $quantity, float $unitCost, ?string $referenceType, ?int $referenceId, ?User $user, CarbonInterface|string|null $movementDate = null, ?string $notes = null): StockMovement
    {
        $this->ensureAvailableStock($companyId, $branchId, $product->id, $quantity, $warehouseId);

        return $this->recordMovement(
            product: $product,
            companyId: $companyId,
            branchId: $branchId,
            warehouseId: $warehouseId,
            type: 'adjustment_out',
            quantityIn: 0,
            quantityOut: $quantity,
            unitCost: $unitCost,
            reason: 'Transfert sortant',
            notes: $notes,
            referenceType: $referenceType,
            referenceId: $referenceId,
            movementDate: $movementDate,
            user: $user,
        );
    }

    public function recordTransferIn(Product $product, int $companyId, int $branchId, int $warehouseId, float $quantity, float $unitCost, ?string $referenceType, ?int $referenceId, ?User $user, CarbonInterface|string|null $movementDate = null, ?string $notes = null): StockMovement
    {
        return $this->recordMovement(
            product: $product,
            companyId: $companyId,
            branchId: $branchId,
            warehouseId: $warehouseId,
            type: 'adjustment_in',
            quantityIn: $quantity,
            quantityOut: 0,
            unitCost: $unitCost,
            reason: 'Transfert entrant',
            notes: $notes,
            referenceType: $referenceType,
            referenceId: $referenceId,
            movementDate: $movementDate,
            user: $user,
        );
    }

    private function recordStockOut(
        Product $product,
        int $companyId,
        int $branchId,
        float $quantity,
        float $unitCost,
        ?string $referenceType,
        ?int $referenceId,
        ?User $user,
        CarbonInterface|string|null $movementDate,
        ?int $warehouseId,
        string $reason,
        string $context,
        bool $allowNegative = false,
    ): Collection {
        if (! $this->usesTrackedLots($product)) {
            if (! $allowNegative) {
                $this->ensureAvailableStock($companyId, $branchId, $product->id, $quantity, $warehouseId, $context);
            }

            return collect([
                $this->recordMovement(
                    product: $product,
                    companyId: $companyId,
                    branchId: $branchId,
                    warehouseId: $warehouseId,
                    type: 'sale',
                    quantityIn: 0,
                    quantityOut: $quantity,
                    unitCost: $unitCost,
                    reason: $reason,
                    notes: null,
                    referenceType: $referenceType,
                    referenceId: $referenceId,
                    movementDate: $movementDate,
                    user: $user,
                ),
            ]);
        }

        $this->assertSaleableQuantity($product, $companyId, $branchId, $quantity, $warehouseId, $context);

        $remainingQuantity = round($quantity, 3);
        $movements = collect();

        $lots = $this->saleableLotsQuery($product, $companyId, $branchId, $warehouseId)
            ->lockForUpdate()
            ->get();

        foreach ($lots as $lot) {
            $availableQuantity = round((float) $lot->quantity_available, 3);
            if ($availableQuantity <= 0) {
                continue;
            }

            $pickedQuantity = round(min($availableQuantity, $remainingQuantity), 3);
            if ($pickedQuantity <= 0) {
                continue;
            }

            $movements->push($this->recordMovement(
                product: $product,
                companyId: $companyId,
                branchId: $branchId,
                warehouseId: $warehouseId,
                type: 'sale',
                quantityIn: 0,
                quantityOut: $pickedQuantity,
                unitCost: $unitCost,
                reason: $reason,
                notes: $this->trackedStockOutNote($lot),
                referenceType: $referenceType,
                referenceId: $referenceId,
                movementDate: $movementDate,
                user: $user,
                productLotId: $lot->id,
            ));

            $lot->forceFill([
                'quantity_available' => round($availableQuantity - $pickedQuantity, 3),
            ])->save();

            $remainingQuantity = round($remainingQuantity - $pickedQuantity, 3);
            if ($remainingQuantity <= 0.0001) {
                break;
            }
        }

        if ($remainingQuantity > 0.0001) {
            throw ValidationException::withMessages([
                'items' => 'Le stock disponible est insuffisant pour finaliser cette '.$context.'.',
            ]);
        }

        return $movements;
    }

    private function usesTrackedLots(Product $product): bool
    {
        return in_array($product->tracking_type, ['lot', 'serial'], true);
    }

    private function saleableLotsQuery(Product $product, int $companyId, int $branchId, ?int $warehouseId = null): Builder
    {
        return ProductLot::query()
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('product_id', $product->id)
            ->where('quantity_available', '>', 0)
            ->when($warehouseId, fn (Builder $query, int $resolvedWarehouseId) => $query->where('warehouse_id', $resolvedWarehouseId))
            ->where(function (Builder $query) {
                $query->whereNull('expires_at')
                    ->orWhereDate('expires_at', '>=', now()->toDateString());
            })
            ->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expires_at')
            ->orderBy('received_at')
            ->orderBy('id');
    }

    private function expiredTrackedQuantity(Product $product, int $companyId, int $branchId, ?int $warehouseId = null): float
    {
        $query = ProductLot::query()
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('product_id', $product->id)
            ->where('quantity_available', '>', 0)
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<', now()->toDateString());

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        return (float) $query->selectRaw('COALESCE(SUM(quantity_available), 0) as balance')->value('balance');
    }

    private function trackedStockOutNote(ProductLot $lot): string
    {
        $label = $lot->tracking_type === 'serial' ? 'Serie ' : 'Lot ';
        $note = $label.$lot->displayCode();

        if ($lot->expires_at) {
            $note .= ' · Exp '.$lot->expires_at->toDateString();
        }

        return $note;
    }

    private function ensureAvailableStock(int $companyId, int $branchId, int $productId, float $quantity, ?int $warehouseId = null, string $context = 'operation'): void
    {
        $available = $this->availableQuantity($companyId, $branchId, $productId, $warehouseId);

        if ($quantity > $available) {
            throw ValidationException::withMessages([
                'items' => 'Le stock disponible est insuffisant pour finaliser cette '.$context.'.',
            ]);
        }
    }

    private function recordMovement(
        Product $product,
        int $companyId,
        int $branchId,
        ?int $warehouseId,
        string $type,
        float $quantityIn,
        float $quantityOut,
        float $unitCost,
        ?string $reason,
        ?string $notes,
        ?string $referenceType = null,
        ?int $referenceId = null,
        CarbonInterface|string|null $movementDate = null,
        ?User $user = null,
        ?int $productLotId = null,
    ): StockMovement {
        $resolvedMovementDate = $movementDate instanceof CarbonInterface
            ? Carbon::instance($movementDate)
            : Carbon::parse($movementDate ?? now());

        $this->periodLockService->assertDateOpen($companyId, $resolvedMovementDate, 'movement_date');
        $resolvedWarehouseId = $warehouseId ?: $this->defaultWarehouseId($companyId, $branchId);

        return StockMovement::query()->create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'warehouse_id' => $resolvedWarehouseId,
            'product_id' => $product->id,
            'product_lot_id' => $productLotId,
            'movement_type' => $type,
            'quantity_in' => $quantityIn,
            'quantity_out' => $quantityOut,
            'unit_cost' => $unitCost,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'reason' => $reason,
            'notes' => $notes,
            'movement_date' => $resolvedMovementDate,
            'created_by' => $user?->id,
        ]);
    }
}
