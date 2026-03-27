<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_invoice_items', function (Blueprint $table) {
            $table->decimal('line_subtotal', 15, 2)->default(0)->after('unit_price');
            $table->string('discount_type', 20)->default('none')->after('line_subtotal');
            $table->decimal('discount_value', 15, 2)->default(0)->after('discount_type');
            $table->decimal('discount_total', 15, 2)->default(0)->after('discount_value');
        });

        DB::table('sales_invoice_items')
            ->where('line_subtotal', 0)
            ->update([
                'line_subtotal' => DB::raw('line_total'),
                'discount_type' => 'none',
                'discount_value' => 0,
                'discount_total' => 0,
            ]);
    }

    public function down(): void
    {
        Schema::table('sales_invoice_items', function (Blueprint $table) {
            $table->dropColumn([
                'line_subtotal',
                'discount_type',
                'discount_value',
                'discount_total',
            ]);
        });
    }
};
