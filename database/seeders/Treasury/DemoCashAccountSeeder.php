<?php

namespace Database\Seeders\Treasury;

use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Treasury\Models\CashAccount;
use Illuminate\Database\Seeder;

class DemoCashAccountSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->where('name', 'Nema Distribution')->firstOrFail();
        $branch = Branch::query()->where('company_id', $company->id)->where('code', 'BKO')->firstOrFail();

        foreach ([
            ['name' => 'Caisse principale', 'type' => 'cash', 'account_number' => null, 'opening_balance' => 150000, 'branch_id' => $branch->id],
            ['name' => 'Banque BDM', 'type' => 'bank', 'account_number' => 'BDM-001', 'opening_balance' => 500000, 'branch_id' => null],
            ['name' => 'Orange Money', 'type' => 'mobile_money', 'account_number' => 'OM-223-76000000', 'opening_balance' => 80000, 'branch_id' => $branch->id],
            ['name' => 'Wave', 'type' => 'mobile_money', 'account_number' => 'WAVE-223-76000011', 'opening_balance' => 65000, 'branch_id' => $branch->id],
            ['name' => 'Moov Money', 'type' => 'mobile_money', 'account_number' => 'MOOV-223-76000022', 'opening_balance' => 42000, 'branch_id' => $branch->id],
        ] as $account) {
            CashAccount::query()->updateOrCreate(
                ['company_id' => $company->id, 'name' => $account['name']],
                [
                    'branch_id' => $account['branch_id'],
                    'type' => $account['type'],
                    'account_number' => $account['account_number'],
                    'opening_balance' => $account['opening_balance'],
                    'is_active' => true,
                ]
            );
        }
    }
}
