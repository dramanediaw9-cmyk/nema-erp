<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Notifications\Models\InternalNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_dashboard_generates_internal_notifications_for_pending_documents(): void
    {
        $operator = User::query()->where('email', 'ops@nema-erp.test')->firstOrFail();
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $supplier = \App\Modules\Partners\Models\Partner::query()->suppliers()->where('company_id', $operator->company_id)->firstOrFail();
        $customer = \App\Modules\Partners\Models\Partner::query()->customers()->where('company_id', $operator->company_id)->firstOrFail();
        $product = \App\Modules\Catalog\Models\Product::query()->where('company_id', $operator->company_id)->where('sku', 'PRD-0001')->firstOrFail();

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

