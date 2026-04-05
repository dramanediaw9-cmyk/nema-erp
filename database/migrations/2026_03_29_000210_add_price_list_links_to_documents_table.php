<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_quotes', function (Blueprint $table): void {
            $table->foreignId('price_list_id')->nullable()->after('customer_id')->constrained('price_lists')->nullOnDelete();
        });

        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->foreignId('price_list_id')->nullable()->after('customer_id')->constrained('price_lists')->nullOnDelete();
        });

        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->foreignId('price_list_id')->nullable()->after('supplier_id')->constrained('price_lists')->nullOnDelete();
        });

        Schema::table('purchase_bills', function (Blueprint $table): void {
            $table->foreignId('price_list_id')->nullable()->after('supplier_id')->constrained('price_lists')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_bills', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('price_list_id');
        });

        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('price_list_id');
        });

        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('price_list_id');
        });

        Schema::table('sales_quotes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('price_list_id');
        });
    }
};
