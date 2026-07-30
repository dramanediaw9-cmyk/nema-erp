<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\ProductCatalogCleanupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCatalogCleanupTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_quality_rules_detect_unusable_names_and_missing_prices(): void
    {
        $service = app(ProductCatalogCleanupService::class);

        $this->assertSame('nom_invalide', $service->invalidReasonForValues('_\'&àé-&-éè&éà', 1));
        $this->assertSame('prix_invalide', $service->invalidReasonForValues('Lait entier 1L', 0));
        $this->assertNull($service->invalidReasonForValues('-G', 500, '-Novarino Glace Tiramisu 90g', 'Taille: G'));
        $this->assertNull($service->invalidReasonForValues('7Up 33cl', 500));
    }

    public function test_cleanup_deletes_unused_invalid_products_and_archives_used_parents(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $attributes = [
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'unit' => 'piece',
            'type' => 'stockable',
            'purchase_price' => 0,
            'min_stock' => 0,
            'is_active' => true,
        ];

        $invalidName = Product::query()->create($attributes + [
            'sku' => 'CLEAN-JUNK',
            'name' => '_\'&àé-&-éè&éà',
            'sale_price' => 1,
        ]);
        $invalidPrice = Product::query()->create($attributes + [
            'sku' => 'CLEAN-ZERO',
            'name' => 'Produit sans prix',
            'sale_price' => 0,
        ]);
        $usedParent = Product::query()->create($attributes + [
            'sku' => 'CLEAN-PARENT',
            'name' => '-',
            'sale_price' => 1,
        ]);
        $child = Product::query()->create($attributes + [
            'parent_product_id' => $usedParent->id,
            'sku' => 'CLEAN-CHILD',
            'name' => 'Variante correcte',
            'sale_price' => 1000,
            'is_variant' => true,
            'variant_label' => 'Taille: G',
        ]);
        $valid = Product::query()->create($attributes + [
            'sku' => 'CLEAN-VALID',
            'name' => 'Riz parfume 5kg',
            'sale_price' => 6500,
        ]);

        $response = $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('products.cleanup-invalid'));

        $response->assertRedirect(route('products.index'))->assertSessionHas('success');
        $this->assertDatabaseMissing('products', ['id' => $invalidName->id]);
        $this->assertDatabaseMissing('products', ['id' => $invalidPrice->id]);
        $this->assertDatabaseHas('products', ['id' => $usedParent->id, 'is_active' => false]);
        $this->assertDatabaseHas('products', ['id' => $child->id]);
        $this->assertDatabaseHas('products', ['id' => $valid->id, 'is_active' => true]);
    }

    private function workspaceSession(User $user): array
    {
        return [
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ];
    }
}

