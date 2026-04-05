<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Approvals\Models\ApprovalStep;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Notifications\Models\InternalNotification;
use App\Modules\Core\Notifications\Services\NotificationService;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Expenses\Models\ExpenseCategory;
use App\Modules\Inventory\Models\StockCount;
use App\Modules\Inventory\Models\StockCountItem;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Partners\Models\Partner;
use App\Modules\Purchases\Models\PurchaseBill;
use App\Modules\Sales\Models\SalesInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvancedNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_notifications_service_creates_overdue_document_and_stale_approval_alerts(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $manager->company_id)->firstOrFail();
        $supplier = Partner::query()->suppliers()->where('company_id', $manager->company_id)->firstOrFail();

        SalesInvoice::query()->create([
            'company_id' => $manager->company_id,
            'branch_id' => $manager->branch_id,
            'customer_id' => $customer->id,
            'invoice_number' => 'ALR-VTE-001',
            'invoice_date' => now()->subDays(15)->toDateString(),
            'due_date' => now()->subDays(5)->toDateString(),
            'status' => 'validated',
            'payment_status' => 'partial',
            'subtotal' => 120000,
            'net_total' => 120000,
            'tax_total' => 0,
            'total' => 120000,
            'amount_paid' => 20000,
            'balance_due' => 100000,
            'created_by' => $manager->id,
        ]);

        PurchaseBill::query()->create([
            'company_id' => $manager->company_id,
            'branch_id' => $manager->branch_id,
            'supplier_id' => $supplier->id,
            'bill_number' => 'ALR-ACH-001',
            'bill_date' => now()->subDays(14)->toDateString(),
            'due_date' => now()->subDays(4)->toDateString(),
            'status' => 'validated',
            'payment_status' => 'unpaid',
            'subtotal' => 90000,
            'net_total' => 90000,
            'tax_total' => 0,
            'total' => 90000,
            'amount_paid' => 0,
            'balance_due' => 90000,
            'created_by' => $manager->id,
        ]);

        $pendingInvoice = SalesInvoice::query()->create([
            'company_id' => $manager->company_id,
            'branch_id' => $manager->branch_id,
            'customer_id' => $customer->id,
            'invoice_number' => 'ALR-VTE-PENDING-001',
            'invoice_date' => now()->subDays(3)->toDateString(),
            'status' => 'pending_approval',
            'payment_status' => 'unpaid',
            'subtotal' => 15000,
            'net_total' => 15000,
            'tax_total' => 0,
            'total' => 15000,
            'amount_paid' => 0,
            'balance_due' => 15000,
            'created_by' => $manager->id,
        ]);

        $approvalStep = ApprovalStep::query()->create([
            'company_id' => $manager->company_id,
            'approvable_type' => SalesInvoice::class,
            'approvable_id' => $pendingInvoice->id,
            'module' => 'sales',
            'step_order' => 1,
            'code' => 'director-review',
            'label' => 'Direction',
            'rule' => 'manual',
            'status' => 'pending',
        ]);

        ApprovalStep::query()->whereKey($approvalStep->id)->update([
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ]);

        $this->syncAlerts($manager);

        $this->assertDatabaseHas('internal_notifications', [
            'company_id' => $manager->company_id,
            'code' => 'overdue-sales-balance',
            'resolved_at' => null,
        ]);

        $this->assertDatabaseHas('internal_notifications', [
            'company_id' => $manager->company_id,
            'code' => 'overdue-purchase-balance',
            'resolved_at' => null,
        ]);

        $this->assertDatabaseHas('internal_notifications', [
            'company_id' => $manager->company_id,
            'code' => 'stale-approvals',
            'resolved_at' => null,
        ]);

        $salesAlert = InternalNotification::query()->where('company_id', $manager->company_id)->where('code', 'overdue-sales-balance')->firstOrFail();
        $approvalAlert = InternalNotification::query()->where('company_id', $manager->company_id)->where('code', 'stale-approvals')->firstOrFail();

        $this->assertSame(1, $salesAlert->meta['count'] ?? null);
        $this->assertSame(1, $approvalAlert->meta['count'] ?? null);
        $this->assertStringContainsString('ventes: 1', (string) ($approvalAlert->meta['breakdown'] ?? ''));
    }

    public function test_notifications_service_creates_expense_spike_and_stock_count_variance_alerts(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $category = ExpenseCategory::query()->where('company_id', $manager->company_id)->where('name', 'Carburant')->firstOrFail();
        $warehouse = Warehouse::query()->where('company_id', $manager->company_id)->where('branch_id', $manager->branch_id)->where('is_default', true)->firstOrFail();
        $product = \App\Modules\Catalog\Models\Product::query()->where('company_id', $manager->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        foreach ([10000, 12000, 11000, 9000] as $index => $amount) {
            Expense::query()->create([
                'company_id' => $manager->company_id,
                'branch_id' => $manager->branch_id,
                'expense_category_id' => $category->id,
                'expense_number' => 'ALR-EXP-BASE-00'.($index + 1),
                'expense_date' => now()->subDays(20 - ($index * 2))->toDateString(),
                'description' => 'Base carburant '.($index + 1),
                'total' => $amount,
                'status' => 'validated',
                'payment_status' => 'paid',
                'created_by' => $manager->id,
            ]);
        }

        $expense = Expense::query()->create([
            'company_id' => $manager->company_id,
            'branch_id' => $manager->branch_id,
            'expense_category_id' => $category->id,
            'expense_number' => 'ALR-EXP-ANOM-001',
            'expense_date' => now()->subDay()->toDateString(),
            'description' => 'Depense inhabituelle de carburant',
            'total' => 90000,
            'status' => 'validated',
            'payment_status' => 'paid',
            'created_by' => $manager->id,
        ]);

        $stockCount = StockCount::query()->create([
            'company_id' => $manager->company_id,
            'branch_id' => $manager->branch_id,
            'warehouse_id' => $warehouse->id,
            'count_number' => 'INV-ALR-001',
            'count_date' => now()->subDay()->toDateString(),
            'status' => 'posted',
            'notes' => 'Inventaire avec ecart',
            'posted_at' => now()->subDay(),
            'posted_by' => $manager->id,
            'created_by' => $manager->id,
        ]);

        StockCountItem::query()->create([
            'stock_count_id' => $stockCount->id,
            'product_id' => $product->id,
            'expected_qty' => 20,
            'counted_qty' => 17,
            'variance_qty' => -3,
            'unit_cost' => 2500,
        ]);

        $this->syncAlerts($manager);

        $this->assertDatabaseHas('internal_notifications', [
            'company_id' => $manager->company_id,
            'code' => 'expense-spike',
            'resolved_at' => null,
        ]);

        $this->assertDatabaseHas('internal_notifications', [
            'company_id' => $manager->company_id,
            'code' => 'stock-count-variance',
            'resolved_at' => null,
        ]);

        $expenseAlert = InternalNotification::query()->where('company_id', $manager->company_id)->where('code', 'expense-spike')->firstOrFail();
        $stockAlert = InternalNotification::query()->where('company_id', $manager->company_id)->where('code', 'stock-count-variance')->firstOrFail();

        $this->assertSame($expense->id, $expenseAlert->meta['expense_id'] ?? null);
        $this->assertSame($stockCount->id, $stockAlert->meta['stock_count_id'] ?? null);
        $this->assertSame($manager->branch_id, $stockAlert->branch_id);
    }

    public function test_notifications_service_creates_lot_expiry_alerts(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $warehouse = Warehouse::query()->where('company_id', $manager->company_id)->where('branch_id', $manager->branch_id)->where('is_default', true)->firstOrFail();

        $product = \App\Modules\Catalog\Models\Product::query()->create([
            'company_id' => $manager->company_id,
            'sku' => 'PRD-ALR-LOT-001',
            'barcode' => '770000000041',
            'name' => 'Yaourt alerte lot',
            'unit' => 'pot',
            'type' => 'stockable',
            'tracking_type' => 'lot',
            'sale_price' => 650,
            'purchase_price' => 420,
            'min_stock' => 4,
            'is_active' => true,
            'sale_ok' => true,
            'purchase_ok' => true,
        ]);

        \App\Modules\Inventory\Models\ProductLot::query()->create([
            'company_id' => $manager->company_id,
            'branch_id' => $manager->branch_id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'tracking_type' => 'lot',
            'lot_number' => 'ALR-LOT-EXP-01',
            'expires_at' => now()->subDay()->toDateString(),
            'received_at' => now()->subDays(12)->toDateString(),
            'unit_cost' => 420,
            'quantity_received' => 14,
            'quantity_available' => 9,
        ]);

        \App\Modules\Inventory\Models\ProductLot::query()->create([
            'company_id' => $manager->company_id,
            'branch_id' => $manager->branch_id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'tracking_type' => 'lot',
            'lot_number' => 'ALR-LOT-SOON-01',
            'expires_at' => now()->addDays(9)->toDateString(),
            'received_at' => now()->subDays(5)->toDateString(),
            'unit_cost' => 420,
            'quantity_received' => 11,
            'quantity_available' => 6,
        ]);

        $this->syncAlerts($manager);

        $expiredCode = 'expired-product-lots-'.$manager->branch_id;
        $expiringCode = 'expiring-product-lots-'.$manager->branch_id;

        $this->assertDatabaseHas('internal_notifications', [
            'company_id' => $manager->company_id,
            'branch_id' => $manager->branch_id,
            'code' => $expiredCode,
            'resolved_at' => null,
        ]);

        $this->assertDatabaseHas('internal_notifications', [
            'company_id' => $manager->company_id,
            'branch_id' => $manager->branch_id,
            'code' => $expiringCode,
            'resolved_at' => null,
        ]);

        $expiredAlert = InternalNotification::query()->where('company_id', $manager->company_id)->where('code', $expiredCode)->firstOrFail();
        $expiringAlert = InternalNotification::query()->where('company_id', $manager->company_id)->where('code', $expiringCode)->firstOrFail();

        $this->assertSame(1, $expiredAlert->meta['count'] ?? null);
        $this->assertSame(1, $expiringAlert->meta['count'] ?? null);

        \App\Modules\Inventory\Models\ProductLot::query()
            ->where('company_id', $manager->company_id)
            ->where('product_id', $product->id)
            ->update([
                'quantity_available' => 0,
                'expires_at' => now()->addDays(90)->toDateString(),
            ]);

        $this->syncAlerts($manager);

        $this->assertNotNull(InternalNotification::query()->where('company_id', $manager->company_id)->where('code', $expiredCode)->firstOrFail()->resolved_at);
        $this->assertNotNull(InternalNotification::query()->where('company_id', $manager->company_id)->where('code', $expiringCode)->firstOrFail()->resolved_at);
    }
    public function test_branch_sales_drop_alert_resolves_when_branch_recovers(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $manager->company_id)->firstOrFail();
        $branch = Branch::query()->where('company_id', $manager->company_id)->where('code', 'SIK')->firstOrFail();

        $this->createSalesInvoice($manager, $customer->id, $branch->id, 'ALR-SIK-PREV-001', now()->subDays(10)->toDateString(), 80000);
        $this->createSalesInvoice($manager, $customer->id, $branch->id, 'ALR-SIK-PREV-002', now()->subDays(8)->toDateString(), 80000);
        $this->createSalesInvoice($manager, $customer->id, $branch->id, 'ALR-SIK-CURR-001', now()->subDay()->toDateString(), 40000);

        $this->syncAlerts($manager);

        $code = 'branch-sales-drop-'.$branch->id;
        $this->assertDatabaseHas('internal_notifications', [
            'company_id' => $manager->company_id,
            'code' => $code,
            'branch_id' => $branch->id,
            'resolved_at' => null,
        ]);

        $this->createSalesInvoice($manager, $customer->id, $branch->id, 'ALR-SIK-CURR-002', now()->toDateString(), 500000);

        $this->syncAlerts($manager);

        $notification = InternalNotification::query()->where('company_id', $manager->company_id)->where('code', $code)->firstOrFail();

        $this->assertNotNull($notification->resolved_at);
    }

    private function syncAlerts(User $user): void
    {
        app(NotificationService::class)->syncCompanyAlerts($user->company_id, $user->branch_id);
    }

    private function createSalesInvoice(User $user, int $customerId, int $branchId, string $number, string $invoiceDate, float $total): SalesInvoice
    {
        return SalesInvoice::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $branchId,
            'customer_id' => $customerId,
            'invoice_number' => $number,
            'invoice_date' => $invoiceDate,
            'status' => 'validated',
            'payment_status' => 'paid',
            'subtotal' => $total,
            'net_total' => $total,
            'tax_total' => 0,
            'total' => $total,
            'amount_paid' => $total,
            'balance_due' => 0,
            'created_by' => $user->id,
        ]);
    }
}
