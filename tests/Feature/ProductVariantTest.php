<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductAttribute;
use App\Modules\Catalog\Models\ProductAttributeValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductVariantTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_manager_can_manage_product_attributes_and_values(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('product-attributes.store'), [
                'code' => 'COLOR',
                'name' => 'Couleur',
                'is_active' => '1',
            ])
            ->assertRedirect(route('product-attributes.index'))
            ->assertSessionHas('success');

        $attribute = ProductAttribute::query()
            ->where('company_id', $user->company_id)
            ->where('code', 'COLOR')
            ->firstOrFail();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('product-attributes.values.store', $attribute), [
                'value' => 'Rouge',
                'code' => 'RED',
                'is_active' => '1',
            ])
            ->assertRedirect(route('product-attributes.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('product_attributes', [
            'id' => $attribute->id,
            'company_id' => $user->company_id,
            'name' => 'Couleur',
        ]);

        $this->assertDatabaseHas('product_attribute_values', [
            'company_id' => $user->company_id,
            'product_attribute_id' => $attribute->id,
            'value' => 'Rouge',
            'code' => 'RED',
        ]);
    }

    public function test_manager_can_create_variant_product_with_parent_and_attribute_values(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        [$colorRed, $sizeOneLiter] = $this->variantValues($user);

        $parent = Product::query()->create([
            'company_id' => $user->company_id,
            'sku' => 'PRD-VAR-PARENT-01',
            'barcode' => '660000000001',
            'name' => 'Jus demo',
            'unit' => 'unite',
            'type' => 'stockable',
            'sale_price' => 1800,
            'purchase_price' => 1200,
            'min_stock' => 2,
            'description' => 'Produit parent pour variantes',
            'is_active' => true,
            'sale_ok' => true,
            'purchase_ok' => true,
        ]);

        $response = $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('products.store'), [
                'name' => 'Jus demo rouge 1L',
                'sku' => 'PRD-VAR-0001',
                'barcode' => '660000000002',
                'unit' => 'unite',
                'type' => 'stockable',
                'sale_price' => 1900,
                'purchase_price' => 1250,
                'min_stock' => 1,
                'sale_ok' => '1',
                'purchase_ok' => '1',
                'invoice_policy' => 'ordered',
                'tracking_type' => 'none',
                'parent_product_id' => $parent->id,
                'variant_value_ids' => [$colorRed->id, $sizeOneLiter->id],
            ]);

        $variant = Product::query()->where('sku', 'PRD-VAR-0001')->firstOrFail();

        $response
            ->assertRedirect(route('products.show', $variant))
            ->assertSessionHas('success');

        $this->assertTrue($variant->is_variant);
        $this->assertSame($parent->id, $variant->parent_product_id);
        $this->assertSame('Couleur: Rouge · Format: 1L', $variant->variant_label);
        $this->assertSame('Jus demo · Couleur: Rouge · Format: 1L', $variant->fresh()->load('parent')->display_name);

        $this->assertDatabaseHas('product_variant_attribute_value', [
            'product_id' => $variant->id,
            'product_attribute_value_id' => $colorRed->id,
        ]);
        $this->assertDatabaseHas('product_variant_attribute_value', [
            'product_id' => $variant->id,
            'product_attribute_value_id' => $sizeOneLiter->id,
        ]);
    }

    public function test_duplicate_variant_combination_is_rejected_for_same_parent(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        [$colorRed, $sizeOneLiter] = $this->variantValues($user);

        $parent = Product::query()->create([
            'company_id' => $user->company_id,
            'sku' => 'PRD-VAR-PARENT-02',
            'barcode' => '660000000011',
            'name' => 'Boisson demo',
            'unit' => 'unite',
            'type' => 'stockable',
            'sale_price' => 2100,
            'purchase_price' => 1400,
            'min_stock' => 2,
            'description' => 'Autre parent de variantes',
            'is_active' => true,
            'sale_ok' => true,
            'purchase_ok' => true,
        ]);

        Product::query()->create([
            'company_id' => $user->company_id,
            'parent_product_id' => $parent->id,
            'is_variant' => true,
            'variant_label' => 'Couleur: Rouge · Format: 1L',
            'variant_signature' => implode('-', [$colorRed->id, $sizeOneLiter->id]),
            'sku' => 'PRD-VAR-EXIST-01',
            'barcode' => '660000000012',
            'name' => 'Boisson demo rouge 1L',
            'unit' => 'unite',
            'type' => 'stockable',
            'sale_price' => 2150,
            'purchase_price' => 1450,
            'min_stock' => 1,
            'description' => 'Variante existante',
            'is_active' => true,
            'sale_ok' => true,
            'purchase_ok' => true,
        ])->attributeValues()->sync([$colorRed->id, $sizeOneLiter->id]);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->from(route('products.create'))
            ->post(route('products.store'), [
                'name' => 'Boisson demo rouge 1L bis',
                'sku' => 'PRD-VAR-EXIST-02',
                'barcode' => '660000000013',
                'unit' => 'unite',
                'type' => 'stockable',
                'sale_price' => 2200,
                'purchase_price' => 1500,
                'min_stock' => 1,
                'sale_ok' => '1',
                'purchase_ok' => '1',
                'invoice_policy' => 'ordered',
                'tracking_type' => 'none',
                'parent_product_id' => $parent->id,
                'variant_value_ids' => [$sizeOneLiter->id, $colorRed->id],
            ])
            ->assertRedirect(route('products.create'))
            ->assertSessionHasErrors('variant_value_ids');

        $this->assertSame(1, Product::query()->where('company_id', $user->company_id)->where('parent_product_id', $parent->id)->count());
    }

    public function test_product_show_page_displays_variant_family_information(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        [$colorRed, $sizeOneLiter] = $this->variantValues($user);

        $parent = Product::query()->create([
            'company_id' => $user->company_id,
            'sku' => 'PRD-VAR-PARENT-03',
            'barcode' => '660000000021',
            'name' => 'Lait demo',
            'unit' => 'brique',
            'type' => 'stockable',
            'sale_price' => 950,
            'purchase_price' => 700,
            'min_stock' => 3,
            'description' => 'Parent pour affichage fiche',
            'is_active' => true,
            'sale_ok' => true,
            'purchase_ok' => true,
        ]);

        $variant = Product::query()->create([
            'company_id' => $user->company_id,
            'parent_product_id' => $parent->id,
            'is_variant' => true,
            'variant_label' => 'Couleur: Rouge · Format: 1L',
            'variant_signature' => implode('-', [$colorRed->id, $sizeOneLiter->id]),
            'sku' => 'PRD-VAR-SHOW-01',
            'barcode' => '660000000022',
            'name' => 'Lait demo rouge 1L',
            'unit' => 'brique',
            'type' => 'stockable',
            'sale_price' => 990,
            'purchase_price' => 720,
            'min_stock' => 2,
            'description' => 'Variante pour test affichage',
            'is_active' => true,
            'sale_ok' => true,
            'purchase_ok' => true,
        ]);
        $variant->attributeValues()->sync([$colorRed->id, $sizeOneLiter->id]);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('products.show', $variant))
            ->assertOk()
            ->assertSee('Famille et variantes')
            ->assertSee('Variante de '.$parent->name)
            ->assertSee('Configuration de la variante')
            ->assertSee('Couleur: Rouge')
            ->assertSee('Format: 1L');

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('products.show', $parent))
            ->assertOk()
            ->assertSee('Variantes disponibles')
            ->assertSee('Lait demo · Couleur: Rouge · Format: 1L');
    }

    private function variantValues(User $user): array
    {
        $color = ProductAttribute::query()->create([
            'company_id' => $user->company_id,
            'code' => 'COLOR-TEST',
            'name' => 'Couleur',
            'is_active' => true,
        ]);

        $format = ProductAttribute::query()->create([
            'company_id' => $user->company_id,
            'code' => 'FORMAT-TEST',
            'name' => 'Format',
            'is_active' => true,
        ]);

        $colorRed = ProductAttributeValue::query()->create([
            'company_id' => $user->company_id,
            'product_attribute_id' => $color->id,
            'value' => 'Rouge',
            'code' => 'RED',
            'is_active' => true,
        ]);

        $sizeOneLiter = ProductAttributeValue::query()->create([
            'company_id' => $user->company_id,
            'product_attribute_id' => $format->id,
            'value' => '1L',
            'code' => '1L',
            'is_active' => true,
        ]);

        return [$colorRed, $sizeOneLiter];
    }

    private function workspaceSession(User $user): array
    {
        return [
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ];
    }
}
