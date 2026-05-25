<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductCategory;
use App\Modules\Core\Audit\Models\ActivityLog;
use App\Support\ActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class ProductImageTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_company_admin_can_create_product_with_image(): void
    {
        Storage::fake('public');

        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $category = ProductCategory::query()->where('company_id', $user->company_id)->firstOrFail();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO7Z0j8AAAAASUVORK5CYII=');

        $response = $this->actingAs($user)
            ->withSession([
                'current_company_id' => $user->company_id,
                'current_branch_id' => $user->branch_id,
            ])
            ->post(route('products.store'), [
                'category_id' => $category->id,
                'sku' => 'PRD-IMG-0001',
                'barcode' => '223990000001',
                'name' => 'Produit photo test',
                'unit' => 'piece',
                'type' => 'stockable',
                'sale_price' => 1200,
                'purchase_price' => 900,
                'min_stock' => 2,
                'description' => 'Produit avec photo',
                'is_active' => 1,
                'image' => UploadedFile::fake()->createWithContent('photo-produit.png', $png),
            ]);

        $product = Product::query()
            ->where('company_id', $user->company_id)
            ->where('sku', 'PRD-IMG-0001')
            ->firstOrFail();

        $response
            ->assertRedirect(route('products.show', $product))
            ->assertSessionHas('success');

        $this->assertNotNull($product->image_path);
        $this->assertSame('public', $product->image_disk);
        $this->assertNotNull($product->image_url);
        Storage::disk('public')->assertExists($product->image_path);

        $this->actingAs($user)
            ->withSession([
                'current_company_id' => $user->company_id,
                'current_branch_id' => $user->branch_id,
            ])
            ->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('Fiche produit')
            ->assertSee('Produit photo test')
            ->assertSee('Historique des actions')
            ->assertSee('Creation produit');

        $this->get($product->image_url)
            ->assertOk();
    }

    public function test_image_url_keeps_application_base_path_when_served_from_subdirectory(): void
    {
        $product = Product::query()->whereNotNull('image_path')->firstOrFail();

        $request = Request::create('/erp/public/point-de-vente/vente', 'GET', [], [], [], [
            'HTTP_HOST' => 'localhost',
            'HTTPS' => 'off',
            'SCRIPT_NAME' => '/erp/public/index.php',
            'PHP_SELF' => '/erp/public/index.php',
            'SCRIPT_FILENAME' => base_path('public/index.php'),
        ]);

        app('url')->setRequest($request);

        $this->assertSame(
            'http://localhost/erp/public/media/produits/'.$product->image_path,
            $product->image_url,
        );
    }

    public function test_product_images_can_use_a_configured_cloud_disk(): void
    {
        Storage::fake('s3');
        config()->set('nema.product_media_disk', 's3');

        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $category = ProductCategory::query()->where('company_id', $user->company_id)->firstOrFail();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO7Z0j8AAAAASUVORK5CYII=');

        $this->actingAs($user)
            ->withSession([
                'current_company_id' => $user->company_id,
                'current_branch_id' => $user->branch_id,
            ])
            ->post(route('products.store'), [
                'category_id' => $category->id,
                'sku' => 'PRD-IMG-S3-0001',
                'barcode' => '223990000099',
                'name' => 'Produit photo cloud',
                'unit' => 'piece',
                'type' => 'stockable',
                'sale_price' => 2200,
                'purchase_price' => 1500,
                'min_stock' => 1,
                'description' => 'Produit avec image sur disque cloud',
                'is_active' => 1,
                'image' => UploadedFile::fake()->createWithContent('photo-cloud.png', $png),
            ])
            ->assertRedirect();

        $product = Product::query()
            ->where('company_id', $user->company_id)
            ->where('sku', 'PRD-IMG-S3-0001')
            ->firstOrFail();

        $this->assertSame('s3', $product->image_disk);
        Storage::disk('s3')->assertExists($product->image_path);

        $this->get($product->image_url)
            ->assertOk();
    }

    public function test_product_creation_rolls_back_and_cleans_uploaded_image_when_transaction_fails(): void
    {
        Storage::fake('public');

        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $category = ProductCategory::query()->where('company_id', $user->company_id)->firstOrFail();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO7Z0j8AAAAASUVORK5CYII=');

        $this->mock(ActivityLogger::class, function (MockInterface $mock): void {
            $mock->shouldReceive('log')
                ->once()
                ->andThrow(new RuntimeException('Simulated product creation failure.'));
        });

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($user)
                ->withSession([
                    'current_company_id' => $user->company_id,
                    'current_branch_id' => $user->branch_id,
                ])
                ->post(route('products.store'), [
                    'category_id' => $category->id,
                    'sku' => 'PRD-IMG-ROLLBACK-01',
                    'barcode' => '223990001001',
                    'name' => 'Produit rollback creation',
                    'unit' => 'piece',
                    'type' => 'stockable',
                    'sale_price' => 1200,
                    'purchase_price' => 900,
                    'min_stock' => 2,
                    'description' => 'Produit qui doit rollback',
                    'is_active' => 1,
                    'image' => UploadedFile::fake()->createWithContent('photo-rollback-create.png', $png),
                ]);

            $this->fail('Expected product creation to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated product creation failure.', $exception->getMessage());
        }

        $this->assertDatabaseMissing('products', [
            'company_id' => $user->company_id,
            'sku' => 'PRD-IMG-ROLLBACK-01',
        ]);
        $this->assertSame([], Storage::disk('public')->allFiles('products'));
    }

    public function test_product_update_rolls_back_and_keeps_previous_image_when_transaction_fails(): void
    {
        Storage::fake('public');

        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $product = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-0001')->firstOrFail();
        $category = ProductCategory::query()->where('company_id', $user->company_id)->firstOrFail();
        $originalName = $product->name;
        $originalPath = 'products/original-product-image.png';
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO7Z0j8AAAAASUVORK5CYII=');

        Storage::disk('public')->put($originalPath, $png);
        $product->update([
            'category_id' => $category->id,
            'image_path' => $originalPath,
            'image_disk' => 'public',
        ]);

        $this->mock(ActivityLogger::class, function (MockInterface $mock): void {
            $mock->shouldReceive('log')
                ->once()
                ->andThrow(new RuntimeException('Simulated product update failure.'));
        });

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($user)
                ->withSession([
                    'current_company_id' => $user->company_id,
                    'current_branch_id' => $user->branch_id,
                ])
                ->put(route('products.update', $product), [
                    'category_id' => $category->id,
                    'sku' => $product->sku,
                    'barcode' => $product->barcode,
                    'name' => 'Produit rollback mise a jour',
                    'unit' => $product->unit,
                    'type' => $product->type,
                    'sale_price' => $product->sale_price,
                    'purchase_price' => $product->purchase_price,
                    'min_stock' => $product->min_stock,
                    'is_active' => $product->is_active ? 1 : 0,
                    'image' => UploadedFile::fake()->createWithContent('photo-rollback-update.png', $png),
                ]);

            $this->fail('Expected product update to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated product update failure.', $exception->getMessage());
        }

        $product->refresh();

        $this->assertSame($originalName, $product->name);
        $this->assertSame($originalPath, $product->image_path);
        Storage::disk('public')->assertExists($originalPath);
        $this->assertSame([$originalPath], Storage::disk('public')->allFiles('products'));
    }

    public function test_product_detail_page_can_show_recent_activity_history(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $product = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        ActivityLog::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'user_id' => $user->id,
            'action' => 'products.update',
            'description' => 'Mise a jour produit test',
            'subject_type' => $product->getMorphClass(),
            'subject_id' => $product->id,
            'properties' => ['source' => 'test'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ]);

        $this->actingAs($user)
            ->withSession([
                'current_company_id' => $user->company_id,
                'current_branch_id' => $user->branch_id,
            ])
            ->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('Historique des actions')
            ->assertSee('Mise a jour produit test');
    }
}
