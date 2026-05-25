<?php

namespace Tests\Concerns;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductCategory;
use App\Modules\Core\Company\Models\Setting;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Partners\Models\Partner;
use App\Modules\Purchases\Models\PurchaseOrder;
use App\Modules\Purchases\Models\PurchaseOrderItem;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Models\SalesOrderItem;

trait BuildsWholesaleCoverageScenario
{
    private function seedWholesaleCoverageScenario(User $user, string $prefix = 'WHO'): array
    {
        $prefix = strtoupper($prefix);

        Setting::query()->updateOrCreate(
            ['company_id' => $user->company_id, 'key' => 'sector_profile'],
            ['value' => ['profile' => 'wholesale_distribution']]
        );

        $warehouse = Warehouse::query()
            ->where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->where('is_default', true)
            ->firstOrFail();
        $customer = Partner::query()
            ->customers()
            ->where('company_id', $user->company_id)
            ->firstOrFail();
        $supplier = Partner::query()
            ->suppliers()
            ->where('company_id', $user->company_id)
            ->firstOrFail();
        $category = ProductCategory::query()->firstOrCreate([
            'company_id' => $user->company_id,
            'name' => 'Epicerie',
        ], [
            'tenant_id' => $user->tenant_id,
            'description' => 'Categorie test grossiste',
            'is_active' => true,
        ]);

        $atRiskProduct = $this->createWholesaleProduct(
            $user,
            $category,
            $prefix.'-RISK-001',
            'Riz import 50kg risque',
            'sac',
            21000,
            16800,
            2,
        );
        $this->seedWholesaleStock($user, $warehouse, $atRiskProduct, 2, 16800, 'Stock grossiste risque');
        $atRiskOrder = $this->createWholesaleOrder(
            $user,
            $customer,
            $warehouse,
            $atRiskProduct,
            'ORDER-'.$prefix.'-RISK-001',
            5,
            0,
            21000,
            'confirmed',
            now()->subDays(2)->toDateString(),
            now()->addDays(2)->toDateString(),
            now()->addDay()->toDateString(),
            'Commande grossiste a risque'
        );

        $incomingProduct = $this->createWholesaleProduct(
            $user,
            $category,
            $prefix.'-INCOMING-001',
            'Huile 20L appro',
            'bidon',
            29500,
            24000,
            1,
        );
        $this->seedWholesaleStock($user, $warehouse, $incomingProduct, 1, 24000, 'Stock grossiste appro');
        $incomingOrder = $this->createWholesaleOrder(
            $user,
            $customer,
            $warehouse,
            $incomingProduct,
            'ORDER-'.$prefix.'-INCOMING-001',
            3,
            0,
            29500,
            'confirmed',
            now()->subDays(2)->toDateString(),
            now()->addDays(3)->toDateString(),
            now()->addDays(2)->toDateString(),
            'Commande grossiste couverte par appro'
        );
        $incomingPurchaseOrder = $this->createIncomingPurchaseOrder(
            $user,
            $supplier,
            $warehouse,
            $incomingProduct,
            'PO-'.$prefix.'-INCOMING-001',
            4,
            24000,
            now()->subDay()->toDateString(),
            now()->addDays(2)->toDateString()
        );

        $overdueProduct = $this->createWholesaleProduct(
            $user,
            $category,
            $prefix.'-OVERDUE-001',
            'Sucre reliquat',
            'sac',
            18500,
            14900,
            1,
        );
        $this->seedWholesaleStock($user, $warehouse, $overdueProduct, 4, 14900, 'Stock grossiste reliquat');
        $overdueOrder = $this->createWholesaleOrder(
            $user,
            $customer,
            $warehouse,
            $overdueProduct,
            'ORDER-'.$prefix.'-OVERDUE-001',
            5,
            2,
            18500,
            'partial_delivered',
            now()->subDays(5)->toDateString(),
            now()->subDay()->toDateString(),
            now()->subDay()->toDateString(),
            'Commande grossiste en retard'
        );

        return compact(
            'warehouse',
            'customer',
            'supplier',
            'category',
            'atRiskProduct',
            'atRiskOrder',
            'incomingProduct',
            'incomingOrder',
            'incomingPurchaseOrder',
            'overdueProduct',
            'overdueOrder',
        );
    }

    private function createWholesaleProduct(
        User $user,
        ProductCategory $category,
        string $sku,
        string $name,
        string $unit,
        float $salePrice,
        float $purchasePrice,
        float $minStock = 1,
    ): Product {
        return Product::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'category_id' => $category->id,
            'sku' => $sku,
            'name' => $name,
            'unit' => $unit,
            'type' => 'stockable',
            'tracking_type' => 'none',
            'sale_price' => $salePrice,
            'purchase_price' => $purchasePrice,
            'min_stock' => $minStock,
            'is_active' => true,
            'sale_ok' => true,
            'purchase_ok' => true,
        ]);
    }

    private function seedWholesaleStock(
        User $user,
        Warehouse $warehouse,
        Product $product,
        float $quantity,
        float $unitCost,
        string $reason,
    ): void {
        StockMovement::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'movement_type' => 'opening',
            'quantity_in' => $quantity,
            'quantity_out' => 0,
            'unit_cost' => $unitCost,
            'reason' => $reason,
            'movement_date' => now()->subDays(5),
            'created_by' => $user->id,
        ]);
    }

    private function createWholesaleOrder(
        User $user,
        Partner $customer,
        Warehouse $warehouse,
        Product $product,
        string $orderNumber,
        float $qty,
        float $deliveredQty,
        float $unitPrice,
        string $status,
        string $orderDate,
        string $requestedDeliveryDate,
        string $commitmentDate,
        string $notes,
    ): SalesOrder {
        $order = SalesOrder::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'order_number' => $orderNumber,
            'order_date' => $orderDate,
            'requested_delivery_date' => $requestedDeliveryDate,
            'commitment_date' => $commitmentDate,
            'status' => $status,
            'subtotal' => $qty * $unitPrice,
            'total' => $qty * $unitPrice,
            'notes' => $notes,
            'confirmed_at' => $status !== 'draft' ? $orderDate : null,
            'created_by' => $user->id,
        ]);

        SalesOrderItem::query()->create([
            'sales_order_id' => $order->id,
            'product_id' => $product->id,
            'description' => 'Ligne '.$notes,
            'qty' => $qty,
            'delivered_qty' => $deliveredQty,
            'unit_price' => $unitPrice,
            'line_total' => $qty * $unitPrice,
        ]);

        return $order;
    }

    private function createIncomingPurchaseOrder(
        User $user,
        Partner $supplier,
        Warehouse $warehouse,
        Product $product,
        string $orderNumber,
        float $qty,
        float $unitCost,
        string $orderDate,
        string $expectedReceiptDate,
    ): PurchaseOrder {
        $order = PurchaseOrder::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'order_number' => $orderNumber,
            'order_date' => $orderDate,
            'expected_receipt_date' => $expectedReceiptDate,
            'status' => 'confirmed',
            'subtotal' => $qty * $unitCost,
            'total' => $qty * $unitCost,
            'confirmed_at' => $orderDate,
            'created_by' => $user->id,
        ]);

        PurchaseOrderItem::query()->create([
            'purchase_order_id' => $order->id,
            'product_id' => $product->id,
            'description' => 'Appro couverture grossiste',
            'qty' => $qty,
            'received_qty' => 0,
            'unit_cost' => $unitCost,
            'line_total' => $qty * $unitCost,
        ]);

        return $order;
    }
}
