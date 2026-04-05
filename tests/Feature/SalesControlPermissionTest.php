<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Partners\Models\Partner;
use App\Modules\Sales\Models\SalesInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesControlPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_pending_sale_can_be_cancelled_only_with_dedicated_permission(): void
    {
        $operator = User::query()->where('email', 'ops@nema-erp.test')->firstOrFail();
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $operator->company_id)->firstOrFail();
        $product = Product::query()->where('company_id', $operator->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $this->actingAs($operator)
            ->withSession($this->workspaceSession($operator))
            ->post(route('sales.store'), [
                'customer_id' => $customer->id,
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays(7)->toDateString(),
                'notes' => 'PENDING-SALE-CANCEL-CONTROL',
                'items' => [[
                    'product_id' => $product->id,
                    'description' => 'Facture en attente a annuler',
                    'qty' => 2,
                    'unit_price' => 500,
                ]],
            ])
            ->assertRedirect();

        $invoice = SalesInvoice::query()
            ->where('company_id', $operator->company_id)
            ->where('notes', 'PENDING-SALE-CANCEL-CONTROL')
            ->firstOrFail();

        $this->assertSame('pending_approval', $invoice->status);

        $this->actingAs($operator)
            ->withSession($this->workspaceSession($operator))
            ->post(route('sales.cancel', $invoice))
            ->assertForbidden();

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->post(route('sales.cancel', $invoice))
            ->assertRedirect(route('sales.show', $invoice));

        $invoice->refresh();

        $this->assertSame('cancelled', $invoice->status);
        $this->assertSame($manager->id, $invoice->cancelled_by);
        $this->assertNotNull($invoice->cancelled_at);
        $this->assertDatabaseMissing('approval_steps', [
            'approvable_type' => SalesInvoice::class,
            'approvable_id' => $invoice->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseMissing('stock_movements', [
            'reference_type' => SalesInvoice::class,
            'reference_id' => $invoice->id,
        ]);
        $this->assertDatabaseMissing('journal_entries', [
            'source_type' => SalesInvoice::class,
            'source_id' => $invoice->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'company_id' => $manager->company_id,
            'branch_id' => $manager->branch_id,
            'action' => 'sales.cancel',
            'description' => 'Annulation facture de vente en attente',
        ]);
    }

    public function test_validated_sale_cannot_use_cancel_route_and_must_use_credit_note_flow(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $manager->company_id)->firstOrFail();
        $product = Product::query()->where('company_id', $manager->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->post(route('sales.store'), [
                'customer_id' => $customer->id,
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays(5)->toDateString(),
                'notes' => 'VALIDATED-SALE-NO-CANCEL',
                'items' => [[
                    'product_id' => $product->id,
                    'description' => 'Facture validee non annulable',
                    'qty' => 1,
                    'unit_price' => 900,
                ]],
            ])
            ->assertRedirect();

        $invoice = SalesInvoice::query()
            ->where('company_id', $manager->company_id)
            ->where('notes', 'VALIDATED-SALE-NO-CANCEL')
            ->firstOrFail();

        $this->assertSame('validated', $invoice->status);

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->from(route('sales.show', $invoice))
            ->post(route('sales.cancel', $invoice))
            ->assertRedirect(route('sales.show', $invoice))
            ->assertSessionHasErrors('sale');

        $invoice->refresh();
        $this->assertSame('validated', $invoice->status);
        $this->assertNull($invoice->cancelled_at);
    }

    public function test_only_user_with_credit_note_issue_permission_can_open_and_create_customer_credit_note(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $operator = User::query()->where('email', 'ops@nema-erp.test')->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $manager->company_id)->firstOrFail();
        $product = Product::query()->where('company_id', $manager->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->post(route('sales.store'), [
                'customer_id' => $customer->id,
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays(8)->toDateString(),
                'notes' => 'CREDIT-NOTE-PERM-CHECK',
                'items' => [[
                    'product_id' => $product->id,
                    'description' => 'Facture pour test avoir',
                    'qty' => 4,
                    'unit_price' => 500,
                ]],
            ])
            ->assertRedirect();

        $invoice = SalesInvoice::query()
            ->where('company_id', $manager->company_id)
            ->where('notes', 'CREDIT-NOTE-PERM-CHECK')
            ->firstOrFail();
        $invoiceItem = $invoice->items()->firstOrFail();

        $this->actingAs($operator)
            ->withSession($this->workspaceSession($operator))
            ->get(route('credit-notes.create', $invoice))
            ->assertForbidden();

        $this->actingAs($operator)
            ->withSession($this->workspaceSession($operator))
            ->post(route('credit-notes.store', $invoice), [
                'credit_note_date' => now()->toDateString(),
                'restock_items' => 1,
                'notes' => 'Tentative sans permission',
                'items' => [[
                    'sales_invoice_item_id' => $invoiceItem->id,
                    'qty' => 1,
                ]],
            ])
            ->assertForbidden();

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->get(route('credit-notes.create', $invoice))
            ->assertOk();

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->post(route('credit-notes.store', $invoice), [
                'credit_note_date' => now()->toDateString(),
                'restock_items' => 1,
                'notes' => 'Avoir autorise controle',
                'items' => [[
                    'sales_invoice_item_id' => $invoiceItem->id,
                    'qty' => 1,
                ]],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('sales_credit_notes', [
            'company_id' => $manager->company_id,
            'sales_invoice_id' => $invoice->id,
            'notes' => 'Avoir autorise controle',
        ]);
    }

    private function workspaceSession(User $user): array
    {
        return [
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ];
    }
}
