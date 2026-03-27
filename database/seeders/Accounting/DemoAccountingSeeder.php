<?php

namespace Database\Seeders\Accounting;

use App\Modules\Accounting\Services\AccountingService;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Purchases\Models\PurchaseBill;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Treasury\Models\Payment;
use Illuminate\Database\Seeder;

class DemoAccountingSeeder extends Seeder
{
    public function run(): void
    {
        $accountingService = app(AccountingService::class);

        SalesInvoice::query()->where('status', 'validated')->with('creator')->get()->each(function (SalesInvoice $invoice) use ($accountingService) {
            $user = $invoice->creator;

            if ($user) {
                $accountingService->recordSalesInvoice($invoice, $user);
            }
        });

        PurchaseBill::query()->where('status', 'validated')->with('creator')->get()->each(function (PurchaseBill $bill) use ($accountingService) {
            $user = $bill->creator;

            if ($user) {
                $accountingService->recordPurchaseBill($bill, $user);
            }
        });

        Payment::query()->with(['creator', 'cashAccount', 'allocations.allocatable'])->get()->each(function (Payment $payment) use ($accountingService) {
            $user = $payment->creator;
            $document = optional($payment->allocations->first())->allocatable;

            if (! $user || ! $document || ! $payment->cashAccount) {
                return;
            }

            if ($payment->payment_type === 'supplier_payment' && $document instanceof PurchaseBill) {
                $accountingService->recordSupplierPayment($payment, $document, $payment->cashAccount, $user);
                return;
            }

            if ($payment->payment_type === 'customer_receipt' && $document instanceof SalesInvoice) {
                $accountingService->recordCustomerReceipt($payment, $document, $payment->cashAccount, $user);
            }
        });

        Expense::query()->where('status', 'validated')->with(['creator', 'category', 'cashAccount', 'supplier'])->get()->each(function (Expense $expense) use ($accountingService) {
            $user = $expense->creator;

            if ($user) {
                $accountingService->recordExpense($expense, $user);
            }
        });
    }
}
