<?php

namespace Database\Seeders\Core;

use App\Modules\Core\Access\Models\Permission;
use App\Modules\Core\Access\Models\Role;
use App\Modules\Core\Company\Models\Company;
use Illuminate\Database\Seeder;

class DemoRoleSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->where('name', 'Nema Distribution')->firstOrFail();
        $allPermissions = Permission::query()->pluck('id')->all();

        $platformAdmin = Role::query()->updateOrCreate(
            ['company_id' => null, 'slug' => 'platform_admin'],
            [
                'name' => 'Administrateur plateforme',
                'description' => 'Acces complet au noyau ERP',
                'is_system' => true,
            ]
        );
        $platformAdmin->permissions()->sync($allPermissions);

        $companyAdmin = Role::query()->updateOrCreate(
            ['company_id' => $company->id, 'slug' => 'company_admin'],
            [
                'name' => 'Administrateur entreprise',
                'description' => 'Administration complete de la societe active',
                'is_system' => false,
            ]
        );
        $companyAdmin->permissions()->sync($allPermissions);

        $director = Role::query()->updateOrCreate(
            ['company_id' => $company->id, 'slug' => 'director'],
            [
                'name' => 'Directeur',
                'description' => 'Vision et pilotage global',
                'is_system' => false,
            ]
        );
        $director->permissions()->sync(
            Permission::query()->whereIn('slug', [
                'dashboard.view',
                'approvals.view',
                'reports.view',
                'budgets.view',
                'notifications.view',
                'notifications.outbound.view',
                'companies.view',
                'branches.view',
                'users.view',
                'roles.view',
                'settings.view',
                'customers.view',
                'suppliers.view',
                'categories.view',
                'products.view',
                'stock.view',
                'stock_counts.view',
                'warehouses.view',
                'transfers.view',
                'crm.view',
                'quotes.view',
                'orders.view',
                'delivery_notes.view',
                'pos.view',
                'sales.view',
                'sales.approve',
                'credit_notes.view',
                'collections.view',
                'collections.manage',
                'purchase_requests.view',
                'purchase_requests.approve',
                'purchases.view',
                'purchases.approve',
                'purchase_orders.view',
                'goods_receipts.view',
                'cash_accounts.view',
                'reconciliations.view',
                'ops.view',
                'payments.view',
                'expense_categories.view',
                'expenses.view',
                'expenses.approve',
                'accounting.view',
                'fixed_assets.view',
                'accounting.manage_periods',
                'activity_logs.view',
            ])->pluck('id')->all()
        );

        $operations = Role::query()->updateOrCreate(
            ['company_id' => $company->id, 'slug' => 'operations_officer'],
            [
                'name' => 'Agent operations',
                'description' => 'Saisie des ventes, achats et depenses sans validation finale',
                'is_system' => false,
            ]
        );
        $operations->permissions()->sync(
            Permission::query()->whereIn('slug', [
                'dashboard.view',
                'budgets.view',
                'budgets.manage',
                'notifications.view',
                'customers.view',
                'suppliers.view',
                'categories.view',
                'products.view',
                'stock.view',
                'stock.manage',
                'stock_counts.view',
                'stock_counts.manage',
                'warehouses.view',
                'transfers.view',
                'transfers.manage',
                'crm.view',
                'crm.manage',
                'quotes.view',
                'quotes.manage',
                'orders.view',
                'orders.manage',
                'delivery_notes.view',
                'delivery_notes.manage',
                'pos.view',
                'pos.manage',
                'sales.view',
                'sales.manage',
                'credit_notes.view',
                'credit_notes.manage',
                'collections.view',
                'collections.manage',
                'purchase_requests.view',
                'purchase_requests.manage',
                'purchases.view',
                'purchases.manage',
                'purchase_orders.view',
                'purchase_orders.manage',
                'goods_receipts.view',
                'goods_receipts.manage',
                'expenses.view',
                'expenses.manage',
                'expense_categories.view',
                'cash_accounts.view',
                'reconciliations.view',
                'reconciliations.manage',
                'fixed_assets.view',
                'fixed_assets.manage',
                'activity_logs.view',
            ])->pluck('id')->all()
        );

        $cashier = Role::query()->updateOrCreate(
            ['company_id' => $company->id, 'slug' => 'cashier'],
            [
                'name' => 'Caissier',
                'description' => 'Gestion du point de vente et de la caisse comptoir',
                'is_system' => false,
            ]
        );
        $cashier->permissions()->sync(
            Permission::query()->whereIn('slug', [
                'dashboard.view',
                'customers.view',
                'categories.view',
                'products.view',
                'stock.view',
                'stock_counts.view',
                'warehouses.view',
                'pos.view',
                'pos.manage',
                'cash_accounts.view',
                'reconciliations.view',
                'ops.view',
                'payments.view',
            ])->pluck('id')->all()
        );
    }
}




