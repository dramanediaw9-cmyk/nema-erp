<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_quotes', function (Blueprint $table) {
            $table->foreignId('converted_sales_order_id')
                ->nullable()
                ->after('converted_sales_invoice_id')
                ->constrained('sales_orders')
                ->nullOnDelete();
        });

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->foreignId('origin_sales_quote_id')
                ->nullable()
                ->after('converted_sales_invoice_id')
                ->constrained('sales_quotes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('origin_sales_quote_id');
        });

        Schema::table('sales_quotes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('converted_sales_order_id');
        });
    }
};
