<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_used_product_can_be_archived_but_not_deleted(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $product = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->patch(route('products.archive', $product))
            ->assertRedirect(route('products.show', $product))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'is_active' => false,
        ]);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->delete(route('products.destroy', $product))
            ->assertRedirect(route('products.show', $product))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
        ]);
    }

    public function test_archived_product_can_be_reactivated(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $product = Product::query()->create([
            'company_id' => $user->company_id,
            'sku' => 'PRD-LIFE-0001',
            'barcode' => '550000000001',
            'name' => 'Produit archive a reactiver',
            'unit' => 'piece',
            'type' => 'stockable',
            'sale_price' => 1800,
            'purchase_price' => 1200,
            'min_stock' => 2,
            'description' => 'Produit pour test de reactivation',
            'is_active' => false,
        ]);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->patch(route('products.restore', $product))
            ->assertRedirect(route('products.show', $product))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'is_active' => true,
        ]);
    }

    public function test_unused_product_can_be_deleted(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $product = Product::query()->create([
            'company_id' => $user->company_id,
            'sku' => 'PRD-LIFE-0002',
            'barcode' => '550000000002',
            'name' => 'Produit vierge a supprimer',
            'unit' => 'piece',
            'type' => 'stockable',
            'sale_price' => 2100,
            'purchase_price' => 1500,
            'min_stock' => 1,
            'description' => 'Produit pour test de suppression',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->delete(route('products.destroy', $product))
            ->assertRedirect(route('products.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('products', [
            'id' => $product->id,
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
