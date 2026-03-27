<?php

namespace Database\Seeders\Purchases;

use App\Models\User;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Partners\Models\Partner;
use App\Modules\Purchases\Models\PurchaseBill;
use App\Modules\Purchases\Services\PurchaseBillService;
use Illuminate\Database\Seeder;

class DemoPurchaseSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->where('name', 'Nema Distribution')->firstOrFail();
        $branch = $company->branches()->where('code', 'BKO')->firstOrFail();
        $supplier = Partner::query()->suppliers()->where('company_id', $company->id)->orderBy('id')->firstOrFail();
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        if (PurchaseBill::query()->where('company_id', $company->id)->where('notes', 'Facture fournisseur de demonstration')->exists()) {
            return;
        }

        $purchaseService = app(PurchaseBillService::class);
        $products = $company->products()->whereIn('sku', ['PRD-0001', 'PRD-0002'])->get()->keyBy('sku');

        $purchaseService->createValidated(
            $company->id,
            $branch->id,
            $supplier,
            [
                'bill_date' => now()->subDays(4)->format('Y-m-d'),
                'due_date' => now()->addDays(12)->format('Y-m-d'),
                'notes' => 'Facture fournisseur de demonstration',
            ],
            $purchaseService->normalizeItems($company->id, [
                [
                    'product_id' => $products['PRD-0001']->id,
                    'description' => 'Reapprovisionnement eau minerale',
                    'qty' => 50,
                    'unit_cost' => 245,
                ],
                [
                    'product_id' => $products['PRD-0002']->id,
                    'description' => 'Reapprovisionnement sucre',
                    'qty' => 30,
                    'unit_cost' => 480,
                ],
            ]),
            $manager,
        );
    }
}
