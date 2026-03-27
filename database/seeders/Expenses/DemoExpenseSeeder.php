<?php

namespace Database\Seeders\Expenses;

use App\Models\User;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Expenses\Models\ExpenseCategory;
use App\Modules\Expenses\Services\ExpenseService;
use App\Modules\Partners\Models\Partner;
use App\Modules\Treasury\Models\CashAccount;
use Illuminate\Database\Seeder;

class DemoExpenseSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->where('name', 'Nema Distribution')->firstOrFail();
        $branch = $company->branches()->where('code', 'BKO')->firstOrFail();
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $categories = collect([
            ['name' => 'Carburant', 'description' => 'Charges liees aux deplacements et approvisionnements', 'default_account_code' => '625100'],
            ['name' => 'Loyer', 'description' => 'Charges de local et bureaux', 'default_account_code' => '613000'],
            ['name' => 'Fournitures', 'description' => 'Achats de fournitures et petit materiel', 'default_account_code' => '606300'],
        ])->mapWithKeys(function (array $data) use ($company) {
            $category = ExpenseCategory::query()->updateOrCreate(
                ['company_id' => $company->id, 'name' => $data['name']],
                [
                    'description' => $data['description'],
                    'default_account_code' => $data['default_account_code'],
                    'is_active' => true,
                ]
            );

            return [$data['name'] => $category];
        });

        if (Expense::query()->where('company_id', $company->id)->where('description', 'Achat de carburant pour livraison Bamako')->exists()) {
            return;
        }

        $supplier = Partner::query()->suppliers()->where('company_id', $company->id)->first();
        $cashAccount = CashAccount::query()->where('company_id', $company->id)->where('name', 'Caisse principale')->first();
        $expenseService = app(ExpenseService::class);

        $expenseService->createValidated($company->id, $branch->id, [
            'expense_category_id' => $categories['Carburant']->id,
            'supplier_id' => $supplier?->id,
            'cash_account_id' => $cashAccount?->id,
            'expense_date' => now()->subDays(2)->format('Y-m-d'),
            'description' => 'Achat de carburant pour livraison Bamako',
            'total' => 35000,
            'payment_date' => now()->subDays(2)->format('Y-m-d'),
            'payment_method' => 'cash',
            'payment_reference' => 'DEP-DEMO-001',
            'notes' => 'Depense de demonstration',
        ], $manager);
    }
}
