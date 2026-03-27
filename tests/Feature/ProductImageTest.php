<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}

