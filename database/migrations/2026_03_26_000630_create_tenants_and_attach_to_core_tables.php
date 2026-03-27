<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('slug')->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $tenantTables = [
            'companies',
            'users',
            'roles',
            'branches',
            'warehouses',
            'partners',
            'product_categories',
            'products',
            'settings',
            'document_sequences',
            'cash_accounts',
            'sales_quotes',
            'sales_orders',
            'delivery_notes',
            'sales_invoices',
            'sales_credit_notes',
            'purchase_requests',
            'purchase_orders',
            'goods_receipts',
            'purchase_bills',
            'stock_movements',
            'stock_transfers',
            'stock_counts',
            'expenses',
            'payments',
            'accounts',
            'journal_entries',
            'budgets',
        ];

        foreach ($tenantTables as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'tenant_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id')->index();
            });
        }

        $defaultTenantId = DB::table('tenants')->insertGetId([
            'code' => 'TENANT-DEFAULT',
            'name' => 'Tenant principal',
            'slug' => Str::slug('Tenant principal'),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (Schema::hasTable('companies') && DB::table('companies')->count() > 0) {
            DB::table('companies')->whereNull('tenant_id')->update(['tenant_id' => $defaultTenantId]);
        }

        $companyTenantMap = Schema::hasTable('companies')
            ? DB::table('companies')->pluck('tenant_id', 'id')->all()
            : [];

        foreach ($tenantTables as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'tenant_id')) {
                continue;
            }

            if ($tableName === 'companies' || ! Schema::hasColumn($tableName, 'company_id')) {
                continue;
            }

            foreach ($companyTenantMap as $companyId => $tenantId) {
                DB::table($tableName)
                    ->whereNull('tenant_id')
                    ->where('company_id', $companyId)
                    ->update(['tenant_id' => $tenantId ?: $defaultTenantId]);
            }
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'tenant_id')) {
            DB::table('users')
                ->whereNull('tenant_id')
                ->update(['tenant_id' => $defaultTenantId]);
        }
    }

    public function down(): void
    {
        $tenantTables = [
            'budgets',
            'journal_entries',
            'accounts',
            'payments',
            'expenses',
            'stock_counts',
            'stock_transfers',
            'stock_movements',
            'purchase_bills',
            'goods_receipts',
            'purchase_orders',
            'purchase_requests',
            'sales_credit_notes',
            'sales_invoices',
            'delivery_notes',
            'sales_orders',
            'sales_quotes',
            'cash_accounts',
            'document_sequences',
            'settings',
            'products',
            'product_categories',
            'partners',
            'warehouses',
            'branches',
            'roles',
            'users',
            'companies',
        ];

        foreach ($tenantTables as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'tenant_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('tenant_id');
            });
        }

        Schema::dropIfExists('tenants');
    }
};
