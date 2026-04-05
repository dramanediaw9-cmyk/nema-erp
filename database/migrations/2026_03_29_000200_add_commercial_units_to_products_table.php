<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('sales_unit_name')->nullable()->after('unit');
            $table->decimal('sales_unit_ratio', 15, 3)->nullable()->after('sales_unit_name');
            $table->string('purchase_unit_name')->nullable()->after('sales_unit_ratio');
            $table->decimal('purchase_unit_ratio', 15, 3)->nullable()->after('purchase_unit_name');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn([
                'sales_unit_name',
                'sales_unit_ratio',
                'purchase_unit_name',
                'purchase_unit_ratio',
            ]);
        });
    }
};
