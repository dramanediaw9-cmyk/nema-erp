<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->foreignId('origin_sales_order_id')->nullable()->after('warehouse_id')->constrained('sales_orders')->nullOnDelete();
            $table->index(['company_id', 'origin_sales_order_id', 'status'], 'purchase_requests_origin_idx');
        });

        Schema::table('purchase_request_items', function (Blueprint $table) {
            $table->foreignId('origin_sales_order_item_id')->nullable()->after('product_id')->constrained('sales_order_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_request_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('origin_sales_order_item_id');
        });

        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropIndex('purchase_requests_origin_idx');
            $table->dropConstrainedForeignId('origin_sales_order_id');
        });
    }
};
