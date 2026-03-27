<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->string('discount_type', 20)->default('none')->after('subtotal');
            $table->decimal('discount_value', 15, 2)->default(0)->after('discount_type');
            $table->decimal('discount_total', 15, 2)->default(0)->after('discount_value');
        });
    }

    public function down(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->dropColumn(['discount_type', 'discount_value', 'discount_total']);
        });
    }
};
