<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Expenses\Models\ExpenseCategory;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Partners\Models\Partner;
use App\Modules\Purchases\Models\PurchaseBill;
use App\Modules\Sales\Models\DeliveryNote;
use App\Modules\Sales\Models\SalesCreditNote;
use App\Modules\Sales\Models\SalesCreditNoteItem;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesInvoiceItem;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Models\SalesOrderItem;
use App\Modules\Sales\Models\SalesQuote;
use App\Modules\Sales\Models\SalesQuoteItem;
use App\Modules\Treasury\Models\CashAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentPdfPrintTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_core_business_documents_open_as_pdf(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $user->company_id)->firstOrFail();
        $supplier = Partner::query()->suppliers()->where('company_id', $user->company_id)->firstOrFail();
        $warehouse = Warehouse::query()->where('company_id', $user->company_id)->firstOrFail();
        $product = Product::query()->where('company_id', $user->company_id)->where('is_active', true)->firstOrFail();

        $quote = SalesQuote::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'customer_id' => $customer->id,
            'quote_number' => 'QTE-PDF-0001',
            'quote_date' => now()->toDateString(),
            'valid_until' => now()->addDays(7)->toDateString(),
            'status' => 'draft',
            'subtotal' => 15000,
            'total' => 15000,
            'notes' => 'Devis de test PDF',
            'created_by' => $user->id,
        ]);

        SalesQuoteItem::query()->create([
            'sales_quote_id' => $quote->id,
            'product_id' => $product->id,
            'description' => $product->name,
            'qty' => 2,
            'unit_price' => 7500,
            'line_total' => 15000,
        ]);

        $order = SalesOrder::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'order_number' => 'CMD-PDF-0001',
            'order_date' => now()->toDateString(),
            'requested_delivery_date' => now()->addDays(2)->toDateString(),
            'status' => 'draft',
            'subtotal' => 18000,
            'total' => 18000,
            'notes' => 'Commande de test PDF',
            'created_by' => $user->id,
        ]);

        $orderItem = SalesOrderItem::query()->create([
            'sales_order_id' => $order->id,
            'product_id' => $product->id,
            'description' => $product->name,
            'qty' => 3,
            'delivered_qty' => 0,
            'unit_price' => 6000,
            'line_total' => 18000,
        ]);

        $invoice = SalesInvoice::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'sale_channel' => 'sales',
            'invoice_number' => 'FAC-PDF-0001',
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(15)->toDateString(),
            'status' => 'validated',
            'payment_status' => 'unpaid',
            'subtotal' => 22000,
            'discount_type' => 'fixed',
            'discount_value' => 0,
            'discount_total' => 0,
            'net_total' => 22000,
            'tax_total' => 0,
            'total' => 22000,
            'amount_paid' => 0,
            'balance_due' => 22000,
            'stock_posted' => false,
            'notes' => 'Facture de test PDF',
            'validated_at' => now(),
            'approved_at' => now(),
            'approved_by' => $user->id,
            'created_by' => $user->id,
        ]);

        $invoiceItem = SalesInvoiceItem::query()->create([
            'sales_invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'description' => $product->name,
            'qty' => 4,
            'unit_price' => 5500,
            'line_subtotal' => 22000,
            'discount_type' => 'fixed',
            'discount_value' => 0,
            'discount_total' => 0,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'line_total' => 22000,
        ]);

        $deliveryNote = DeliveryNote::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'warehouse_id' => $warehouse->id,
            'sales_order_id' => $order->id,
            'customer_id' => $customer->id,
            'delivery_number' => 'BL-PDF-0001',
            'delivery_date' => now()->toDateString(),
            'status' => 'issued',
            'subtotal' => 18000,
            'total' => 18000,
            'notes' => 'Livraison de test PDF',
            'issued_at' => now(),
            'created_by' => $user->id,
        ]);

        $deliveryNote->items()->create([
            'sales_order_item_id' => $orderItem->id,
            'product_id' => $product->id,
            'description' => $product->name,
            'qty' => 3,
            'unit_price' => 6000,
            'line_total' => 18000,
        ]);

        $creditNote = SalesCreditNote::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'warehouse_id' => $warehouse->id,
            'sales_invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'credit_note_number' => 'AV-PDF-0001',
            'credit_note_date' => now()->toDateString(),
            'status' => 'validated',
            'restock_items' => false,
            'subtotal' => 5500,
            'total' => 5500,
            'notes' => 'Avoir de test PDF',
            'validated_at' => now(),
            'created_by' => $user->id,
        ]);

        SalesCreditNoteItem::query()->create([
            'sales_credit_note_id' => $creditNote->id,
            'sales_invoice_item_id' => $invoiceItem->id,
            'product_id' => $product->id,
            'description' => $product->name,
            'qty' => 1,
            'unit_price' => 5500,
            'line_total' => 5500,
        ]);

        $purchase = PurchaseBill::query()->where('company_id', $user->company_id)->first();

        if (! $purchase) {
            $purchase = PurchaseBill::query()->create([
                'tenant_id' => $user->tenant_id,
                'company_id' => $user->company_id,
                'branch_id' => $user->branch_id,
                'warehouse_id' => $warehouse->id,
                'supplier_id' => $supplier->id,
                'bill_number' => 'ACH-PDF-0001',
                'bill_date' => now()->toDateString(),
                'due_date' => now()->addDays(20)->toDateString(),
                'status' => 'validated',
                'payment_status' => 'unpaid',
                'subtotal' => 9000,
                'net_total' => 9000,
                'tax_total' => 0,
                'total' => 9000,
                'amount_paid' => 0,
                'balance_due' => 9000,
                'notes' => 'Achat de test PDF',
                'validated_at' => now(),
                'approved_at' => now(),
                'approved_by' => $user->id,
                'created_by' => $user->id,
            ]);

            $purchase->items()->create([
                'product_id' => $product->id,
                'description' => $product->name,
                'qty' => 2,
                'unit_cost' => 4500,
                'line_total' => 9000,
            ]);
        }

        $expense = Expense::query()->where('company_id', $user->company_id)->first();

        if (! $expense) {
            $expenseCategory = ExpenseCategory::query()->where('company_id', $user->company_id)->firstOrFail();
            $cashAccount = CashAccount::query()->where('company_id', $user->company_id)->firstOrFail();

            $expense = Expense::query()->create([
                'tenant_id' => $user->tenant_id,
                'company_id' => $user->company_id,
                'branch_id' => $user->branch_id,
                'expense_category_id' => $expenseCategory->id,
                'supplier_id' => $supplier->id,
                'cash_account_id' => $cashAccount->id,
                'expense_number' => 'DEP-PDF-0001',
                'expense_date' => now()->toDateString(),
                'description' => 'Depense de test PDF',
                'total' => 7000,
                'status' => 'validated',
                'payment_status' => 'paid',
                'payment_date' => now()->toDateString(),
                'payment_method' => 'cash',
                'payment_reference' => 'PAY-PDF-0001',
                'notes' => 'Depense de test PDF',
                'approved_at' => now(),
                'approved_by' => $user->id,
                'created_by' => $user->id,
            ]);
        }

        $documents = [
            route('quotes.print', $quote),
            route('orders.print', $order),
            route('sales.print', $invoice),
            route('delivery-notes.print', $deliveryNote),
            route('credit-notes.print', $creditNote),
            route('purchases.print', $purchase),
            route('expenses.print', $expense),
        ];

        foreach ($documents as $url) {
            $response = $this->actingAs($user)
                ->withSession([
                    'current_company_id' => $user->company_id,
                    'current_branch_id' => $user->branch_id,
                ])
                ->get($url);

            $response->assertOk();
            $this->assertPdfResponse($response);
        }
    }

    private function assertPdfResponse($response): void
    {
        $contentType = $response->headers->get('content-type', '');
        $content = $response->getContent();

        $this->assertStringStartsWith('application/pdf', $contentType);
        $this->assertIsString($content);
        $this->assertStringStartsWith('%PDF-', $content);
    }
}
