<?php

namespace Database\Seeders\Inventory;

use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class PosTestStockSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->where('name', 'Nema Distribution')->firstOrFail();
        $branch = Branch::query()->where('company_id', $company->id)->where('code', 'BKO')->firstOrFail();
        $warehouse = Warehouse::query()
            ->where('company_id', $company->id)
            ->where('branch_id', $branch->id)
            ->where('is_default', true)
            ->firstOrFail();

        $stocks = [
            'POS-TEST-001' => ['quantity' => 90, 'unit_cost' => 120],
            'POS-TEST-002' => ['quantity' => 70, 'unit_cost' => 180],
            'POS-TEST-003' => ['quantity' => 55, 'unit_cost' => 475],
            'POS-TEST-004' => ['quantity' => 24, 'unit_cost' => 1500],
        ];

        foreach ($stocks as $sku => $values) {
            $product = Product::query()->where('company_id', $company->id)->where('sku', $sku)->first();

            if (! $product) {
                continue;
            }

            $exists = StockMovement::query()
                ->where('company_id', $company->id)
                ->where('branch_id', $branch->id)
                ->where('warehouse_id', $warehouse->id)
                ->where('product_id', $product->id)
                ->where('movement_type', 'opening')
                ->where('reason', 'Stock initial tests POS')
                ->exists();

            if ($exists) {
                continue;
            }

            StockMovement::query()->create([
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'movement_type' => 'opening',
                'quantity_in' => $values['quantity'],
                'quantity_out' => 0,
                'unit_cost' => $values['unit_cost'],
                'reason' => 'Stock initial tests POS',
                'movement_date' => Carbon::now()->subDay(),
            ]);
        }
    }
}
