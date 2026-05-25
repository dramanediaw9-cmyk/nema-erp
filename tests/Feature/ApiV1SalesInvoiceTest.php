<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Integrations\Models\ApiToken;
use App\Modules\Partners\Models\Partner;
use App\Modules\Sales\Models\SalesInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApiV1SalesInvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_api_token_can_create_and_show_sales_invoice(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $plainToken = $this->createApiToken($manager);
        $customer = Partner::query()->customers()->where('company_id', $manager->company_id)->firstOrFail();
        $product = Product::query()->where('company_id', $manager->company_id)->where('sku', 'PRD-0001')->firstOrFail();
        $initialStock = $this->stockBalance($manager->company_id, $manager->branch_id, $product->id);

        $response = $this->withToken($plainToken)
            ->postJson('/api/v1/sales-invoices', [
                'customer_id' => $customer->id,
                'branch_id' => $manager->branch_id,
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays(7)->toDateString(),
                'notes' => 'API-SALE-001',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'description' => 'Facture creee par API',
                        'qty' => 2,
                        'unit_price' => 400,
                    ],
                ],
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('invoice.customer_id', $customer->id)
            ->assertJsonPath('invoice.notes', 'API-SALE-001')
            ->assertJsonPath('invoice.status', 'validated')
            ->assertJsonPath('workflow.is_fully_approved', true);

        $invoiceId = (int) $response->json('invoice.id');
        $invoice = SalesInvoice::query()->whereKey($invoiceId)->firstOrFail();

        $this->assertSame('api', $invoice->sale_channel);
        $this->assertDatabaseHas('stock_movements', [
            'company_id' => $manager->company_id,
            'branch_id' => $manager->branch_id,
            'product_id' => $product->id,
            'reference_type' => SalesInvoice::class,
            'reference_id' => $invoice->id,
            'movement_type' => 'sale',
        ]);

        $this->assertEqualsWithDelta($initialStock - 2, $this->stockBalance($manager->company_id, $manager->branch_id, $product->id), 0.001);

        $this->withToken($plainToken)
            ->getJson('/api/v1/sales-invoices/'.$invoice->id)
            ->assertOk()
            ->assertJsonPath('id', $invoice->id)
            ->assertJsonPath('items.0.product_id', $product->id)
            ->assertJsonPath('items.0.product.sku', 'PRD-0001');
    }

    public function test_api_token_can_filter_sales_invoices(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $plainToken = $this->createApiToken($manager);
        $invoice = SalesInvoice::query()->where('company_id', $manager->company_id)->where('notes', 'Facture de demonstration initiale')->firstOrFail();

        $this->withToken($plainToken)
            ->getJson('/api/v1/sales-invoices?status=validated&search='.$invoice->invoice_number.'&customer_id='.$invoice->customer_id)
            ->assertOk()
            ->assertJsonPath('data.0.id', $invoice->id)
            ->assertJsonPath('data.0.invoice_number', $invoice->invoice_number);
    }

    public function test_api_actor_without_sales_permissions_cannot_read_or_create_sales_invoices(): void
    {
        $cashier = User::query()->where('email', 'caissier@nema-erp.test')->firstOrFail();
        $plainToken = $this->createApiToken($cashier);
        $invoice = SalesInvoice::query()->where('company_id', $cashier->company_id)->where('notes', 'Facture de demonstration initiale')->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $cashier->company_id)->firstOrFail();
        $product = Product::query()->where('company_id', $cashier->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $this->withToken($plainToken)
            ->getJson('/api/v1/sales-invoices')
            ->assertForbidden();

        $this->withToken($plainToken)
            ->getJson('/api/v1/sales-invoices/'.$invoice->id)
            ->assertForbidden();

        $this->withToken($plainToken)
            ->postJson('/api/v1/sales-invoices', [
                'customer_id' => $customer->id,
                'branch_id' => $cashier->branch_id,
                'invoice_date' => now()->toDateString(),
                'items' => [
                    [
                        'product_id' => $product->id,
                        'qty' => 1,
                        'unit_price' => 500,
                    ],
                ],
            ])
            ->assertForbidden();
    }

    private function createApiToken(User $user): string
    {
        $plainToken = 'nema_test_sales_api_token_'.$user->id;

        ApiToken::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'name' => 'Test API Sales',
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addDay(),
            'created_by' => $user->id,
        ]);

        return $plainToken;
    }

    private function stockBalance(int $companyId, int $branchId, int $productId): float
    {
        return (float) DB::table('stock_movements')
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->selectRaw('COALESCE(SUM(quantity_in - quantity_out), 0) as balance')
            ->value('balance');
    }
}
