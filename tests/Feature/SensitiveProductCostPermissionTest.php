<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductSupplier;
use App\Modules\Core\Access\Models\Permission;
use App\Modules\Core\Access\Models\Role;
use App\Modules\Partners\Models\Partner;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SensitiveProductCostPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_operations_profile_cannot_see_product_costs_in_catalog_or_detail(): void
    {
        $operations = User::query()->where('email', 'ops@nema-erp.test')->firstOrFail();
        $product = Product::query()->where('company_id', $operations->company_id)->where('sku', 'PRD-0001')->firstOrFail();
        $supplier = Partner::query()->suppliers()->where('company_id', $operations->company_id)->firstOrFail();

        $product->update(['purchase_price' => 4321]);
        ProductSupplier::query()->updateOrCreate(
            [
                'product_id' => $product->id,
                'supplier_id' => $supplier->id,
            ],
            [
                'tenant_id' => $operations->tenant_id,
                'company_id' => $operations->company_id,
                'supplier_product_code' => 'SUP-HIDDEN-01',
                'supplier_product_name' => 'Cout masque',
                'min_qty' => 1,
                'unit_cost' => 3876,
                'lead_time_days' => 4,
                'is_preferred' => true,
            ]
        );

        $this->actingAs($operations)
            ->withSession($this->workspaceSession($operations))
            ->get(route('products.index'))
            ->assertOk()
            ->assertDontSee('PU achat')
            ->assertDontSee('4 321 XOF');

        $this->actingAs($operations)
            ->withSession($this->workspaceSession($operations))
            ->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('Couts confidentiels')
            ->assertSee('Couts fournisseurs masques')
            ->assertDontSee('Prix d achat')
            ->assertDontSee('3 876 XOF');
    }

    public function test_product_editor_without_cost_permission_keeps_existing_costs_on_update(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $product = Product::query()->where('company_id', $manager->company_id)->where('sku', 'PRD-0001')->firstOrFail();
        $supplier = Partner::query()->suppliers()->where('company_id', $manager->company_id)->firstOrFail();

        $product->update([
            'purchase_price' => 1999,
            'sale_price' => 2500,
            'min_stock' => 2,
        ]);

        ProductSupplier::query()->updateOrCreate(
            [
                'product_id' => $product->id,
                'supplier_id' => $supplier->id,
            ],
            [
                'tenant_id' => $manager->tenant_id,
                'company_id' => $manager->company_id,
                'supplier_product_code' => 'SUP-KEEP-01',
                'supplier_product_name' => 'Libelle fournisseur conserve',
                'min_qty' => 2,
                'unit_cost' => 1444,
                'lead_time_days' => 6,
                'is_preferred' => true,
            ]
        );

        $editor = UserFactory::new()->create([
            'tenant_id' => $manager->tenant_id,
            'company_id' => $manager->company_id,
            'branch_id' => $manager->branch_id,
            'email' => 'product-editor@nema-erp.test',
            'name' => 'Product Editor',
            'is_active' => true,
        ]);

        $role = Role::query()->create([
            'tenant_id' => $manager->tenant_id,
            'company_id' => $manager->company_id,
            'name' => 'Editeur produits sans couts',
            'slug' => 'product_editor_without_costs',
            'description' => 'Peut gerer les produits sans voir les couts sensibles',
            'is_system' => false,
        ]);
        $role->permissions()->sync(
            Permission::query()->whereIn('slug', [
                'products.view',
                'products.manage',
                'categories.view',
                'suppliers.view',
            ])->pluck('id')->all()
        );
        $editor->roles()->attach($role);

        $response = $this->actingAs($editor)
            ->withSession($this->workspaceSession($editor))
            ->put(route('products.update', $product), [
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'name' => 'Produit renomme sans cout visible',
                'category_id' => $product->category_id,
                'type' => $product->type,
                'unit' => $product->unit,
                'parent_product_id' => $product->parent_product_id,
                'sales_unit_name' => $product->sales_unit_name,
                'sales_unit_ratio' => $product->sales_unit_ratio,
                'purchase_unit_name' => $product->purchase_unit_name,
                'purchase_unit_ratio' => $product->purchase_unit_ratio,
                'sale_ok' => $product->sale_ok ? '1' : '0',
                'purchase_ok' => $product->purchase_ok ? '1' : '0',
                'sale_blocked' => $product->sale_blocked ? '1' : '0',
                'sale_block_reason' => $product->sale_block_reason,
                'purchase_blocked' => $product->purchase_blocked ? '1' : '0',
                'purchase_block_reason' => $product->purchase_block_reason,
                'invoice_policy' => $product->invoice_policy,
                'tracking_type' => $product->tracking_type,
                'sale_price' => $product->sale_price,
                'sale_tax_rule_id' => $product->sale_tax_rule_id,
                'purchase_tax_rule_id' => $product->purchase_tax_rule_id,
                'min_stock' => $product->min_stock,
                'auto_replenish' => $product->auto_replenish ? '1' : '0',
                'reorder_max_qty' => $product->reorder_max_qty,
                'reorder_multiple_qty' => $product->reorder_multiple_qty,
                'purchase_lead_time_days' => $product->purchase_lead_time_days,
                'description' => $product->description,
                'sales_description' => $product->sales_description,
                'purchase_description' => $product->purchase_description,
                'internal_notes' => 'Edition sans exposition des couts.',
                'is_active' => $product->is_active ? '1' : '0',
                'supplier_infos' => [[
                    'supplier_id' => (string) $supplier->id,
                    'supplier_product_code' => 'SUP-KEEP-01',
                    'supplier_product_name' => 'Libelle fournisseur conserve',
                    'min_qty' => '2',
                    'lead_time_days' => '6',
                    'is_preferred' => '1',
                ]],
            ]);

        $response->assertRedirect(route('products.show', $product));

        $product->refresh();
        $supplierInfo = ProductSupplier::query()
            ->where('product_id', $product->id)
            ->where('supplier_id', $supplier->id)
            ->firstOrFail();

        $this->assertSame('Produit renomme sans cout visible', $product->name);
        $this->assertSame(1999.0, (float) $product->purchase_price);
        $this->assertSame(1444.0, (float) $supplierInfo->unit_cost);
        $this->assertSame('Edition sans exposition des couts.', $product->internal_notes);
    }

    private function workspaceSession(User $user): array
    {
        return [
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ];
    }
}
