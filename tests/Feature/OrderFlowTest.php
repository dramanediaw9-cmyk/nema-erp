<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Partners\Models\Partner;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderFlowTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_manager_can_create_a_customer_order(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $user->company_id)->firstOrFail();
        $product = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $response = $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('orders.store'), [
                'customer_id' => $customer->id,
                'order_date' => now()->format('Y-m-d'),
                'requested_delivery_date' => now()->addDays(5)->format('Y-m-d'),
                'notes' => 'TEST-ORDER-001',
                'items' => [[
                    'product_id' => $product->id,
                    'description' => 'Commande test eau',
                    'qty' => 4,
                    'unit_price' => 500,
                ]],
            ]);

        $order = SalesOrder::query()->where('company_id', $user->company_id)->where('notes', 'TEST-ORDER-001')->firstOrFail();

        $response->assertRedirect(route('orders.show', $order));
        $this->assertSame('draft', $order->status);
        $this->assertEqualsWithDelta(2000, (float) $order->total, 0.001);
        $this->assertMatchesRegularExpression('/^CMD-BKO-\d{4}-\d{5}$/', $order->order_number);
    }

    public function test_confirmed_order_can_be_converted_to_invoice(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $user->company_id)->firstOrFail();
        $product = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('orders.store'), [
                'customer_id' => $customer->id,
                'order_date' => now()->format('Y-m-d'),
                'requested_delivery_date' => now()->addDays(4)->format('Y-m-d'),
                'notes' => 'TEST-ORDER-CONVERT',
                'items' => [[
                    'product_id' => $product->id,
                    'description' => 'Commande conversion',
                    'qty' => 2,
                    'unit_price' => 450,
                ]],
            ])
            ->assertRedirect();

        $order = SalesOrder::query()->where('company_id', $user->company_id)->where('notes', 'TEST-ORDER-CONVERT')->firstOrFail();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('orders.confirm', $order))
            ->assertRedirect(route('orders.show', $order));

        $response = $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('orders.convert', $order), [
                'invoice_date' => now()->format('Y-m-d'),
                'due_date' => now()->addDays(10)->format('Y-m-d'),
                'notes' => 'FACTURE ISSUE DE LA COMMANDE',
            ]);

        $order->refresh();
        $invoice = SalesInvoice::query()->where('company_id', $user->company_id)->where('id', $order->converted_sales_invoice_id)->firstOrFail();

        $response->assertRedirect(route('sales.show', $invoice));
        $this->assertSame('converted', $order->status);
        $this->assertNotNull($order->converted_at);
        $this->assertSame('validated', $invoice->status);
        $this->assertStringContainsString($order->order_number, (string) $invoice->notes);
    }

    private function workspaceSession(User $user): array
    {
        return [
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ];
    }
}
