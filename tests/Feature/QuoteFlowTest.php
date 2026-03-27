<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Partners\Models\Partner;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Models\SalesQuote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteFlowTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_manager_can_create_a_quote(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $user->company_id)->firstOrFail();
        $product = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $response = $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('quotes.store'), [
                'customer_id' => $customer->id,
                'quote_date' => now()->format('Y-m-d'),
                'valid_until' => now()->addDays(15)->format('Y-m-d'),
                'notes' => 'TEST-QUOTE-001',
                'items' => [[
                    'product_id' => $product->id,
                    'description' => 'Devis test eau',
                    'qty' => 3,
                    'unit_price' => 450,
                ]],
            ]);

        $quote = SalesQuote::query()->where('company_id', $user->company_id)->where('notes', 'TEST-QUOTE-001')->firstOrFail();

        $response->assertRedirect(route('quotes.show', $quote));
        $this->assertSame('draft', $quote->status);
        $this->assertEqualsWithDelta(1350, (float) $quote->total, 0.001);
        $this->assertMatchesRegularExpression('/^DEV-BKO-\d{4}-\d{5}$/', $quote->quote_number);
    }

    public function test_accepted_quote_can_be_converted_to_invoice(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $user->company_id)->firstOrFail();
        $product = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('quotes.store'), [
                'customer_id' => $customer->id,
                'quote_date' => now()->format('Y-m-d'),
                'valid_until' => now()->addDays(10)->format('Y-m-d'),
                'notes' => 'TEST-QUOTE-CONVERT',
                'items' => [[
                    'product_id' => $product->id,
                    'description' => 'Devis conversion',
                    'qty' => 2,
                    'unit_price' => 400,
                ]],
            ])
            ->assertRedirect();

        $quote = SalesQuote::query()->where('company_id', $user->company_id)->where('notes', 'TEST-QUOTE-CONVERT')->firstOrFail();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('quotes.accept', $quote))
            ->assertRedirect(route('quotes.show', $quote));

        $response = $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('quotes.convert', $quote), [
                'invoice_date' => now()->format('Y-m-d'),
                'due_date' => now()->addDays(10)->format('Y-m-d'),
                'notes' => 'FACTURE ISSUE DU DEVIS',
            ]);

        $quote->refresh();
        $invoice = SalesInvoice::query()->where('company_id', $user->company_id)->where('id', $quote->converted_sales_invoice_id)->firstOrFail();

        $response->assertRedirect(route('sales.show', $invoice));
        $this->assertSame('converted', $quote->status);
        $this->assertNotNull($quote->converted_at);
        $this->assertSame('validated', $invoice->status);
        $this->assertStringContainsString($quote->quote_number, (string) $invoice->notes);
    }

    public function test_accepted_quote_can_be_converted_to_confirmed_order(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $user->company_id)->firstOrFail();
        $product = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('quotes.store'), [
                'customer_id' => $customer->id,
                'quote_date' => now()->format('Y-m-d'),
                'valid_until' => now()->addDays(7)->format('Y-m-d'),
                'notes' => 'TEST-QUOTE-TO-ORDER',
                'items' => [[
                    'product_id' => $product->id,
                    'description' => 'Devis vers commande',
                    'qty' => 4,
                    'unit_price' => 500,
                ]],
            ])
            ->assertRedirect();

        $quote = SalesQuote::query()->where('company_id', $user->company_id)->where('notes', 'TEST-QUOTE-TO-ORDER')->firstOrFail();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('quotes.accept', $quote))
            ->assertRedirect(route('quotes.show', $quote));

        $response = $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('quotes.convert-order', $quote), [
                'order_date' => now()->format('Y-m-d'),
                'requested_delivery_date' => now()->addDays(5)->format('Y-m-d'),
                'notes' => 'COMMANDE ISSUE DU DEVIS',
            ]);

        $quote->refresh();
        $order = SalesOrder::query()->where('company_id', $user->company_id)->where('id', $quote->converted_sales_order_id)->firstOrFail();

        $response->assertRedirect(route('orders.show', $order));
        $this->assertSame('converted', $quote->status);
        $this->assertNotNull($quote->converted_at);
        $this->assertSame('confirmed', $order->status);
        $this->assertNotNull($order->confirmed_at);
        $this->assertSame($quote->id, $order->origin_sales_quote_id);
        $this->assertStringContainsString($quote->quote_number, (string) $order->notes);
    }

    private function workspaceSession(User $user): array
    {
        return [
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ];
    }
}
