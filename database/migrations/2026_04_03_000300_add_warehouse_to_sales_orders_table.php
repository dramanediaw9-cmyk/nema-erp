<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->foreignId('warehouse_id')->nullable()->after('branch_id')->constrained('warehouses')->nullOnDelete();
            $table->index(['company_id', 'branch_id', 'warehouse_id', 'status'], 'sales_orders_scope_idx');
        });

        $orders = DB::table('sales_orders')
            ->select('id', 'company_id', 'branch_id')
            ->orderBy('id')
            ->get();

        foreach ($orders as $order) {
            $warehouseId = DB::table('warehouses')
                ->where('company_id', $order->company_id)
                ->where('branch_id', $order->branch_id)
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->value('id');

            if ($warehouseId) {
                DB::table('sales_orders')
                    ->where('id', $order->id)
                    ->update(['warehouse_id' => $warehouseId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->dropIndex('sales_orders_scope_idx');
            $table->dropConstrainedForeignId('warehouse_id');
        });
    }
};
