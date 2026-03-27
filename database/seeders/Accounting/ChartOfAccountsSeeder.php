<?php

namespace Database\Seeders\Accounting;

use App\Modules\Accounting\Services\ChartOfAccountsService;
use App\Modules\Core\Company\Models\Company;
use Illuminate\Database\Seeder;

class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(ChartOfAccountsService::class);

        Company::query()->pluck('id')->each(fn (int $companyId) => $service->ensureDefaultAccounts($companyId));
    }
}
