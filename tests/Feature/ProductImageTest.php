<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
                'barcode' => '223900000001',
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
            ->assertSee('Produit photo test');

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
                'barcode' => '223900000099',
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
}
