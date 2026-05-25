<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductCategory;
use App\Modules\Inventory\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCatalogFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_products_page_filters_by_search_category_type_status_and_stock_state(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $category = ProductCategory::query()->create([
            'company_id' => $user->company_id,
            'name' => 'Produits filtre demo',
            'description' => 'Categorie de test pour le catalogue produit',
            'is_active' => true,
        ]);

        Product::query()->create([
            'company_id' => $user->company_id,
            'category_id' => $category->id,
            'sku' => 'PRD-FILT-01',
            'barcode' => '111111000001',
            'name' => 'Filtre produit stock actif',
            'unit' => 'carton',
            'type' => 'stockable',
            'sale_price' => 2400,
            'purchase_price' => 1800,
            'min_stock' => 5,
            'description' => 'Produit cible du filtre',
            'is_active' => true,
        ]);

        Product::query()->create([
            'company_id' => $user->company_id,
            'category_id' => $category->id,
            'sku' => 'PRD-FILT-02',
            'barcode' => '111111000002',
            'name' => 'Filtre produit service',
            'unit' => 'heure',
            'type' => 'service',
            'sale_price' => 12000,
            'purchase_price' => 0,
            'min_stock' => 0,
            'description' => 'Service de test',
            'is_active' => true,
        ]);

        Product::query()->create([
            'company_id' => $user->company_id,
            'category_id' => $category->id,
            'sku' => 'PRD-FILT-03',
            'barcode' => '111111000003',
            'name' => 'Filtre produit inactif',
            'unit' => 'carton',
            'type' => 'stockable',
            'sale_price' => 2600,
            'purchase_price' => 1900,
            'min_stock' => 5,
            'description' => 'Produit inactif de test',
            'is_active' => false,
        ]);

        $availableProduct = Product::query()->create([
            'company_id' => $user->company_id,
            'category_id' => $category->id,
            'sku' => 'PRD-FILT-04',
            'barcode' => '111111000004',
            'name' => 'Filtre produit disponible',
            'unit' => 'carton',
            'type' => 'stockable',
            'sale_price' => 2800,
            'purchase_price' => 2000,
            'min_stock' => 5,
            'description' => 'Produit disponible de test',
            'is_active' => true,
        ]);

        StockMovement::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'warehouse_id' => null,
            'product_id' => $availableProduct->id,
            'movement_type' => 'opening',
            'quantity_in' => 20,
            'quantity_out' => 0,
            'unit_cost' => 2000,
            'reason' => 'Stock de test disponible',
            'notes' => 'Creation de stock pour filtre produit',
            'movement_date' => now(),
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('products.index', [
                'search' => 'FILT',
                'category_id' => $category->id,
                'type' => 'stockable',
                'status' => 'active',
                'stock_state' => 'low',
            ]))
            ->assertOk()
            ->assertSee('Filtre produit stock actif')
            ->assertSee('PRD-FILT-01')
            ->assertSee('A surveiller')
            ->assertDontSee('Filtre produit service')
            ->assertDontSee('PRD-FILT-02')
            ->assertDontSee('Filtre produit inactif')
            ->assertDontSee('PRD-FILT-03')
            ->assertDontSee('Filtre produit disponible')
            ->assertDontSee('PRD-FILT-04');
    }

    public function test_products_page_searches_by_barcode(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        Product::query()->create([
            'company_id' => $user->company_id,
            'sku' => 'PRD-BAR-01',
            'barcode' => '998877665544',
            'name' => 'Huile recherche code barre',
            'unit' => 'bouteille',
            'type' => 'stockable',
            'sale_price' => 1500,
            'purchase_price' => 1100,
            'min_stock' => 2,
            'description' => 'Produit trouve via code barre',
            'is_active' => true,
        ]);

        Product::query()->create([
            'company_id' => $user->company_id,
            'sku' => 'PRD-BAR-02',
            'barcode' => '112233445566',
            'name' => 'Huile autre produit',
            'unit' => 'bouteille',
            'type' => 'stockable',
            'sale_price' => 1400,
            'purchase_price' => 1000,
            'min_stock' => 2,
            'description' => 'Produit de comparaison',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('products.index', [
                'search' => '998877',
            ]))
            ->assertOk()
            ->assertSee('Huile recherche code barre')
            ->assertSee('PRD-BAR-01')
            ->assertDontSee('Huile autre produit')
            ->assertDontSee('PRD-BAR-02');
    }

    public function test_products_page_can_render_kanban_view(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        Product::query()->create([
            'company_id' => $user->company_id,
            'sku' => 'PRD-KANBAN-01',
            'barcode' => '554433221100',
            'name' => 'Savon kanban',
            'unit' => 'piece',
            'type' => 'stockable',
            'sale_price' => 900,
            'purchase_price' => 650,
            'min_stock' => 4,
            'description' => 'Produit pour la vue kanban',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('products.index', [
                'view' => 'kanban',
                'search' => 'KANBAN',
            ]))
            ->assertOk()
            ->assertSee('Kanban')
            ->assertSee('Savon kanban')
            ->assertSee('Ouvrir fiche');
    }

    private function workspaceSession(User $user): array
    {
        return [
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ];
    }
}
