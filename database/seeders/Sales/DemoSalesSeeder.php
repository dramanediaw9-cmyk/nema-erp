<?php

namespace Database\Seeders\Sales;

use App\Models\User;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Partners\Models\Partner;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Services\SalesInvoiceService;
use App\Modules\Treasury\Models\CashAccount;
use App\Modules\Treasury\Services\PaymentService;
use Illuminate\Database\Seeder;

class DemoSalesSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->where('name', 'Nema Distribution')->firstOrFail();
        $branch = $company->branches()->where('code', 'BKO')->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $company->id)->where('name', 'Sahel Market')->firstOrFail();
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $cashAccount = CashAccount::query()->where('company_id', $company->id)->where('name', 'Caisse principale')->firstOrFail();

        $existing = SalesInvoice::query()
            ->where('company_id', $company->id)
            ->where('notes', 'Facture de demonstration initiale')
            ->first();

        if ($existing) {
            return;
        }

        $salesService = app(SalesInvoiceService::class);
        $paymentService = app(PaymentService::class);
        $products = $company->products()->whereIn('sku', ['PRD-0001', 'PRD-0002'])->get()->keyBy('sku');

        $items = collect([
            [
                'product_id' => $products['PRD-0001']->id,
                'description' => 'Eau minerale 1.5L',
                'qty' => 10,
                'unit_price' => 400,
            ],
            [
                'product_id' => $products['PRD-0002']->id,
                'description' => 'Sucre 1kg',
                'qty' => 2,
                'unit_price' => 700,
            ],
        ]);

        $invoice = $salesService->createValidated(
            $company->id,
            $branch->id,
            $customer,
            [
                'invoice_date' => now()->subDays(3)->format('Y-m-d'),
                'due_date' => now()->addDays(10)->format('Y-m-d'),
                'notes' => 'Facture de demonstration initiale',
            ],
            $salesService->normalizeItems($company->id, $items->all()),
            $manager,
        );

        $paymentService->recordCustomerReceipt(
            $company->id,
            $branch->id,
            $invoice,
            $cashAccount,
            [
                'payment_date' => now()->subDays(1)->format('Y-m-d'),
                'amount' => 3000,
                'method' => 'cash',
                'reference' => 'REC-DEMO-001',
                'notes' => 'Encaissement de demonstration',
            ],
            $manager,
        );
    }
}
