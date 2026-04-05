<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('sale_ok')->default(true)->after('type');
            $table->boolean('purchase_ok')->default(true)->after('sale_ok');
            $table->string('invoice_policy', 20)->default('ordered')->after('purchase_ok');
            $table->string('tracking_type', 20)->default('none')->after('invoice_policy');
            $table->text('sales_description')->nullable()->after('description');
            $table->text('purchase_description')->nullable()->after('sales_description');
            $table->text('internal_notes')->nullable()->after('purchase_description');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'sale_ok',
                'purchase_ok',
                'invoice_policy',
                'tracking_type',
                'sales_description',
                'purchase_description',
                'internal_notes',
            ]);
        });
    }
};
