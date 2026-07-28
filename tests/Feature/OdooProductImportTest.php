<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Imports\Odoo\Contracts\OdooClient;
use App\Modules\Core\Imports\Odoo\Jobs\ProcessOdooProductImportBatch;
use App\Modules\Core\Imports\Odoo\Models\OdooConnection;
use App\Modules\Core\Imports\Odoo\Models\OdooProductMapping;
use App\Modules\Core\Imports\Odoo\Services\OdooClientFactory;
use App\Modules\Core\Imports\Odoo\Services\OdooProductImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OdooProductImportTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_manager_can_save_connection_and_start_resumable_import(): void
    {
        Queue::fake();
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $this->actingAs($user)->withSession([
            'current_tenant_id' => $user->tenant_id,
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ]);

        $this->get(route('imports.odoo.index'))
            ->assertOk()
            ->assertSee('Odoo → Nema ERP');

        $this->post(route('imports.odoo.connections.save'), [
            'name' => 'Odoo Test',
            'protocol' => 'jsonrpc',
            'url' => 'https://odoo.example.test',
            'database' => 'odoo_test',
            'username' => 'api@example.test',
            'secret' => 'secret-api-key',
            'batch_size' => 100,
            'verify_ssl' => 1,
            'import_images' => 1,
            'import_stock' => 1,
            'is_active' => 1,
        ])->assertRedirect(route('imports.odoo.index'));

        $connection = OdooConnection::query()->where('name', 'Odoo Test')->firstOrFail();
        $this->assertSame('secret-api-key', $connection->secret);
        $this->assertStringNotContainsString('secret-api-key', (string) DB::table('odoo_connections')->where('id', $connection->id)->value('secret'));

        $this->post(route('imports.odoo.connections.start', $connection), ['mode' => 'full'])
            ->assertRedirect(route('imports.odoo.index'));

        $run = $connection->runs()->firstOrFail();
        $this->assertSame('queued', $run->status);
        $this->assertSame('full', $run->mode);
        Queue::assertPushed(ProcessOdooProductImportBatch::class, fn (ProcessOdooProductImportBatch $job): bool => $job->runId === $run->id);

        $this->getJson(route('imports.odoo.runs.status', $run))
            ->assertOk()
            ->assertJsonPath('status', 'queued')
            ->assertJsonPath('progress', 0);
    }

    public function test_full_import_preserves_product_data_and_second_run_does_not_duplicate(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $connection = OdooConnection::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'name' => 'Odoo Fake',
            'protocol' => 'jsonrpc',
            'url' => 'https://odoo.example.test',
            'database' => 'fake',
            'username' => 'fake',
            'secret' => 'fake',
            'batch_size' => 25,
            'verify_ssl' => true,
            'import_images' => false,
            'import_stock' => false,
            'is_active' => true,
        ]);

        $client = new FakeOdooProductClient;
        $this->app->instance(OdooClientFactory::class, new class($client) extends OdooClientFactory
        {
            public function __construct(private readonly OdooClient $client) {}

            public function make(OdooConnection $connection): OdooClient
            {
                return $this->client;
            }
        });

        $service = $this->app->make(OdooProductImportService::class);
        $run = $service->createRun($connection, 'full', $user);
        while ($service->processNextBatch($run)) {
            $run->refresh();
        }

        $run->refresh();
        $product = Product::query()->where('company_id', $user->company_id)->where('sku', 'SKU-001')->firstOrFail();
        $this->assertSame('completed', $run->status);
        $this->assertSame(2, $run->processed_count);
        $this->assertSame('Produit Odoo', $product->name);
        $this->assertSame('5901234123457', $product->barcode);
        $this->assertSame('12500.00', $product->sale_price);
        $this->assertSame('7000.00', $product->purchase_price);
        $this->assertSame('Description commerciale', $product->sales_description);
        $this->assertCount(2, OdooProductMapping::query()->where('odoo_connection_id', $connection->id)->get());

        $second = $service->createRun($connection->fresh(), 'full', $user);
        while ($service->processNextBatch($second)) {
            $second->refresh();
        }

        $this->assertSame(1, Product::query()->where('company_id', $user->company_id)->where('sku', 'SKU-001')->count());
        $this->assertSame(2, $second->fresh()->skipped_count);
    }

    public function test_full_import_repairs_crossed_mappings_before_updating_skus(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $connection = OdooConnection::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'name' => 'Odoo Mapping Repair',
            'protocol' => 'jsonrpc',
            'url' => 'https://odoo.example.test',
            'database' => 'fake',
            'username' => 'fake',
            'secret' => 'fake',
            'batch_size' => 25,
            'verify_ssl' => true,
            'import_images' => false,
            'import_stock' => false,
            'is_active' => true,
        ]);

        $first = Product::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'sku' => 'ODOO-P-'.$connection->id.'-11',
            'barcode' => 'BARCODE-11',
            'name' => 'Premier produit',
        ]);
        $second = Product::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'sku' => 'ODOO-P-'.$connection->id.'-21',
            'barcode' => 'BARCODE-21',
            'name' => 'Deuxieme produit',
        ]);

        OdooProductMapping::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'odoo_connection_id' => $connection->id,
            'product_id' => $second->id,
            'odoo_model' => 'product.template',
            'odoo_id' => 10,
            'odoo_template_id' => 10,
        ]);
        OdooProductMapping::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'odoo_connection_id' => $connection->id,
            'product_id' => $first->id,
            'odoo_model' => 'product.template',
            'odoo_id' => 20,
            'odoo_template_id' => 20,
        ]);

        $client = new FakeOdooCrossedMappingClient;
        $this->app->instance(OdooClientFactory::class, new class($client) extends OdooClientFactory
        {
            public function __construct(private readonly OdooClient $client) {}

            public function make(OdooConnection $connection): OdooClient
            {
                return $this->client;
            }
        });

        $service = $this->app->make(OdooProductImportService::class);
        $run = $service->createRun($connection, 'full', $user);
        while ($service->processNextBatch($run)) {
            $run->refresh();
        }

        $this->assertSame('completed', $run->fresh()->status);
        $this->assertSame(0, $run->fresh()->failed_count);
        $this->assertSame($first->id, OdooProductMapping::query()
            ->where('odoo_connection_id', $connection->id)
            ->where('odoo_model', 'product.template')
            ->where('odoo_id', 10)
            ->value('product_id'));
        $this->assertSame($second->id, OdooProductMapping::query()
            ->where('odoo_connection_id', $connection->id)
            ->where('odoo_model', 'product.template')
            ->where('odoo_id', 20)
            ->value('product_id'));
        $this->assertSame($first->id, OdooProductMapping::query()
            ->where('odoo_connection_id', $connection->id)
            ->where('odoo_model', 'product.product')
            ->where('odoo_id', 11)
            ->value('product_id'));
        $this->assertSame($second->id, OdooProductMapping::query()
            ->where('odoo_connection_id', $connection->id)
            ->where('odoo_model', 'product.product')
            ->where('odoo_id', 21)
            ->value('product_id'));
    }

    public function test_manager_browser_can_advance_a_queued_import_when_no_worker_is_available(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $connection = OdooConnection::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'name' => 'Odoo Browser Fallback',
            'protocol' => 'jsonrpc',
            'url' => 'https://odoo.example.test',
            'database' => 'fake',
            'username' => 'fake',
            'secret' => 'fake',
            'batch_size' => 25,
            'verify_ssl' => true,
            'import_images' => false,
            'import_stock' => false,
            'is_active' => true,
        ]);

        $client = new FakeOdooProductClient;
        $this->app->instance(OdooClientFactory::class, new class($client) extends OdooClientFactory
        {
            public function __construct(private readonly OdooClient $client) {}

            public function make(OdooConnection $connection): OdooClient
            {
                return $this->client;
            }
        });

        $run = $this->app->make(OdooProductImportService::class)->createRun($connection, 'full', $user);
        $this->actingAs($user)->withSession([
            'current_tenant_id' => $user->tenant_id,
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ]);

        $this->postJson(route('imports.odoo.runs.advance', $run))
            ->assertOk()
            ->assertJsonPath('status', 'running')
            ->assertJsonPath('phase', 'templates')
            ->assertJsonPath('processed_count', 1)
            ->assertJsonPath('worker_fallback', false);
    }
}

class FakeOdooProductClient implements OdooClient
{
    public function authenticate(): int
    {
        return 7;
    }

    public function version(): array
    {
        return ['server_version' => '18.0'];
    }

    public function supportedFields(string $model, array $fields): array
    {
        return $fields;
    }

    public function searchCount(string $model, array $domain): int
    {
        return 1;
    }

    public function searchRead(string $model, array $domain, array $fields, int $limit = 0, int $offset = 0, string $order = 'id asc'): array
    {
        if (in_array($model, ['product.supplierinfo'], true)) {
            return [];
        }

        $cursor = collect($domain)->first(fn (array $clause): bool => ($clause[0] ?? null) === 'id' && ($clause[1] ?? null) === '>');
        if ($cursor) {
            return [];
        }

        if ($model === 'product.template') {
            return [[
                'id' => 10,
                'name' => 'Produit Odoo',
                'default_code' => 'SKU-001',
                'barcode' => '5901234123457',
                'categ_id' => [5, 'Produits importes'],
                'list_price' => 12500,
                'standard_price' => 7000,
                'taxes_id' => [],
                'supplier_taxes_id' => [],
                'uom_id' => [1, 'Unite'],
                'uom_po_id' => [1, 'Unite'],
                'description' => 'Description generale',
                'description_sale' => 'Description commerciale',
                'description_purchase' => 'Description achat',
                'active' => true,
                'sale_ok' => true,
                'purchase_ok' => true,
                'detailed_type' => 'product',
                'product_variant_ids' => [11],
                'attribute_line_ids' => [],
                'write_date' => '2026-07-22 10:00:00',
                'tracking' => 'none',
                'invoice_policy' => 'order',
            ]];
        }

        if ($model === 'product.product') {
            return [[
                'id' => 11,
                'product_tmpl_id' => [10, 'Produit Odoo'],
                'name' => 'Produit Odoo',
                'display_name' => 'Produit Odoo',
                'default_code' => 'SKU-001',
                'barcode' => '5901234123457',
                'lst_price' => 12500,
                'standard_price' => 7000,
                'active' => true,
                'qty_available' => 18,
                'product_template_attribute_value_ids' => [],
                'product_template_variant_value_ids' => [],
                'write_date' => '2026-07-22 10:00:00',
            ]];
        }

        return [];
    }

    public function read(string $model, array $ids, array $fields): array
    {
        return [];
    }
}

class FakeOdooCrossedMappingClient extends FakeOdooProductClient
{
    public function searchCount(string $model, array $domain): int
    {
        return 2;
    }

    public function searchRead(string $model, array $domain, array $fields, int $limit = 0, int $offset = 0, string $order = 'id asc'): array
    {
        if ($model === 'product.supplierinfo') {
            return [];
        }

        $cursor = collect($domain)->first(fn (array $clause): bool => ($clause[0] ?? null) === 'id' && ($clause[1] ?? null) === '>');
        if ($cursor) {
            return [];
        }

        if ($model === 'product.template') {
            return [
                $this->template(10, 11, 'Premier produit'),
                $this->template(20, 21, 'Deuxieme produit'),
            ];
        }

        if ($model === 'product.product') {
            return [
                $this->variant(11, 10, 'Premier produit'),
                $this->variant(21, 20, 'Deuxieme produit'),
            ];
        }

        return [];
    }

    private function template(int $id, int $variantId, string $name): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'default_code' => null,
            'barcode' => 'BARCODE-'.$variantId,
            'categ_id' => false,
            'list_price' => 1000,
            'standard_price' => 500,
            'taxes_id' => [],
            'supplier_taxes_id' => [],
            'uom_id' => [1, 'Unite'],
            'uom_po_id' => [1, 'Unite'],
            'active' => true,
            'sale_ok' => true,
            'purchase_ok' => true,
            'detailed_type' => 'product',
            'product_variant_ids' => [$variantId],
            'attribute_line_ids' => [],
            'write_date' => '2026-07-28 10:00:00',
            'tracking' => 'none',
            'invoice_policy' => 'order',
        ];
    }

    private function variant(int $id, int $templateId, string $name): array
    {
        return [
            'id' => $id,
            'product_tmpl_id' => [$templateId, $name],
            'name' => $name,
            'display_name' => $name,
            'default_code' => null,
            'barcode' => 'BARCODE-'.$id,
            'lst_price' => 1000,
            'standard_price' => 500,
            'active' => true,
            'qty_available' => 1,
            'product_template_attribute_value_ids' => [],
            'product_template_variant_value_ids' => [],
            'write_date' => '2026-07-28 10:00:00',
        ];
    }
}
