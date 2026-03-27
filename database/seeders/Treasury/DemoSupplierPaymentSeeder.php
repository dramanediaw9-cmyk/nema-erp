<?php

namespace Database\Seeders\Treasury;

use App\Models\User;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Purchases\Models\PurchaseBill;
use App\Modules\Treasury\Models\CashAccount;
use App\Modules\Treasury\Models\Payment;
use App\Modules\Treasury\Services\PaymentService;
use Illuminate\Database\Seeder;

class DemoSupplierPaymentSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->where('name', 'Nema Distribution')->firstOrFail();
        $branch = $company->branches()->where('code', 'BKO')->firstOrFail();
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        if (Payment::query()->where('company_id', $company->id)->where('reference', 'SUP-DEMO-001')->exists()) {
            return;
        }

        $bill = PurchaseBill::query()
            ->where('company_id', $company->id)
            ->where('notes', 'Facture fournisseur de demonstration')
            ->first();

        if (! $bill || (float) $bill->balance_due <= 0) {
            return;
        }

        $cashAccount = CashAccount::query()
            ->where('company_id', $company->id)
            ->where('name', 'Banque BDM')
            ->first()
            ?? CashAccount::query()->where('company_id', $company->id)->orderBy('id')->firstOrFail();

        app(PaymentService::class)->recordSupplierPayment(
            $company->id,
            $branch->id,
            $bill,
            $cashAccount,
            [
                'payment_date' => now()->subDays(2)->format('Y-m-d'),
                'amount' => min((float) $bill->balance_due, 10000),
                'method' => 'bank_transfer',
                'reference' => 'SUP-DEMO-001',
                'notes' => 'Reglement fournisseur de demonstration',
            ],
            $manager,
        );
    }
}
