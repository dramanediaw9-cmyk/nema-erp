<?php

namespace Database\Seeders\Inventory;

use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Inventory\Models\StockMovement;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DemoStockSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->where('name', 'Nema Distribution')->firstOrFail();
        $branch = Branch::query()->where('company_id', $company->id)->where('code', 'BKO')->firstOrFail();

        $stocks = [
            'PRD-0001' => ['quantity' => 120, 'unit_cost' => 250],
            'PRD-0002' => ['quantity' => 14, 'unit_cost' => 500],
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
