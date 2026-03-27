<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->decimal('delivered_qty', 15, 3)->default(0)->after('qty');
        });

        Schema::table('delivery_note_items', function (Blueprint $table) {
            $table->foreignId('sales_order_item_id')->nullable()->after('delivery_note_id')->constrained('sales_order_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('delivery_note_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sales_order_item_id');
        });

        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->dropColumn('delivered_qty');
        });
    }
};
