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

        $directorPermissions = Permission::query()->whereIn('slug', [
            'dashboard.view',
            'approvals.view',
            'reports.view',
            'reports.margin.view',
            'budgets.view',
            'notifications.view',
            'notifications.outbound.view',
            'automation.view',
            'automation.manage',
            'companies.view',
            'branches.view',
            'branches.cross_scope',
            'users.view',
            'roles.view',
            'settings.view',
            'settings.integrations.manage',
            'platform.view',
            'customers.view',
            'suppliers.view',
            'categories.view',
            'products.view',
            'products.cost.view',
            'stock.view',
            'stock_counts.view',
            'warehouses.view',
            'transfers.view',
            'crm.view',
            'quotes.view',
            'orders.view',
            'delivery_notes.view',
            'pos.view',
            'pos.sessions.unlock',
            'sales.view',
            'sales.approve',
            'sales.cancel',
            'sales.price_override',
            'credit_notes.view',
            'credit_notes.issue',
            'collections.view',
            'collections.manage',
            'purchase_requests.view',
            'purchase_requests.approve',
            'purchases.view',
            'purchases.approve',
            'supplier_credit_notes.view',
            'supplier_credit_notes.issue',
            'purchase_orders.view',
            'goods_receipts.view',
            'cash_accounts.view',
            'reconciliations.view',
            'ops.view',
            'payments.view',
            'payments.validate',
            'expense_categories.view',
            'expenses.view',
            'expenses.approve',
            'accounting.view',
            'accounting.reverse',
            'fixed_assets.view',
            'accounting.manage_periods',
            'hr.view',
            'payroll.view',
            'projects.view',
            'manufacturing.view',
            'commerce.view',
            'activity_logs.view',
        ])->pluck('id')->all();

        $operationsPermissions = Permission::query()->whereIn('slug', [
            'dashboard.view',
            'budgets.view',
            'budgets.manage',
            'notifications.view',
            'automation.view',
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
            'supplier_credit_notes.view',
            'supplier_credit_notes.manage',
            'supplier_credit_notes.issue',
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
            'hr.view',
            'hr.manage',
            'payroll.view',
            'projects.view',
            'projects.manage',
            'manufacturing.view',
            'manufacturing.manage',
            'commerce.view',
            'commerce.manage',
            'activity_logs.view',
        ])->pluck('id')->all();

        $cashierPermissions = Permission::query()->whereIn('slug', [
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
        ])->pluck('id')->all();

        Company::query()->orderBy('id')->get()->each(function (Company $company) use ($allPermissions, $directorPermissions, $operationsPermissions, $cashierPermissions): void {
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
            $director->permissions()->sync($directorPermissions);

            $operations = Role::query()->updateOrCreate(
                ['company_id' => $company->id, 'slug' => 'operations_officer'],
                [
                    'name' => 'Agent operations',
                    'description' => 'Saisie des ventes, achats et depenses sans validation finale',
                    'is_system' => false,
                ]
            );
            $operations->permissions()->sync($operationsPermissions);

            $cashier = Role::query()->updateOrCreate(
                ['company_id' => $company->id, 'slug' => 'cashier'],
                [
                    'name' => 'Caissier',
                    'description' => 'Gestion du point de vente et de la caisse comptoir',
                    'is_system' => false,
                ]
            );
            $cashier->permissions()->sync($cashierPermissions);
        });
    }
}
