<?php

namespace Database\Seeders\Inventory;

use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DemoStockSeeder extends Seeder
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
            'PRD-0001' => ['quantity' => 120, 'unit_cost' => 250],
            'PRD-0002' => ['quantity' => 14, 'unit_cost' => 500],
            'PRD-0005' => ['quantity' => 48, 'unit_cost' => 650],
            'PRD-0006' => ['quantity' => 72, 'unit_cost' => 300],
            'PRD-0007' => ['quantity' => 18, 'unit_cost' => 3800],
            'PRD-0008' => ['quantity' => 36, 'unit_cost' => 1150],
            'PRD-0009' => ['quantity' => 64, 'unit_cost' => 325],
            'PRD-0010' => ['quantity' => 54, 'unit_cost' => 425],
            'PRD-0011' => ['quantity' => 40, 'unit_cost' => 220],
            'PRD-0012' => ['quantity' => 32, 'unit_cost' => 700],
            'PRD-0013' => ['quantity' => 28, 'unit_cost' => 450],
            'PRD-0014' => ['quantity' => 45, 'unit_cost' => 375],
        ];

        foreach ($stocks as $sku => $values) {
            $product = Product::query()->where('company_id', $company->id)->where('sku', $sku)->first();

            if (! $product) {
                continue;
            }

            $exists = StockMovement::query()
                ->where('company_id', $company->id)
                ->where('branch_id', $branch->id)
                ->where('product_id', $product->id)
                ->where('movement_type', 'opening')
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
                'reason' => 'Stock initial de demonstration',
                'movement_date' => Carbon::now()->subDays(2),
            ]);
        }
    }
}
