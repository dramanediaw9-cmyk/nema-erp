<?php

namespace Database\Seeders\Accounting;

use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Core\Company\Models\Company;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class AccountingPeriodSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->where('name', 'Nema Distribution')->first();

        if (! $company) {
            return;
        }

        $year = (int) now()->format('Y');

        foreach (range(1, 12) as $month) {
            $start = Carbon::create($year, $month, 1)->startOfMonth();
            $end = $start->copy()->endOfMonth();

            AccountingPeriod::query()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                ],
                [
                    'name' => sprintf('Periode %02d/%d', $month, $year),
                    'status' => 'open',
                    'closed_at' => null,
                    'closed_by' => null,
                ]
            );
        }
    }
}
