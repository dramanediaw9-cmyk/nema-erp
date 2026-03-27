<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('branch_id')->constrained('warehouses')->nullOnDelete();
        });

        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('branch_id')->constrained('warehouses')->nullOnDelete();
        });

        Schema::table('purchase_bills', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('branch_id')->constrained('warehouses')->nullOnDelete();
        });

        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('branch_id')->constrained('warehouses')->nullOnDelete();
        });

        if (Schema::hasTable('branches') && Schema::hasTable('warehouses')) {
            $branches = DB::table('branches')->select('id', 'company_id', 'code', 'name')->get();

            foreach ($branches as $branch) {
                $warehouseId = DB::table('warehouses')
                    ->where('branch_id', $branch->id)
                    ->value('id');

                if (! $warehouseId) {
                    $warehouseId = DB::table('warehouses')->insertGetId([
                        'company_id' => $branch->company_id,
                        'branch_id' => $branch->id,
                        'name' => 'Depot principal '.$branch->name,
                        'code' => 'DEP-'.$branch->code,
                        'is_default' => true,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('stock_movements')->where('branch_id', $branch->id)->whereNull('warehouse_id')->update(['warehouse_id' => $warehouseId]);
                DB::table('sales_invoices')->where('branch_id', $branch->id)->whereNull('warehouse_id')->update(['warehouse_id' => $warehouseId]);
                DB::table('purchase_bills')->where('branch_id', $branch->id)->whereNull('warehouse_id')->update(['warehouse_id' => $warehouseId]);
                DB::table('delivery_notes')->where('branch_id', $branch->id)->whereNull('warehouse_id')->update(['warehouse_id' => $warehouseId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
        });

        Schema::table('purchase_bills', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
        });

        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
        });
    }
};
