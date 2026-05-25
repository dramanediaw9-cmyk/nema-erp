<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductCategory;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Collaboration\Models\Attachment;
use App\Modules\Core\Company\Models\Setting;
use App\Modules\Core\Notifications\Models\InternalNotification;
use App\Modules\Inventory\Models\ProductLot;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Partners\Models\Partner;
use App\Modules\Treasury\Models\CashAccount;
use App\Modules\Treasury\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWholesaleCoverageScenario;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use BuildsWholesaleCoverageScenario;
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_dashboard_generates_internal_notifications_for_pending_documents(): void
    {
        $operator = User::query()->where('email', 'ops@nema-erp.test')->firstOrFail();
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $supplier = Partner::query()->suppliers()->where('company_id', $operator->company_id)->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $operator->company_id)->firstOrFail();
        $product = Product::query()->where('company_id', $operator->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $this->actingAs($operator)
            ->withSession([
                'current_company_id' => $operator->company_id,
                'current_branch_id' => $operator->branch_id,
            ])
            ->post(route('sales.store'), [
                'customer_id' => $customer->id,
                'invoice_date' => now()->toDateString(),
                'notes' => 'NOTIF-SALE-PENDING',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'description' => 'Vente pour notification',
                        'qty' => 1,
                        'unit_price' => 500,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->actingAs($operator)
            ->withSession([
                'current_company_id' => $operator->company_id,
                'current_branch_id' => $operator->branch_id,
            ])
            ->post(route('purchases.store'), [
                'supplier_id' => $supplier->id,
                'bill_date' => now()->toDateString(),
                'due_date' => now()->addDays(7)->toDateString(),
                'notes' => 'NOTIF-PURCHASE-PENDING',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'description' => 'Achat pour notification',
                        'qty' => 3,
                        'unit_cost' => 250,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('sales_invoices', [
            'company_id' => $operator->company_id,
            'notes' => 'NOTIF-SALE-PENDING',
            'status' => 'pending_approval',
        ]);

        $this->assertDatabaseHas('purchase_bills', [
            'company_id' => $operator->company_id,
            'notes' => 'NOTIF-PURCHASE-PENDING',
            'status' => 'pending_approval',
        ]);

        $this->actingAs($manager)
            ->withSession([
                'current_company_id' => $manager->company_id,
                'current_branch_id' => $manager->branch_id,
            ])
            ->get(route('dashboard'))
            ->assertOk();

        $this->assertDatabaseHas('internal_notifications', [
            'company_id' => $manager->company_id,
            'code' => 'pending-sales-approval',
            'resolved_at' => null,
        ]);

        $this->assertDatabaseHas('internal_notifications', [
            'company_id' => $manager->company_id,
            'code' => 'pending-purchases-approval',
            'resolved_at' => null,
        ]);
    }

    public function test_dashboard_generates_internal_notification_for_unreconciled_mobile_money(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $waveAccount = CashAccount::query()->where('company_id', $manager->company_id)->where('name', 'Wave')->firstOrFail();

        Payment::query()->create([
            'tenant_id' => $manager->tenant_id,
            'company_id' => $manager->company_id,
            'branch_id' => $manager->branch_id,
            'cash_account_id' => $waveAccount->id,
            'partner_id' => null,
            'payment_number' => 'ENC-NOTIF-MM-001',
            'direction' => 'in',
            'payment_type' => 'customer_receipt',
            'payment_date' => now()->toDateString(),
            'amount' => 32000,
            'method' => 'wave',
            'reference' => null,
            'notes' => 'Notification mobile money non rapproche',
            'created_by' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->withSession([
                'current_company_id' => $manager->company_id,
                'current_branch_id' => $manager->branch_id,
            ])
            ->get(route('dashboard'))
            ->assertOk();

        $notification = InternalNotification::query()
            ->where('company_id', $manager->company_id)
            ->where('title', 'Mobile money a rapprocher')
            ->whereNull('resolved_at')
            ->firstOrFail();

        $this->assertContains($notification->code, [
            'mobile-money-reconciliation-risk',
            'mobile-money-reconciliation-risk-'.$manager->branch_id,
        ]);
        $this->assertStringContainsString('32 000 XOF', (string) $notification->message);
        $this->assertStringContainsString('1 sans reference exploitable', (string) $notification->message);
    }

    public function test_dashboard_generates_internal_notification_for_pending_internal_transfer_deposits(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $bankAccount = CashAccount::query()->where('company_id', $manager->company_id)->where('name', 'Banque BDM')->firstOrFail();

        Payment::query()->create([
            'tenant_id' => $manager->tenant_id,
            'company_id' => $manager->company_id,
            'branch_id' => $manager->branch_id,
            'cash_account_id' => $bankAccount->id,
            'partner_id' => null,
            'payment_number' => 'ENC-NOTIF-DEPOT-001',
            'direction' => 'in',
            'payment_type' => 'internal_transfer',
            'payment_date' => now()->subDays(3)->toDateString(),
            'amount' => 47000,
            'method' => 'bank_transfer',
            'reference' => null,
            'notes' => 'Notification depot agence a rapprocher',
            'created_by' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->withSession([
                'current_company_id' => $manager->company_id,
                'current_branch_id' => $manager->branch_id,
            ])
            ->get(route('dashboard'))
            ->assertOk();

        $notification = InternalNotification::query()
            ->where('company_id', $manager->company_id)
            ->where('title', 'Versements agence a rapprocher')
            ->whereNull('resolved_at')
            ->firstOrFail();

        $this->assertContains($notification->code, [
            'internal-transfer-deposit-risk',
            'internal-transfer-deposit-risk-'.$manager->branch_id,
        ]);
        $this->assertStringContainsString('47 000 XOF', (string) $notification->message);
        $this->assertStringContainsString('1 depot(s) depuis 2+ jours', (string) $notification->message);
        $this->assertStringContainsString('1 sans bordereau exploitable', (string) $notification->message);
    }

    public function test_dashboard_does_not_flag_missing_deposit_proof_when_attachment_exists(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $bankAccount = CashAccount::query()->where('company_id', $manager->company_id)->where('name', 'Banque BDM')->firstOrFail();

        $payment = Payment::query()->create([
            'tenant_id' => $manager->tenant_id,
            'company_id' => $manager->company_id,
            'branch_id' => $manager->branch_id,
            'cash_account_id' => $bankAccount->id,
            'partner_id' => null,
            'payment_number' => 'ENC-NOTIF-DEPOT-002',
            'direction' => 'in',
            'payment_type' => 'internal_transfer',
            'payment_date' => now()->subDays(3)->toDateString(),
            'amount' => 52000,
            'method' => 'bank_transfer',
            'reference' => null,
            'notes' => 'Depot documente par piece jointe',
            'created_by' => $manager->id,
        ]);

        Attachment::query()->create([
            'tenant_id' => $manager->tenant_id,
            'company_id' => $manager->company_id,
            'attachable_type' => Payment::class,
            'attachable_id' => $payment->id,
            'disk' => 'public',
            'path' => 'tests/bordereau-notif.pdf',
            'original_name' => 'bordereau-notif.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 4096,
            'created_by' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->withSession([
                'current_company_id' => $manager->company_id,
                'current_branch_id' => $manager->branch_id,
            ])
            ->get(route('dashboard'))
            ->assertOk();

        $notification = InternalNotification::query()
            ->where('company_id', $manager->company_id)
            ->where('title', 'Versements agence a rapprocher')
            ->whereNull('resolved_at')
            ->firstOrFail();

        $this->assertStringContainsString('52 000 XOF', (string) $notification->message);
        $this->assertStringContainsString('1 depot(s) depuis 2+ jours', (string) $notification->message);
        $this->assertStringNotContainsString('sans bordereau exploitable', (string) $notification->message);
    }

    public function test_dashboard_generates_internal_notification_for_documented_internal_transfer_deposits(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $bankAccount = CashAccount::query()->where('company_id', $manager->company_id)->where('name', 'Banque BDM')->firstOrFail();

        Payment::query()->create([
            'tenant_id' => $manager->tenant_id,
            'company_id' => $manager->company_id,
            'branch_id' => $manager->branch_id,
            'cash_account_id' => $bankAccount->id,
            'partner_id' => null,
            'payment_number' => 'ENC-NOTIF-DEPOT-DOC-001',
            'direction' => 'in',
            'payment_type' => 'internal_transfer',
            'payment_date' => now()->subDays(3)->toDateString(),
            'amount' => 61000,
            'method' => 'bank_transfer',
            'reference' => 'BORD-NOTIF-001',
            'notes' => 'Depot documente par reference',
            'created_by' => $manager->id,
        ]);

        $paymentWithAttachment = Payment::query()->create([
            'tenant_id' => $manager->tenant_id,
            'company_id' => $manager->company_id,
            'branch_id' => $manager->branch_id,
            'cash_account_id' => $bankAccount->id,
            'partner_id' => null,
            'payment_number' => 'ENC-NOTIF-DEPOT-DOC-002',
            'direction' => 'in',
            'payment_type' => 'internal_transfer',
            'payment_date' => now()->toDateString(),
            'amount' => 19000,
            'method' => 'bank_transfer',
            'reference' => null,
            'notes' => 'Depot documente par piece jointe',
            'created_by' => $manager->id,
        ]);

        Payment::query()->create([
            'tenant_id' => $manager->tenant_id,
            'company_id' => $manager->company_id,
            'branch_id' => $manager->branch_id,
            'cash_account_id' => $bankAccount->id,
            'partner_id' => null,
            'payment_number' => 'ENC-NOTIF-DEPOT-DOC-003',
            'direction' => 'in',
            'payment_type' => 'internal_transfer',
            'payment_date' => now()->subDays(4)->toDateString(),
            'amount' => 27000,
            'method' => 'bank_transfer',
            'reference' => null,
            'notes' => 'Depot sans justificatif',
            'created_by' => $manager->id,
        ]);

        Attachment::query()->create([
            'tenant_id' => $manager->tenant_id,
            'company_id' => $manager->company_id,
            'attachable_type' => Payment::class,
            'attachable_id' => $paymentWithAttachment->id,
            'disk' => 'public',
            'path' => 'tests/bordereau-notif-documented.pdf',
            'original_name' => 'bordereau-notif-documented.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 4096,
            'created_by' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->withSession([
                'current_company_id' => $manager->company_id,
                'current_branch_id' => $manager->branch_id,
            ])
            ->get(route('dashboard'))
            ->assertOk();

        $notification = InternalNotification::query()
            ->where('company_id', $manager->company_id)
            ->where('title', 'Versements documentes a rapprocher')
            ->whereNull('resolved_at')
            ->firstOrFail();

        $this->assertContains($notification->code, [
            'internal-transfer-documented-deposit-risk',
            'internal-transfer-documented-deposit-risk-'.$manager->branch_id,
        ]);
        $this->assertStringContainsString('2 versement(s) documentes attendent encore le rapprochement', (string) $notification->message);
        $this->assertStringContainsString('80 000 XOF', (string) $notification->message);
        $this->assertStringContainsString('1 depot(s) depuis 2+ jours', (string) $notification->message);
    }

    public function test_dashboard_generates_internal_notification_for_tracked_products_without_saleable_stock(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $warehouse = Warehouse::query()
            ->where('company_id', $manager->company_id)
            ->where('branch_id', $manager->branch_id)
            ->where('is_default', true)
            ->firstOrFail();
        $category = ProductCategory::query()->firstOrCreate([
            'company_id' => $manager->company_id,
            'name' => 'Produits traces',
        ], [
            'tenant_id' => $manager->tenant_id,
            'description' => 'Categorie test stock vendable',
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'tenant_id' => $manager->tenant_id,
            'company_id' => $manager->company_id,
            'category_id' => $category->id,
            'sku' => 'NOTIF-TRK-001',
            'name' => 'Vaccin test',
            'unit' => 'flacon',
            'type' => 'stockable',
            'tracking_type' => 'lot',
            'sale_price' => 8500,
            'purchase_price' => 6200,
            'min_stock' => 2,
            'is_active' => true,
            'sale_ok' => true,
            'purchase_ok' => true,
        ]);

        ProductLot::query()->create([
            'tenant_id' => $manager->tenant_id,
            'company_id' => $manager->company_id,
            'branch_id' => $manager->branch_id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'tracking_type' => 'lot',
            'lot_number' => 'NOTIF-TRK-LOT-001',
            'expires_at' => now()->subDay()->toDateString(),
            'received_at' => now()->subDays(10)->toDateString(),
            'unit_cost' => 6200,
            'quantity_received' => 5,
            'quantity_available' => 5,
            'notes' => 'Lot expire pour notification',
        ]);

        $this->actingAs($manager)
            ->withSession([
                'current_company_id' => $manager->company_id,
                'current_branch_id' => $manager->branch_id,
            ])
            ->get(route('dashboard'))
            ->assertOk();

        $notification = InternalNotification::query()
            ->where('company_id', $manager->company_id)
            ->where('title', 'Produits traces sans stock vendable')
            ->whereNull('resolved_at')
            ->firstOrFail();

        $this->assertContains($notification->code, [
            'tracked-saleable-stock-risk',
            'tracked-saleable-stock-risk-'.$manager->branch_id,
        ]);
        $this->assertStringContainsString('1 produit(s) traces n ont plus aucun lot non expire disponible pour la vente', (string) $notification->message);
        $this->assertStringContainsString('Vaccin test', (string) $notification->message);
    }

    public function test_dashboard_generates_food_store_notifications_for_short_dated_lots_and_saleable_stockouts(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $warehouse = Warehouse::query()
            ->where('company_id', $manager->company_id)
            ->where('branch_id', $manager->branch_id)
            ->where('is_default', true)
            ->firstOrFail();
        $category = ProductCategory::query()->firstOrCreate([
            'company_id' => $manager->company_id,
            'name' => 'Produits frais',
        ], [
            'tenant_id' => $manager->tenant_id,
            'description' => 'Categorie food notification',
            'is_active' => true,
        ]);

        Setting::query()->updateOrCreate(
            ['company_id' => $manager->company_id, 'key' => 'sector_profile'],
            ['value' => ['profile' => 'food_store']]
        );

        $shortDatedProduct = Product::query()->create([
            'tenant_id' => $manager->tenant_id,
            'company_id' => $manager->company_id,
            'category_id' => $category->id,
            'sku' => 'FOOD-NOTIF-001',
            'name' => 'Lait frais vanille',
            'unit' => 'brique',
            'type' => 'stockable',
            'tracking_type' => 'lot',
            'sale_price' => 900,
            'purchase_price' => 610,
            'min_stock' => 4,
            'is_active' => true,
            'sale_ok' => true,
            'purchase_ok' => true,
        ]);

        ProductLot::query()->create([
            'tenant_id' => $manager->tenant_id,
            'company_id' => $manager->company_id,
            'branch_id' => $manager->branch_id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $shortDatedProduct->id,
            'tracking_type' => 'lot',
            'lot_number' => 'FOOD-NOTIF-SHORT-001',
            'expires_at' => now()->addDays(2)->toDateString(),
            'received_at' => now()->subDays(2)->toDateString(),
            'unit_cost' => 610,
            'quantity_received' => 8,
            'quantity_available' => 8,
        ]);

        $stockoutProduct = Product::query()->create([
            'tenant_id' => $manager->tenant_id,
            'company_id' => $manager->company_id,
            'category_id' => $category->id,
            'sku' => 'FOOD-NOTIF-002',
            'name' => 'Eau glacee citron',
            'unit' => 'bouteille',
            'type' => 'stockable',
            'tracking_type' => 'none',
            'sale_price' => 450,
            'purchase_price' => 260,
            'min_stock' => 3,
            'is_active' => true,
            'sale_ok' => true,
            'purchase_ok' => true,
        ]);

        StockMovement::query()->create([
            'company_id' => $manager->company_id,
            'branch_id' => $manager->branch_id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $stockoutProduct->id,
            'movement_type' => 'opening',
            'quantity_in' => 6,
            'quantity_out' => 0,
            'unit_cost' => 260,
            'reason' => 'Stock initial food notification',
            'movement_date' => now()->subDays(3),
            'created_by' => $manager->id,
        ]);

        StockMovement::query()->create([
            'company_id' => $manager->company_id,
            'branch_id' => $manager->branch_id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $stockoutProduct->id,
            'movement_type' => 'sale',
            'quantity_in' => 0,
            'quantity_out' => 6,
            'unit_cost' => 260,
            'reason' => 'Sortie food notification',
            'movement_date' => now()->subDay(),
            'created_by' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->withSession([
                'current_company_id' => $manager->company_id,
                'current_branch_id' => $manager->branch_id,
            ])
            ->get(route('dashboard'))
            ->assertOk();

        $shortDatedNotification = InternalNotification::query()
            ->where('company_id', $manager->company_id)
            ->where('title', 'Lots courts a ecouler')
            ->whereNull('resolved_at')
            ->firstOrFail();

        $this->assertStringContainsString('1 lot(s) sur 1 produit(s) expirent sous 7 jours', (string) $shortDatedNotification->message);
        $this->assertStringContainsString('Lait frais vanille', (string) $shortDatedNotification->message);

        $stockoutNotification = InternalNotification::query()
            ->where('company_id', $manager->company_id)
            ->where('title', 'Ruptures rayon vendables')
            ->whereNull('resolved_at')
            ->firstOrFail();

        $this->assertStringContainsString('1 reference(s) ont deja tourne en stock mais n ont plus rien de vendable au comptoir', (string) $stockoutNotification->message);
        $this->assertStringContainsString('Eau glacee citron', (string) $stockoutNotification->message);
    }

    public function test_dashboard_generates_wholesale_distribution_notifications_for_risk_and_overdue_commitments(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $this->seedWholesaleCoverageScenario($manager, 'NOTIFWHO');

        $this->actingAs($manager)
            ->withSession([
                'current_company_id' => $manager->company_id,
                'current_branch_id' => $manager->branch_id,
            ])
            ->get(route('dashboard'))
            ->assertOk();

        $riskNotification = InternalNotification::query()
            ->where('company_id', $manager->company_id)
            ->where('title', 'Commandes grossiste a risque')
            ->whereNull('resolved_at')
            ->firstOrFail();

        $this->assertContains($riskNotification->code, [
            'wholesale-order-coverage-risk',
            'wholesale-order-coverage-risk-'.$manager->branch_id,
        ]);
        $this->assertStringContainsString('1 commande(s) couvrent mal 1 ligne(s) pour 3,000 unite(s) encore non couvertes', (string) $riskNotification->message);
        $this->assertStringContainsString('ORDER-NOTIFWHO-RISK-001', (string) $riskNotification->message);

        $overdueNotification = InternalNotification::query()
            ->where('company_id', $manager->company_id)
            ->where('title', 'Engagements grossiste en retard')
            ->whereNull('resolved_at')
            ->firstOrFail();

        $this->assertContains($overdueNotification->code, [
            'wholesale-overdue-commitments',
            'wholesale-overdue-commitments-'.$manager->branch_id,
        ]);
        $this->assertStringContainsString('1 commande(s) gardent encore 3,000 unite(s) en reliquat apres la date promise', (string) $overdueNotification->message);
        $this->assertStringContainsString('ORDER-NOTIFWHO-OVERDUE-001', (string) $overdueNotification->message);
    }

    public function test_user_can_view_and_mark_notification_as_read(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        InternalNotification::query()->create([
            'company_id' => $manager->company_id,
            'branch_id' => $manager->branch_id,
            'code' => 'manual-test-alert',
            'type' => 'system',
            'level' => 'warning',
            'title' => 'Alerte de test',
            'message' => 'Une alerte interne de demonstration.',
            'action_url' => route('dashboard'),
        ]);

        $this->actingAs($manager)
            ->withSession([
                'current_company_id' => $manager->company_id,
                'current_branch_id' => $manager->branch_id,
            ])
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Centre d alertes')
            ->assertSee('Alertes actives')
            ->assertSee('Alerte de test');

        $notification = InternalNotification::query()->where('company_id', $manager->company_id)->where('code', 'manual-test-alert')->firstOrFail();

        $this->actingAs($manager)
            ->withSession([
                'current_company_id' => $manager->company_id,
                'current_branch_id' => $manager->branch_id,
            ])
            ->post(route('notifications.read', $notification))
            ->assertSessionHas('success');

        $notification->refresh();
        $this->assertTrue($notification->is_read);
        $this->assertSame($manager->id, $notification->read_by);
    }

    public function test_branch_limited_user_only_sees_visible_agency_notifications(): void
    {
        $operations = User::query()->where('email', 'ops@nema-erp.test')->firstOrFail();
        $director = User::query()->where('email', 'dg@nema-erp.test')->firstOrFail();

        $otherBranch = Branch::query()->create([
            'tenant_id' => $operations->tenant_id,
            'company_id' => $operations->company_id,
            'name' => 'Agence Segou',
            'code' => 'SEG',
            'city' => 'Segou',
            'address' => 'Segou Centre',
            'is_active' => true,
            'is_default' => false,
        ]);

        $globalNotification = InternalNotification::query()->create([
            'company_id' => $operations->company_id,
            'branch_id' => null,
            'code' => 'global-branch-visible-alert',
            'type' => 'system',
            'level' => 'warning',
            'title' => 'Alerte globale visible',
            'message' => 'Message global commun a toutes les agences.',
            'action_url' => route('dashboard'),
        ]);

        $ownNotification = InternalNotification::query()->create([
            'company_id' => $operations->company_id,
            'branch_id' => $operations->branch_id,
            'code' => 'own-branch-visible-alert',
            'type' => 'system',
            'level' => 'warning',
            'title' => 'Alerte agence Bamako visible',
            'message' => 'Message propre a l agence active.',
            'action_url' => route('dashboard'),
        ]);

        $hiddenNotification = InternalNotification::query()->create([
            'company_id' => $operations->company_id,
            'branch_id' => $otherBranch->id,
            'code' => 'hidden-branch-alert',
            'type' => 'system',
            'level' => 'danger',
            'title' => 'Alerte agence Segou cachee',
            'message' => 'Message reserve a une autre agence.',
            'action_url' => route('dashboard'),
        ]);

        $this->actingAs($operations)
            ->withSession([
                'current_company_id' => $operations->company_id,
                'current_branch_id' => $operations->branch_id,
            ])
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Alerte globale visible')
            ->assertSee('Alerte agence Bamako visible')
            ->assertDontSee('Alerte agence Segou cachee');

        $this->actingAs($operations)
            ->withSession([
                'current_company_id' => $operations->company_id,
                'current_branch_id' => $operations->branch_id,
            ])
            ->post(route('notifications.read', $hiddenNotification))
            ->assertForbidden();

        $this->actingAs($operations)
            ->withSession([
                'current_company_id' => $operations->company_id,
                'current_branch_id' => $operations->branch_id,
            ])
            ->post(route('notifications.read-all'))
            ->assertSessionHas('success');

        $this->assertTrue($globalNotification->fresh()->is_read);
        $this->assertTrue($ownNotification->fresh()->is_read);
        $this->assertFalse($hiddenNotification->fresh()->is_read);

        $this->actingAs($director)
            ->withSession([
                'current_company_id' => $director->company_id,
                'current_branch_id' => $director->branch_id,
            ])
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Alerte agence Segou cachee');
    }

    public function test_notifications_page_can_filter_by_scope_level_read_state_and_search(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        InternalNotification::query()->create([
            'company_id' => $manager->company_id,
            'branch_id' => $manager->branch_id,
            'code' => 'stock-warning-filter',
            'type' => 'system',
            'level' => 'warning',
            'title' => 'Stock critique Bamako',
            'message' => 'Une alerte de stock a traiter.',
            'action_url' => route('stock.index'),
            'is_read' => false,
        ]);

        InternalNotification::query()->create([
            'company_id' => $manager->company_id,
            'branch_id' => $manager->branch_id,
            'code' => 'info-read-filter',
            'type' => 'system',
            'level' => 'info',
            'title' => 'Information de demonstration',
            'message' => 'Notification informative deja lue.',
            'action_url' => route('dashboard'),
            'is_read' => true,
            'read_at' => now(),
            'read_by' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->withSession([
                'current_company_id' => $manager->company_id,
                'current_branch_id' => $manager->branch_id,
            ])
            ->get(route('notifications.index', [
                'scope' => 'all',
                'level' => 'warning',
                'read_state' => 'unread',
                'search' => 'Stock',
            ]))
            ->assertOk()
            ->assertSee('Stock critique Bamako')
            ->assertDontSee('Information de demonstration');
    }
}
