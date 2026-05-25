<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductCategory;
use App\Modules\Core\Collaboration\Models\Attachment;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Expenses\Models\ExpenseCategory;
use App\Modules\Inventory\Models\ProductLot;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Partners\Models\Partner;
use App\Modules\Purchases\Models\PurchaseBill;
use App\Modules\Purchases\Models\PurchaseOrder;
use App\Modules\Purchases\Models\PurchaseOrderItem;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Models\SalesOrderItem;
use App\Modules\Sales\Models\SalesQuote;
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

    public function test_payments_page_can_filter_stale_unreconciled_mobile_money(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $waveAccount = CashAccount::query()->where('company_id', $user->company_id)->where('name', 'Wave')->firstOrFail();
        $orangeAccount = CashAccount::query()->where('company_id', $user->company_id)->where('name', 'Orange Money')->firstOrFail();

        $staleWave = Payment::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'cash_account_id' => $waveAccount->id,
            'partner_id' => null,
            'payment_number' => 'ENC-MM-OLD-001',
            'direction' => 'in',
            'payment_type' => 'customer_receipt',
            'payment_date' => now()->subDays(5)->toDateString(),
            'amount' => 41000,
            'method' => 'wave',
            'reference' => null,
            'notes' => 'Encaissement Wave ancien non rapproche',
            'created_by' => $user->id,
        ]);

        Payment::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'cash_account_id' => $waveAccount->id,
            'partner_id' => null,
            'payment_number' => 'ENC-MM-RECENT-001',
            'direction' => 'in',
            'payment_type' => 'customer_receipt',
            'payment_date' => now()->toDateString(),
            'amount' => 9000,
            'method' => 'wave',
            'reference' => 'WAVE-RECENT-001',
            'notes' => 'Encaissement Wave recent',
            'created_by' => $user->id,
        ]);

        $orangePayment = Payment::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'cash_account_id' => $orangeAccount->id,
            'partner_id' => null,
            'payment_number' => 'ENC-MM-OLD-002',
            'direction' => 'in',
            'payment_type' => 'customer_receipt',
            'payment_date' => now()->subDays(6)->toDateString(),
            'amount' => 15000,
            'method' => 'orange_money',
            'reference' => 'OM-OLD-002',
            'notes' => 'Encaissement Orange ancien rapproche',
            'created_by' => $user->id,
        ]);

        $reconciliation = TreasuryReconciliation::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'cash_account_id' => $orangeAccount->id,
            'reconciliation_number' => 'RAP-BKO-TEST-AGE-001',
            'statement_date' => now()->toDateString(),
            'statement_reference' => 'WALLET-AGE-001',
            'statement_balance' => 15000,
            'matched_total' => 15000,
            'book_balance' => 15000,
            'difference' => 0,
            'payments_count' => 1,
            'status' => 'balanced',
            'notes' => 'Rapprochement ancien mobile money',
            'created_by' => $user->id,
        ]);

        TreasuryReconciliationPayment::query()->create([
            'treasury_reconciliation_id' => $reconciliation->id,
            'payment_id' => $orangePayment->id,
        ]);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('payments.index', [
                'aging_state' => 'mobile_age_3_plus',
            ]))
            ->assertOk()
            ->assertSee('Voir 3+ jours')
            ->assertSee('Plus ancien ouvert')
            ->assertSee($staleWave->payment_number)
            ->assertDontSee('ENC-MM-RECENT-001')
            ->assertDontSee('ENC-MM-OLD-002');
    }

    public function test_payments_page_can_filter_pending_internal_transfer_deposits(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $bankAccount = CashAccount::query()->where('company_id', $user->company_id)->where('name', 'Banque BDM')->firstOrFail();

        $staleDeposit = Payment::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'cash_account_id' => $bankAccount->id,
            'partner_id' => null,
            'payment_number' => 'ENC-DEPOT-OLD-001',
            'direction' => 'in',
            'payment_type' => 'internal_transfer',
            'payment_date' => now()->subDays(4)->toDateString(),
            'amount' => 91000,
            'method' => 'bank_transfer',
            'reference' => 'BORD-DEPOT-OLD-001',
            'notes' => 'Depot agence ancien non rapproche',
            'created_by' => $user->id,
        ]);

        Payment::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'cash_account_id' => $bankAccount->id,
            'partner_id' => null,
            'payment_number' => 'ENC-DEPOT-RECENT-001',
            'direction' => 'in',
            'payment_type' => 'internal_transfer',
            'payment_date' => now()->toDateString(),
            'amount' => 15000,
            'method' => 'bank_transfer',
            'reference' => 'BORD-DEPOT-RECENT-001',
            'notes' => 'Depot agence recent',
            'created_by' => $user->id,
        ]);

        $reconciledDeposit = Payment::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'cash_account_id' => $bankAccount->id,
            'partner_id' => null,
            'payment_number' => 'ENC-DEPOT-OLD-002',
            'direction' => 'in',
            'payment_type' => 'internal_transfer',
            'payment_date' => now()->subDays(6)->toDateString(),
            'amount' => 27000,
            'method' => 'bank_transfer',
            'reference' => 'BORD-DEPOT-OLD-002',
            'notes' => 'Depot agence ancien rapproche',
            'created_by' => $user->id,
        ]);

        $reconciliation = TreasuryReconciliation::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'cash_account_id' => $bankAccount->id,
            'reconciliation_number' => 'RAP-BKO-TEST-DEPOT-001',
            'statement_date' => now()->toDateString(),
            'statement_reference' => 'BANK-DEPOSIT-001',
            'statement_balance' => 27000,
            'matched_total' => 27000,
            'book_balance' => 27000,
            'difference' => 0,
            'payments_count' => 1,
            'status' => 'balanced',
            'notes' => 'Rapprochement depot agence',
            'created_by' => $user->id,
        ]);

        TreasuryReconciliationPayment::query()->create([
            'treasury_reconciliation_id' => $reconciliation->id,
            'payment_id' => $reconciledDeposit->id,
        ]);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('payments.index', [
                'aging_state' => 'deposit_bank_age_2_plus',
            ]))
            ->assertOk()
            ->assertSee('Pilotage versements agence')
            ->assertSee('Versement banque 2+ jours')
            ->assertSee($staleDeposit->payment_number)
            ->assertSee('Pret a rapprocher')
            ->assertDontSee('ENC-DEPOT-RECENT-001')
            ->assertDontSee('ENC-DEPOT-OLD-002');
    }

    public function test_payments_page_can_filter_internal_transfer_deposits_missing_reference(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $bankAccount = CashAccount::query()->where('company_id', $user->company_id)->where('name', 'Banque BDM')->firstOrFail();

        $missingReferenceDeposit = Payment::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'cash_account_id' => $bankAccount->id,
            'partner_id' => null,
            'payment_number' => 'ENC-DEPOT-MISS-001',
            'direction' => 'in',
            'payment_type' => 'internal_transfer',
            'payment_date' => now()->subDays(2)->toDateString(),
            'amount' => 38000,
            'method' => 'bank_transfer',
            'reference' => null,
            'notes' => 'Depot sans bordereau exploitable',
            'created_by' => $user->id,
        ]);

        Payment::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'cash_account_id' => $bankAccount->id,
            'partner_id' => null,
            'payment_number' => 'ENC-DEPOT-MISS-002',
            'direction' => 'in',
            'payment_type' => 'internal_transfer',
            'payment_date' => now()->subDays(2)->toDateString(),
            'amount' => 22000,
            'method' => 'bank_transfer',
            'reference' => 'BORD-DEPOT-MISS-002',
            'notes' => 'Depot avec bordereau',
            'created_by' => $user->id,
        ]);

        $depositWithAttachment = Payment::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'cash_account_id' => $bankAccount->id,
            'partner_id' => null,
            'payment_number' => 'ENC-DEPOT-MISS-004',
            'direction' => 'in',
            'payment_type' => 'internal_transfer',
            'payment_date' => now()->subDays(3)->toDateString(),
            'amount' => 29000,
            'method' => 'bank_transfer',
            'reference' => null,
            'notes' => 'Depot avec photo du bordereau',
            'created_by' => $user->id,
        ]);

        Attachment::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'attachable_type' => Payment::class,
            'attachable_id' => $depositWithAttachment->id,
            'disk' => 'public',
            'path' => 'tests/bordereau-photo.jpg',
            'original_name' => 'bordereau-photo.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 2048,
            'created_by' => $user->id,
        ]);

        $reconciledMissingReferenceDeposit = Payment::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'cash_account_id' => $bankAccount->id,
            'partner_id' => null,
            'payment_number' => 'ENC-DEPOT-MISS-003',
            'direction' => 'in',
            'payment_type' => 'internal_transfer',
            'payment_date' => now()->subDays(4)->toDateString(),
            'amount' => 17000,
            'method' => 'bank_transfer',
            'reference' => null,
            'notes' => 'Depot rapproche sans bordereau',
            'created_by' => $user->id,
        ]);

        $reconciliation = TreasuryReconciliation::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'cash_account_id' => $bankAccount->id,
            'reconciliation_number' => 'RAP-BKO-TEST-DEPOT-REF-001',
            'statement_date' => now()->toDateString(),
            'statement_reference' => 'BANK-DEPOSIT-REF-001',
            'statement_balance' => 17000,
            'matched_total' => 17000,
            'book_balance' => 17000,
            'difference' => 0,
            'payments_count' => 1,
            'status' => 'balanced',
            'notes' => 'Rapprochement depot sans reference',
            'created_by' => $user->id,
        ]);

        TreasuryReconciliationPayment::query()->create([
            'treasury_reconciliation_id' => $reconciliation->id,
            'payment_id' => $reconciledMissingReferenceDeposit->id,
        ]);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('payments.index', [
                'deposit_missing_reference' => 1,
            ]))
            ->assertOk()
            ->assertSee('Depot sans bordereau')
            ->assertSee('Voir bordereaux manquants')
            ->assertSee($missingReferenceDeposit->payment_number)
            ->assertSee('Bordereau manquant')
            ->assertDontSee('ENC-DEPOT-MISS-002')
            ->assertDontSee('ENC-DEPOT-MISS-003')
            ->assertDontSee('ENC-DEPOT-MISS-004');
    }

    public function test_payments_page_can_filter_documented_internal_transfer_deposits(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $bankAccount = CashAccount::query()->where('company_id', $user->company_id)->where('name', 'Banque BDM')->firstOrFail();

        $depositWithReference = Payment::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'cash_account_id' => $bankAccount->id,
            'partner_id' => null,
            'payment_number' => 'ENC-DEPOT-DOC-001',
            'direction' => 'in',
            'payment_type' => 'internal_transfer',
            'payment_date' => now()->subDays(3)->toDateString(),
            'amount' => 41000,
            'method' => 'bank_transfer',
            'reference' => 'BORD-DEPOT-DOC-001',
            'notes' => 'Depot documente par reference',
            'created_by' => $user->id,
        ]);

        $depositWithAttachment = Payment::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'cash_account_id' => $bankAccount->id,
            'partner_id' => null,
            'payment_number' => 'ENC-DEPOT-DOC-002',
            'direction' => 'in',
            'payment_type' => 'internal_transfer',
            'payment_date' => now()->subDays(2)->toDateString(),
            'amount' => 33000,
            'method' => 'bank_transfer',
            'reference' => null,
            'notes' => 'Depot documente par justificatif',
            'created_by' => $user->id,
        ]);

        Attachment::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'attachable_type' => Payment::class,
            'attachable_id' => $depositWithAttachment->id,
            'disk' => 'public',
            'path' => 'tests/depot-documente.pdf',
            'original_name' => 'depot-documente.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 4096,
            'created_by' => $user->id,
        ]);

        Payment::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'cash_account_id' => $bankAccount->id,
            'partner_id' => null,
            'payment_number' => 'ENC-DEPOT-DOC-003',
            'direction' => 'in',
            'payment_type' => 'internal_transfer',
            'payment_date' => now()->subDays(2)->toDateString(),
            'amount' => 18000,
            'method' => 'bank_transfer',
            'reference' => null,
            'notes' => 'Depot non documente',
            'created_by' => $user->id,
        ]);

        $reconciledDocumentedDeposit = Payment::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'cash_account_id' => $bankAccount->id,
            'partner_id' => null,
            'payment_number' => 'ENC-DEPOT-DOC-004',
            'direction' => 'in',
            'payment_type' => 'internal_transfer',
            'payment_date' => now()->subDays(4)->toDateString(),
            'amount' => 22000,
            'method' => 'bank_transfer',
            'reference' => 'BORD-DEPOT-DOC-004',
            'notes' => 'Depot documente rapproche',
            'created_by' => $user->id,
        ]);

        $reconciliation = TreasuryReconciliation::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'cash_account_id' => $bankAccount->id,
            'reconciliation_number' => 'RAP-BKO-TEST-DEPOT-DOC-001',
            'statement_date' => now()->toDateString(),
            'statement_reference' => 'BANK-DEPOSIT-DOC-001',
            'statement_balance' => 22000,
            'matched_total' => 22000,
            'book_balance' => 22000,
            'difference' => 0,
            'payments_count' => 1,
            'status' => 'balanced',
            'notes' => 'Rapprochement depot documente',
            'created_by' => $user->id,
        ]);

        TreasuryReconciliationPayment::query()->create([
            'treasury_reconciliation_id' => $reconciliation->id,
            'payment_id' => $reconciledDocumentedDeposit->id,
        ]);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('payments.index', [
                'deposit_documented' => 1,
            ]))
            ->assertOk()
            ->assertSee('Depot documente')
            ->assertSee('Voir documentes')
            ->assertSee($depositWithReference->payment_number)
            ->assertSee($depositWithAttachment->payment_number)
            ->assertSee('Pret a rapprocher')
            ->assertSee('Bordereau joint')
            ->assertSee('Reference depot')
            ->assertDontSee('ENC-DEPOT-DOC-003')
            ->assertDontSee('ENC-DEPOT-DOC-004');
    }

    public function test_payments_page_can_render_kanban_view(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $waveAccount = CashAccount::query()->where('company_id', $user->company_id)->where('name', 'Wave')->firstOrFail();

        $payment = Payment::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'cash_account_id' => $waveAccount->id,
            'partner_id' => null,
            'payment_number' => 'ENC-KANBAN-001',
            'direction' => 'in',
            'payment_type' => 'customer_receipt',
            'payment_date' => now()->toDateString(),
            'amount' => 32500,
            'method' => 'wave',
            'reference' => null,
            'notes' => 'Paiement test vue kanban',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('payments.index', [
                'search' => $payment->payment_number,
                'view' => 'kanban',
            ]))
            ->assertOk()
            ->assertSee($payment->payment_number)
            ->assertSee('Voir le paiement');
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

    public function test_stock_page_can_filter_tracked_products_with_zero_saleable_stock(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $category = ProductCategory::query()->where('company_id', $user->company_id)->where('name', 'Epicerie')->firstOrFail();
        $warehouse = Warehouse::query()
            ->where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->where('is_default', true)
            ->firstOrFail();

        $blockedTrackedProduct = Product::query()->create([
            'company_id' => $user->company_id,
            'category_id' => $category->id,
            'sku' => 'PRD-TRK-ZERO-01',
            'barcode' => '770000000503',
            'name' => 'Ceftriaxone injectable',
            'unit' => 'boite',
            'type' => 'stockable',
            'tracking_type' => 'lot',
            'sale_price' => 5800,
            'purchase_price' => 4100,
            'min_stock' => 2,
            'description' => 'Produit trace sans lot vendable',
            'is_active' => true,
            'sale_ok' => true,
            'purchase_ok' => true,
        ]);

        ProductLot::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $blockedTrackedProduct->id,
            'tracking_type' => 'lot',
            'lot_number' => 'CEF-LOT-EXP',
            'expires_at' => now()->subDay()->toDateString(),
            'received_at' => now()->subDays(30)->toDateString(),
            'unit_cost' => 4100,
            'quantity_received' => 5,
            'quantity_available' => 5,
        ]);

        $healthyTrackedProduct = Product::query()->create([
            'company_id' => $user->company_id,
            'category_id' => $category->id,
            'sku' => 'PRD-TRK-ZERO-02',
            'barcode' => '770000000504',
            'name' => 'Paracetamol sirop',
            'unit' => 'flacon',
            'type' => 'stockable',
            'tracking_type' => 'lot',
            'sale_price' => 2600,
            'purchase_price' => 1700,
            'min_stock' => 2,
            'description' => 'Produit trace vendable',
            'is_active' => true,
            'sale_ok' => true,
            'purchase_ok' => true,
        ]);

        ProductLot::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $healthyTrackedProduct->id,
            'tracking_type' => 'lot',
            'lot_number' => 'PARA-LOT-OK',
            'expires_at' => now()->addDays(60)->toDateString(),
            'received_at' => now()->subDays(3)->toDateString(),
            'unit_cost' => 1700,
            'quantity_received' => 7,
            'quantity_available' => 7,
        ]);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('stock.index', [
                'tracking_type' => 'tracked',
                'saleability_state' => 'zero',
            ]))
            ->assertOk()
            ->assertSee('Ceftriaxone injectable')
            ->assertSee('Stock non vendable')
            ->assertDontSee('Paracetamol sirop');
    }

    public function test_stock_page_can_filter_saleable_critical_products_without_including_zero_stockouts(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $category = ProductCategory::query()->where('company_id', $user->company_id)->where('name', 'Epicerie')->firstOrFail();
        $warehouse = Warehouse::query()
            ->where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->where('is_default', true)
            ->firstOrFail();

        $criticalProduct = Product::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'category_id' => $category->id,
            'sku' => 'PRD-CRIT-SALE-01',
            'name' => 'Biscuit mil 200g',
            'unit' => 'sachet',
            'type' => 'stockable',
            'tracking_type' => 'none',
            'sale_price' => 450,
            'purchase_price' => 250,
            'min_stock' => 2,
            'is_active' => true,
            'sale_ok' => true,
            'purchase_ok' => true,
        ]);

        StockMovement::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $criticalProduct->id,
            'movement_type' => 'opening',
            'quantity_in' => 3,
            'quantity_out' => 0,
            'unit_cost' => 250,
            'reason' => 'Stock initial critique',
            'movement_date' => now()->subDays(3),
            'created_by' => $user->id,
        ]);

        StockMovement::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $criticalProduct->id,
            'movement_type' => 'sale',
            'quantity_in' => 0,
            'quantity_out' => 2,
            'unit_cost' => 250,
            'reason' => 'Sortie critique',
            'movement_date' => now()->subDay(),
            'created_by' => $user->id,
        ]);

        $zeroProduct = Product::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'category_id' => $category->id,
            'sku' => 'PRD-CRIT-SALE-02',
            'name' => 'Eau 50cl rupture',
            'unit' => 'bouteille',
            'type' => 'stockable',
            'tracking_type' => 'none',
            'sale_price' => 250,
            'purchase_price' => 140,
            'min_stock' => 4,
            'is_active' => true,
            'sale_ok' => true,
            'purchase_ok' => true,
        ]);

        StockMovement::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $zeroProduct->id,
            'movement_type' => 'opening',
            'quantity_in' => 4,
            'quantity_out' => 0,
            'unit_cost' => 140,
            'reason' => 'Stock initial rupture',
            'movement_date' => now()->subDays(3),
            'created_by' => $user->id,
        ]);

        StockMovement::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $zeroProduct->id,
            'movement_type' => 'sale',
            'quantity_in' => 0,
            'quantity_out' => 4,
            'unit_cost' => 140,
            'reason' => 'Sortie rupture',
            'movement_date' => now()->subDay(),
            'created_by' => $user->id,
        ]);

        $healthyProduct = Product::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'category_id' => $category->id,
            'sku' => 'PRD-CRIT-SALE-03',
            'name' => 'The gingembre',
            'unit' => 'boite',
            'type' => 'stockable',
            'tracking_type' => 'none',
            'sale_price' => 1200,
            'purchase_price' => 820,
            'min_stock' => 2,
            'is_active' => true,
            'sale_ok' => true,
            'purchase_ok' => true,
        ]);

        StockMovement::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $healthyProduct->id,
            'movement_type' => 'opening',
            'quantity_in' => 8,
            'quantity_out' => 0,
            'unit_cost' => 820,
            'reason' => 'Stock initial sain',
            'movement_date' => now()->subDays(2),
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('stock.index', [
                'saleability_state' => 'critical',
            ]))
            ->assertOk()
            ->assertSee('Biscuit mil 200g')
            ->assertSee('Vendable critique')
            ->assertDontSee('Eau 50cl rupture')
            ->assertDontSee('The gingembre');
    }

    public function test_orders_page_can_filter_by_wholesale_coverage_and_overdue_reliquats(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $user->company_id)->firstOrFail();
        $supplier = Partner::query()->suppliers()->where('company_id', $user->company_id)->firstOrFail();
        $warehouse = Warehouse::query()
            ->where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->where('is_default', true)
            ->firstOrFail();
        $category = ProductCategory::query()->where('company_id', $user->company_id)->where('name', 'Epicerie')->firstOrFail();

        $atRiskProduct = Product::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'category_id' => $category->id,
            'sku' => 'ORD-FLT-WHO-001',
            'name' => 'Riz import 50kg risque',
            'unit' => 'sac',
            'type' => 'stockable',
            'tracking_type' => 'none',
            'sale_price' => 21000,
            'purchase_price' => 16800,
            'min_stock' => 2,
            'is_active' => true,
            'sale_ok' => true,
            'purchase_ok' => true,
        ]);

        StockMovement::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $atRiskProduct->id,
            'movement_type' => 'opening',
            'quantity_in' => 2,
            'quantity_out' => 0,
            'unit_cost' => 16800,
            'reason' => 'Stock filtre grossiste risque',
            'movement_date' => now()->subDays(5),
            'created_by' => $user->id,
        ]);

        $atRiskOrder = SalesOrder::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'order_number' => 'ORDER-WHO-RISK-001',
            'order_date' => now()->subDays(2)->toDateString(),
            'requested_delivery_date' => now()->addDays(2)->toDateString(),
            'commitment_date' => now()->addDay()->toDateString(),
            'status' => 'confirmed',
            'subtotal' => 105000,
            'total' => 105000,
            'notes' => 'Commande grossiste a risque',
            'confirmed_at' => now()->subDays(2),
            'created_by' => $user->id,
        ]);

        SalesOrderItem::query()->create([
            'sales_order_id' => $atRiskOrder->id,
            'product_id' => $atRiskProduct->id,
            'description' => 'Ligne risque grossiste',
            'qty' => 5,
            'delivered_qty' => 0,
            'unit_price' => 21000,
            'line_total' => 105000,
        ]);

        $incomingProduct = Product::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'category_id' => $category->id,
            'sku' => 'ORD-FLT-WHO-002',
            'name' => 'Huile 20L appro',
            'unit' => 'bidon',
            'type' => 'stockable',
            'tracking_type' => 'none',
            'sale_price' => 29500,
            'purchase_price' => 24000,
            'min_stock' => 1,
            'is_active' => true,
            'sale_ok' => true,
            'purchase_ok' => true,
        ]);

        StockMovement::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $incomingProduct->id,
            'movement_type' => 'opening',
            'quantity_in' => 1,
            'quantity_out' => 0,
            'unit_cost' => 24000,
            'reason' => 'Stock filtre grossiste appro',
            'movement_date' => now()->subDays(5),
            'created_by' => $user->id,
        ]);

        $incomingOrder = SalesOrder::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'order_number' => 'ORDER-WHO-INCOMING-001',
            'order_date' => now()->subDays(2)->toDateString(),
            'requested_delivery_date' => now()->addDays(3)->toDateString(),
            'commitment_date' => now()->addDays(2)->toDateString(),
            'status' => 'confirmed',
            'subtotal' => 88500,
            'total' => 88500,
            'notes' => 'Commande grossiste couverte par appro',
            'confirmed_at' => now()->subDays(2),
            'created_by' => $user->id,
        ]);

        SalesOrderItem::query()->create([
            'sales_order_id' => $incomingOrder->id,
            'product_id' => $incomingProduct->id,
            'description' => 'Ligne couverte par appro',
            'qty' => 3,
            'delivered_qty' => 0,
            'unit_price' => 29500,
            'line_total' => 88500,
        ]);

        $incomingPurchaseOrder = PurchaseOrder::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'order_number' => 'PO-WHO-INCOMING-001',
            'order_date' => now()->subDay()->toDateString(),
            'expected_receipt_date' => now()->addDays(2)->toDateString(),
            'status' => 'confirmed',
            'subtotal' => 96000,
            'total' => 96000,
            'confirmed_at' => now()->subDay(),
            'created_by' => $user->id,
        ]);

        PurchaseOrderItem::query()->create([
            'purchase_order_id' => $incomingPurchaseOrder->id,
            'product_id' => $incomingProduct->id,
            'description' => 'Appro couverture grossiste',
            'qty' => 4,
            'received_qty' => 0,
            'unit_cost' => 24000,
            'line_total' => 96000,
        ]);

        $overdueProduct = Product::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'category_id' => $category->id,
            'sku' => 'ORD-FLT-WHO-003',
            'name' => 'Sucre reliquat',
            'unit' => 'sac',
            'type' => 'stockable',
            'tracking_type' => 'none',
            'sale_price' => 18500,
            'purchase_price' => 14900,
            'min_stock' => 1,
            'is_active' => true,
            'sale_ok' => true,
            'purchase_ok' => true,
        ]);

        StockMovement::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $overdueProduct->id,
            'movement_type' => 'opening',
            'quantity_in' => 4,
            'quantity_out' => 0,
            'unit_cost' => 14900,
            'reason' => 'Stock filtre grossiste reliquat',
            'movement_date' => now()->subDays(6),
            'created_by' => $user->id,
        ]);

        $overdueOrder = SalesOrder::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'order_number' => 'ORDER-WHO-OVERDUE-001',
            'order_date' => now()->subDays(5)->toDateString(),
            'requested_delivery_date' => now()->subDay()->toDateString(),
            'commitment_date' => now()->subDay()->toDateString(),
            'status' => 'partial_delivered',
            'subtotal' => 92500,
            'total' => 92500,
            'notes' => 'Commande grossiste en retard',
            'confirmed_at' => now()->subDays(5),
            'created_by' => $user->id,
        ]);

        SalesOrderItem::query()->create([
            'sales_order_id' => $overdueOrder->id,
            'product_id' => $overdueProduct->id,
            'description' => 'Ligne reliquat en retard',
            'qty' => 5,
            'delivered_qty' => 2,
            'unit_price' => 18500,
            'line_total' => 92500,
        ]);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('orders.index', [
                'coverage_state' => 'at_risk',
            ]))
            ->assertOk()
            ->assertSee('Commande a risque')
            ->assertSee($atRiskOrder->order_number)
            ->assertDontSee($incomingOrder->order_number)
            ->assertDontSee($overdueOrder->order_number);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('orders.index', [
                'coverage_state' => 'incoming',
            ]))
            ->assertOk()
            ->assertSee('Couvert par appro')
            ->assertSee($incomingOrder->order_number)
            ->assertDontSee($atRiskOrder->order_number);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('orders.index', [
                'delivery_focus' => 'overdue',
            ]))
            ->assertOk()
            ->assertSee('Engagement depasse depuis le')
            ->assertSee($overdueOrder->order_number)
            ->assertDontSee($incomingOrder->order_number);
    }

    public function test_sales_page_filters_by_search_workflow_payment_and_branch(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $customerId = Partner::query()->customers()->where('company_id', $user->company_id)->value('id');
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

    public function test_quotes_and_orders_pages_can_render_kanban_views(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $user->company_id)->firstOrFail();
        $product = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-0001')->firstOrFail();
        $warehouse = Warehouse::query()
            ->where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->orderByDesc('is_default')
            ->firstOrFail();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('quotes.store'), [
                'customer_id' => $customer->id,
                'quote_date' => now()->toDateString(),
                'notes' => 'KANBAN-QUOTE-VIEW',
                'items' => [[
                    'product_id' => $product->id,
                    'description' => 'Devis test vue kanban',
                    'qty' => 1,
                    'unit_price' => 600,
                ]],
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('orders.store'), [
                'customer_id' => $customer->id,
                'warehouse_id' => $warehouse->id,
                'order_date' => now()->toDateString(),
                'notes' => 'KANBAN-ORDER-VIEW',
                'items' => [[
                    'product_id' => $product->id,
                    'description' => 'Commande test vue kanban',
                    'qty' => 1,
                    'unit_price' => 600,
                ]],
            ])
            ->assertRedirect();

        $quote = SalesQuote::query()
            ->where('company_id', $user->company_id)
            ->where('notes', 'KANBAN-QUOTE-VIEW')
            ->firstOrFail();
        $order = SalesOrder::query()
            ->where('company_id', $user->company_id)
            ->where('notes', 'KANBAN-ORDER-VIEW')
            ->firstOrFail();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('quotes.index', [
                'search' => $quote->quote_number,
                'view' => 'kanban',
            ]))
            ->assertOk()
            ->assertSee($quote->quote_number)
            ->assertSee('Voir le devis');

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('orders.index', [
                'search' => $order->order_number,
                'view' => 'kanban',
            ]))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Voir la commande');
    }

    public function test_purchases_page_filters_by_search_workflow_payment_and_branch(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $supplierId = Partner::query()->suppliers()->where('company_id', $user->company_id)->value('id');
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
