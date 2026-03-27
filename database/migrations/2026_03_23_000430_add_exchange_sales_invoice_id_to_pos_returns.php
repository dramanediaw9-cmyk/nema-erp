<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_returns', function (Blueprint $table) {
            $table->foreignId('exchange_sales_invoice_id')
                ->nullable()
                ->after('sales_invoice_id')
                ->constrained('sales_invoices')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pos_returns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('exchange_sales_invoice_id');
        });
    }
};
