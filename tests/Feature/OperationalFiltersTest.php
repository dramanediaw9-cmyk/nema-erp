<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductCategory;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Expenses\Models\ExpenseCategory;
use App\Modules\Inventory\Models\ProductLot;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Purchases\Models\PurchaseBill;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Treasury\Models\CashAccount;
use App\Modules\Treasury\Models\Payment;
use App\Modules\Treasury\Models\TreasuryReconciliation;
use App\Modules\Treasury\Models\TreasuryReconciliationPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_payments_page_filters_by_search_account_method_and_type(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $supplierPayment = Payment::query()->where('company_id', $user->company_id)->where('reference', 'SUP-DEMO-001')->firstOrFail();
        $customerPayment = Payment::query()->where('company_id', $user->company_id)->where('reference', 'REC-DEMO-001')->firstOrFail();
        $bankAccount = CashAccount::query()->where('company_id', $user->company_id)->where('name', 'Banque BDM')->firstOrFail();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('payments.index', [
                'search' => $supplierPayment->payment_number,
                'cash_account_id' => $bankAccount->id,
                'method' => 'bank_transfer',
                'payment_type' => 'supplier_payment',
            ]))
            ->assertOk()
            ->assertSee($supplierPayment->payment_number)
            ->assertSee('Banque BDM')
            ->assertDontSee($customerPayment->payment_number);
    }

    public function test_payments_page_can_highlight_unreconciled_mobile_money_without_reference(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $waveAccount = CashAccount::query()->where('company_id', $user->company_id)->where('name', 'Wave')->firstOrFail();
        $orangeAccount = CashAccount::query()->where('company_id', $user->company_id)->where('name', 'Orange Money')->firstOrFail();

        $wavePayment = Payment::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'cash_account_id' => $waveAccount->id,
            'partner_id' => null,
            'payment_number' => 'ENC-MM-TEST-001',
            'direction' => 'in',
            'payment_type' => 'customer_receipt',
            'payment_date' => now()->toDateString(),
            'amount' => 25000,
            'method' => 'wave',
            'reference' => null,
            'notes' => 'Encaissement Wave sans reference',
            'created_by' => $user->id,
        ]);

        $orangePayment = Payment::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'cash_account_id' => $orangeAccount->id,
            'partner_id' => null,
            'payment_number' => 'ENC-MM-TEST-002',
            'direction' => 'in',
            'payment_type' => 'customer_receipt',
            'payment_date' => now()->toDateString(),
            'amount' => 18000,
            'method' => 'orange_money',
            'reference' => 'OM-REF-TEST-002',
            'notes' => 'Encaissement Orange rapproche',
            'created_by' => $user->id,
        ]);

        $reconciliation = TreasuryReconciliation::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'cash_account_id' => $orangeAccount->id,
            'reconciliation_number' => 'RAP-BKO-TEST-001',
            'statement_date' => now()->toDateString(),
            'statement_reference' => 'WALLET-STATEMENT-001',
            'statement_balance' => 18000,
            'matched_total' => 18000,
            'book_balance' => 18000,
            'difference' => 0,
            'payments_count' => 1,
            'status' => 'balanced',
            'notes' => 'Rapprochement test mobile money',
            'created_by' => $user->id,
        ]);

        TreasuryReconciliationPayment::query()->create([
            'treasury_reconciliation_id' => $reconciliation->id,
            'payment_id' => $orangePayment->id,
        ]);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('payments.index', [
                'method' => 'wave',
                'reconciliation_status' => 'unreconciled',
                'missing_reference' => 1,
            ]))
            ->assertOk()
            ->assertSee('Pilotage mobile money')
            ->assertSee('ENC-MM-TEST-001')
            ->assertSee('Reference manquante')
            ->assertSee('A rapprocher')
            ->assertDontSee('ENC-MM-TEST-002');
    }

    public function test_stock_page_filters_by_category_search_and_stock_state(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $category = ProductCategory::query()->where('company_id', $user->company_id)->where('name', 'Epicerie')->firstOrFail();

        Product::query()->create([
            'company_id' => $user->company_id,
            'category_id' => $category->id,
            'sku' => 'PRD-LOW01',
            'name' => 'Riz local 5kg',
            'unit' => 'sac',
            'type' => 'stockable',
            'sale_price' => 1500,
            'purchase_price' => 1100,
            'min_stock' => 5,
            'description' => 'Produit de test faible stock',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('stock.index', [
                'search' => 'Riz',
                'category_id' => $category->id,
                'stock_state' => 'low',
            ]))
            ->assertOk()
            ->assertSee('Riz local 5kg')
            ->assertSee('PRD-LOW01')
            ->assertDontSee('Sucre 1kg')
            ->assertDontSee('Eau minerale 1.5L');
    }


    public function test_stock_page_handles_tracked_lot_products_without_sql_errors(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $category = ProductCategory::query()->where('company_id', $user->company_id)->where('name', 'Epicerie')->firstOrFail();
        $warehouse = Warehouse::query()
            ->where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->where('is_default', true)
            ->firstOrFail();

        $product = Product::query()->create([
            'company_id' => $user->company_id,
            'category_id' => $category->id,
            'sku' => 'PRD-LOT-STOCK01',
            'barcode' => '770000000501',
            'name' => 'Nido poudre 900g',
            'unit' => 'boite',
            'type' => 'stockable',
            'tracking_type' => 'lot',
            'sale_price' => 4200,
            'purchase_price' => 3250,
            'min_stock' => 3,
            'description' => 'Produit lot trace pour ecran stock',
            'is_active' => true,
            'sale_ok' => true,
            'purchase_ok' => true,
        ]);

        ProductLot::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'tracking_type' => 'lot',
            'lot_number' => 'NIDO-LOT-001',
            'expires_at' => now()->addDays(25)->toDateString(),
            'received_at' => now()->subDays(7)->toDateString(),
            'unit_cost' => 3250,
            'quantity_received' => 10,
            'quantity_available' => 10,
        ]);

        ProductLot::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'tracking_type' => 'lot',
            'lot_number' => 'NIDO-LOT-EXP',
            'expires_at' => now()->subDay()->toDateString(),
            'received_at' => now()->subDays(20)->toDateString(),
            'unit_cost' => 3250,
            'quantity_received' => 4,
            'quantity_available' => 4,
        ]);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('stock.index', [
                'search' => 'Nido',
                'category_id' => $category->id,
            ]))
            ->assertOk()
            ->assertSee('Nido poudre 900g')
            ->assertSee('PRD-LOT-STOCK01');
    }

    public function test_sales_page_filters_by_search_workflow_payment_and_branch(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $customerId = \App\Modules\Partners\Models\Partner::query()->customers()->where('company_id', $user->company_id)->value('id');
        $product = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('sales.store'), [
                'customer_id' => $customerId,
                'invoice_date' => now()->toDateString(),
                'notes' => 'FILTER-SALE-UNPAID',
                'items' => [[
                    'product_id' => $product->id,
                    'description' => 'Facture de test filtre',
                    'qty' => 1,
                    'unit_price' => 400,
                ]],
            ])
            ->assertRedirect();

        $demoInvoice = SalesInvoice::query()->where('company_id', $user->company_id)->where('notes', 'Facture de demonstration initiale')->firstOrFail();
        $newInvoice = SalesInvoice::query()->where('company_id', $user->company_id)->where('notes', 'FILTER-SALE-UNPAID')->firstOrFail();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('sales.index', [
                'search' => $demoInvoice->invoice_number,
                'branch_id' => $user->branch_id,
                'status' => 'validated',
                'payment_status' => 'partial',
            ]))
            ->assertOk()
            ->assertSee($demoInvoice->invoice_number)
            ->assertDontSee($newInvoice->invoice_number);
    }

    public function test_purchases_page_filters_by_search_workflow_payment_and_branch(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $supplierId = \App\Modules\Partners\Models\Partner::query()->suppliers()->where('company_id', $user->company_id)->value('id');
        $product = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('purchases.store'), [
                'supplier_id' => $supplierId,
                'bill_date' => now()->toDateString(),
                'notes' => 'FILTER-PURCHASE-UNPAID',
                'items' => [[
                    'product_id' => $product->id,
                    'description' => 'Achat de test filtre',
                    'qty' => 2,
                    'unit_cost' => 250,
                ]],
            ])
            ->assertRedirect();

        $demoBill = PurchaseBill::query()->where('company_id', $user->company_id)->where('notes', 'Facture fournisseur de demonstration')->firstOrFail();
        $newBill = PurchaseBill::query()->where('company_id', $user->company_id)->where('notes', 'FILTER-PURCHASE-UNPAID')->firstOrFail();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('purchases.index', [
                'search' => $demoBill->bill_number,
                'branch_id' => $user->branch_id,
                'status' => 'validated',
                'payment_status' => 'partial',
            ]))
            ->assertOk()
            ->assertSee($demoBill->bill_number)
            ->assertDontSee($newBill->bill_number);
    }

    public function test_expenses_page_filters_by_search_category_workflow_and_payment(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $carburant = ExpenseCategory::query()->where('company_id', $user->company_id)->where('name', 'Carburant')->firstOrFail();
        $loyer = ExpenseCategory::query()->where('company_id', $user->company_id)->where('name', 'Loyer')->firstOrFail();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('expenses.store'), [
                'expense_category_id' => $loyer->id,
                'expense_date' => now()->toDateString(),
                'description' => 'Loyer bureau test filtre',
                'total' => 90000,
            ])
            ->assertRedirect();

        $demoExpense = Expense::query()->where('company_id', $user->company_id)->where('description', 'Achat de carburant pour livraison Bamako')->firstOrFail();
        $newExpense = Expense::query()->where('company_id', $user->company_id)->where('description', 'Loyer bureau test filtre')->firstOrFail();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('expenses.index', [
                'search' => 'carburant',
                'branch_id' => $user->branch_id,
                'category_id' => $carburant->id,
                'status' => 'validated',
                'payment_status' => 'paid',
            ]))
            ->assertOk()
            ->assertSee($demoExpense->description)
            ->assertDontSee($newExpense->description);
    }

    public function test_stock_movements_page_filters_by_search_type_and_branch(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('stock.movements', [
                'search' => 'PRD-0001',
                'branch_id' => $user->branch_id,
                'movement_type' => 'sale',
            ]))
            ->assertOk()
            ->assertSee('Eau minerale 1.5L')
            ->assertSee('Vente')
            ->assertDontSee('Stock initial de demonstration');
    }

    private function workspaceSession(User $user): array
    {
        return [
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ];
    }
}
