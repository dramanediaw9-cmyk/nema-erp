<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductCategory;
use App\Modules\Core\Audit\Models\ActivityLog;
use App\Modules\Core\Company\Models\Setting;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Inventory\Models\ProductLot;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Purchases\Models\PurchaseBill;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Treasury\Models\CashAccount;
use App\Modules\Treasury\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWholesaleCoverageScenario;
use Tests\TestCase;

class DocumentInsightsTest extends TestCase
{
    use BuildsWholesaleCoverageScenario;
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_dashboard_shows_operational_watchlist(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Suivi operationnel')
            ->assertSee('Ventes en attente')
            ->assertSee('Stock a surveiller');
    }

    public function test_dashboard_surfaces_mobile_money_reconciliation_risk(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $waveAccount = CashAccount::query()->where('company_id', $user->company_id)->where('name', 'Wave')->firstOrFail();

        Payment::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'cash_account_id' => $waveAccount->id,
            'partner_id' => null,
            'payment_number' => 'ENC-DASH-MM-001',
            'direction' => 'in',
            'payment_type' => 'customer_receipt',
            'payment_date' => now()->toDateString(),
            'amount' => 25000,
            'method' => 'wave',
            'reference' => null,
            'notes' => 'Dashboard mobile money test',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Mobile money a rapprocher')
            ->assertSee('25 000 XOF encore ouverts')
            ->assertSee('1 reference(s) manquante(s)')
            ->assertSee('Rapprocher les wallets mobiles');
    }

    public function test_dashboard_surfaces_pending_internal_transfer_deposits(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $bankAccount = CashAccount::query()->where('company_id', $user->company_id)->where('name', 'Banque BDM')->firstOrFail();

        Payment::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'cash_account_id' => $bankAccount->id,
            'partner_id' => null,
            'payment_number' => 'ENC-DASH-DEPOT-001',
            'direction' => 'in',
            'payment_type' => 'internal_transfer',
            'payment_date' => now()->subDays(4)->toDateString(),
            'amount' => 45000,
            'method' => 'bank_transfer',
            'reference' => null,
            'notes' => 'Dashboard depot banque a confirmer',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Versements agence a rapprocher')
            ->assertSee('45 000 XOF en attente de confirmation bancaire')
            ->assertSee('Versements sans bordereau')
            ->assertSee('Confirmer les depots agence');
    }

    public function test_dashboard_surfaces_documented_internal_transfer_deposits_ready_for_reconciliation(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $bankAccount = CashAccount::query()->where('company_id', $user->company_id)->where('name', 'Banque BDM')->firstOrFail();

        Payment::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'cash_account_id' => $bankAccount->id,
            'partner_id' => null,
            'payment_number' => 'ENC-DASH-DEPOT-READY-001',
            'direction' => 'in',
            'payment_type' => 'internal_transfer',
            'payment_date' => now()->subDays(3)->toDateString(),
            'amount' => 61000,
            'method' => 'bank_transfer',
            'reference' => 'BORD-DASH-READY-001',
            'notes' => 'Dashboard depot deja documente',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Versements documentes a rapprocher')
            ->assertSee('61 000 XOF disposent deja d une preuve de depot exploitable')
            ->assertSee('Rapprocher les depots documentes');
    }

    public function test_dashboard_surfaces_pharmacy_sector_signals(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $warehouse = Warehouse::query()
            ->where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->where('is_default', true)
            ->firstOrFail();
        $category = ProductCategory::query()->firstOrCreate([
            'company_id' => $user->company_id,
            'name' => 'Produits sensibles',
        ], [
            'tenant_id' => $user->tenant_id,
            'description' => 'Categorie pharmacie test',
            'is_active' => true,
        ]);

        Setting::query()->updateOrCreate(
            ['company_id' => $user->company_id, 'key' => 'sector_profile'],
            ['value' => ['profile' => 'pharmacy_parapharmacy']]
        );

        $expiringProduct = Product::query()->create([
            'company_id' => $user->company_id,
            'category_id' => $category->id,
            'sku' => 'PHA-DASH-001',
            'name' => 'Amoxicilline 500mg',
            'unit' => 'boite',
            'type' => 'stockable',
            'tracking_type' => 'lot',
            'sale_price' => 2500,
            'purchase_price' => 1800,
            'min_stock' => 2,
            'is_active' => true,
            'sale_ok' => true,
            'purchase_ok' => true,
        ]);

        ProductLot::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $expiringProduct->id,
            'tracking_type' => 'lot',
            'lot_number' => 'PHA-EXP-001',
            'expires_at' => now()->addDays(10)->toDateString(),
            'received_at' => now()->subDays(5)->toDateString(),
            'unit_cost' => 1800,
            'quantity_received' => 6,
            'quantity_available' => 6,
        ]);

        $expiredProduct = Product::query()->create([
            'company_id' => $user->company_id,
            'category_id' => $category->id,
            'sku' => 'PHA-DASH-002',
            'name' => 'Serum injectable',
            'unit' => 'flacon',
            'type' => 'stockable',
            'tracking_type' => 'lot',
            'sale_price' => 3200,
            'purchase_price' => 2200,
            'min_stock' => 1,
            'is_active' => true,
            'sale_ok' => true,
            'purchase_ok' => true,
        ]);

        ProductLot::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $expiredProduct->id,
            'tracking_type' => 'lot',
            'lot_number' => 'PHA-OLD-001',
            'expires_at' => now()->subDay()->toDateString(),
            'received_at' => now()->subDays(20)->toDateString(),
            'unit_cost' => 2200,
            'quantity_received' => 4,
            'quantity_available' => 4,
        ]);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Signaux terrain du secteur')
            ->assertSee('Lots proches de peremption')
            ->assertSee('Lots expires encore en stock')
            ->assertSee('Produits traces sans stock vendable')
            ->assertSee('1 lot(s) sur 1 produit(s) expirent sous 30 jours')
            ->assertSee('1 lot(s) sur 1 produit(s) sont deja expires mais encore disponibles')
            ->assertSee('1 reference(s) n ont plus aucun lot non expire disponible');
    }

    public function test_dashboard_surfaces_food_store_sector_signals(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $warehouse = Warehouse::query()
            ->where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->where('is_default', true)
            ->firstOrFail();
        $category = ProductCategory::query()->firstOrCreate([
            'company_id' => $user->company_id,
            'name' => 'Produits frais',
        ], [
            'tenant_id' => $user->tenant_id,
            'description' => 'Categorie food test',
            'is_active' => true,
        ]);

        Setting::query()->updateOrCreate(
            ['company_id' => $user->company_id, 'key' => 'sector_profile'],
            ['value' => ['profile' => 'food_store']]
        );

        $shortDatedProduct = Product::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'category_id' => $category->id,
            'sku' => 'FOOD-DASH-001',
            'name' => 'Yaourt fraise 500g',
            'unit' => 'pot',
            'type' => 'stockable',
            'tracking_type' => 'lot',
            'sale_price' => 950,
            'purchase_price' => 620,
            'min_stock' => 4,
            'is_active' => true,
            'sale_ok' => true,
            'purchase_ok' => true,
        ]);

        ProductLot::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $shortDatedProduct->id,
            'tracking_type' => 'lot',
            'lot_number' => 'FOOD-SHORT-001',
            'expires_at' => now()->addDays(3)->toDateString(),
            'received_at' => now()->subDays(2)->toDateString(),
            'unit_cost' => 620,
            'quantity_received' => 6,
            'quantity_available' => 6,
        ]);

        $stockoutProduct = Product::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'category_id' => $category->id,
            'sku' => 'FOOD-DASH-002',
            'name' => 'Jus gingembre 33cl',
            'unit' => 'bouteille',
            'type' => 'stockable',
            'tracking_type' => 'none',
            'sale_price' => 700,
            'purchase_price' => 420,
            'min_stock' => 3,
            'is_active' => true,
            'sale_ok' => true,
            'purchase_ok' => true,
        ]);

        StockMovement::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $stockoutProduct->id,
            'movement_type' => 'opening',
            'quantity_in' => 5,
            'quantity_out' => 0,
            'unit_cost' => 420,
            'reason' => 'Stock initial food dashboard',
            'movement_date' => now()->subDays(4),
            'created_by' => $user->id,
        ]);

        StockMovement::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $stockoutProduct->id,
            'movement_type' => 'sale',
            'quantity_in' => 0,
            'quantity_out' => 5,
            'unit_cost' => 420,
            'reason' => 'Sortie food dashboard',
            'movement_date' => now()->subDay(),
            'created_by' => $user->id,
        ]);

        $criticalProduct = Product::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'category_id' => $category->id,
            'sku' => 'FOOD-DASH-003',
            'name' => 'Biscuit sesame',
            'unit' => 'sachet',
            'type' => 'stockable',
            'tracking_type' => 'none',
            'sale_price' => 500,
            'purchase_price' => 280,
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
            'unit_cost' => 280,
            'reason' => 'Stock initial critique food dashboard',
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
            'unit_cost' => 280,
            'reason' => 'Sortie critique food dashboard',
            'movement_date' => now()->subDay(),
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Lots courts a ecouler')
            ->assertSee('Ruptures rayon vendables')
            ->assertSee('Rayon vendable critique')
            ->assertSee('1 lot(s) sur 1 produit(s) expirent sous 7 jours')
            ->assertSee('1 reference(s) ont deja tourne en stock mais n ont plus rien de vendable au comptoir')
            ->assertSee('1 reference(s) restent vendables mais sont deja au seuil mini rayon');
    }

    public function test_dashboard_surfaces_wholesale_distribution_sector_signals(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $this->seedWholesaleCoverageScenario($user, 'DASHWHO');

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Signaux terrain du secteur')
            ->assertSee('Commandes a risque stock')
            ->assertSee('Commandes couvertes par appro')
            ->assertSee('Engagements en retard')
            ->assertSee('1 commande(s) couvrent mal 1 ligne(s)')
            ->assertSee('1 commande(s) dependent deja d achats confirmes sur 1 ligne(s)')
            ->assertSee('1 commande(s) gardent un reliquat ouvert pour 3,000 unite(s) apres la date promise');
    }

    public function test_document_detail_pages_show_related_operational_and_accounting_sections(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $invoice = SalesInvoice::query()->where('company_id', $user->company_id)->where('notes', 'Facture de demonstration initiale')->firstOrFail();
        $bill = PurchaseBill::query()->where('company_id', $user->company_id)->where('notes', 'Facture fournisseur de demonstration')->firstOrFail();
        $expense = Expense::query()->where('company_id', $user->company_id)->where('description', 'Achat de carburant pour livraison Bamako')->firstOrFail();

        ActivityLog::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'user_id' => $user->id,
            'action' => 'sales.approve',
            'description' => 'Validation facture test',
            'subject_type' => $invoice->getMorphClass(),
            'subject_id' => $invoice->id,
            'properties' => ['source' => 'test'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ]);

        ActivityLog::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'user_id' => $user->id,
            'action' => 'purchases.approve',
            'description' => 'Validation achat test',
            'subject_type' => $bill->getMorphClass(),
            'subject_id' => $bill->id,
            'properties' => ['source' => 'test'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ]);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('sales.show', $invoice))
            ->assertOk()
            ->assertSee('Historique des actions')
            ->assertSee('Validation facture test')
            ->assertSee('Mouvements de stock lies')
            ->assertSee('Ecritures comptables liees');

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('purchases.show', $bill))
            ->assertOk()
            ->assertSee('Historique des actions')
            ->assertSee('Validation achat test')
            ->assertSee('Mouvements de stock lies')
            ->assertSee('Ecritures comptables liees');

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('expenses.show', $expense))
            ->assertOk()
            ->assertSee('Ecritures comptables liees')
            ->assertSee('Informations generales');
    }

    public function test_journal_entries_support_source_filter_and_back_navigation_to_document(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $invoice = SalesInvoice::query()->where('company_id', $user->company_id)->where('notes', 'Facture de demonstration initiale')->firstOrFail();
        $bill = PurchaseBill::query()->where('company_id', $user->company_id)->where('notes', 'Facture fournisseur de demonstration')->firstOrFail();
        $entry = JournalEntry::query()
            ->where('company_id', $user->company_id)
            ->where('source_type', SalesInvoice::class)
            ->where('source_id', $invoice->id)
            ->firstOrFail();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('accounting.journal-entries.index', [
                'source_type' => 'sales',
                'search' => $invoice->invoice_number,
            ]))
            ->assertOk()
            ->assertSee($invoice->invoice_number)
            ->assertDontSee($bill->bill_number);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('accounting.journal-entries.show', $entry))
            ->assertOk()
            ->assertSee('Ouvrir le document source')
            ->assertSee($invoice->invoice_number);
    }

    private function workspaceSession(User $user): array
    {
        return [
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ];
    }
}
