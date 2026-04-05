<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('auto_replenish')->default(false)->after('min_stock');
            $table->decimal('reorder_max_qty', 15, 3)->nullable()->after('auto_replenish');
            $table->decimal('reorder_multiple_qty', 15, 3)->nullable()->after('reorder_max_qty');
            $table->unsignedSmallInteger('purchase_lead_time_days')->nullable()->after('reorder_multiple_qty');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn([
                'auto_replenish',
                'reorder_max_qty',
                'reorder_multiple_qty',
                'purchase_lead_time_days',
            ]);
        });
    }
};
