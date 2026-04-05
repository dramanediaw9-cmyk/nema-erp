<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_bills', function (Blueprint $table): void {
            $table->foreignId('purchase_order_id')->nullable()->after('payment_term_id')->constrained()->nullOnDelete();
            $table->foreignId('goods_receipt_id')->nullable()->after('purchase_order_id')->constrained()->nullOnDelete();
            $table->index(['company_id', 'purchase_order_id'], 'purchase_bills_company_order_idx');
            $table->unique(['company_id', 'goods_receipt_id'], 'purchase_bills_company_receipt_unique');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_bills', function (Blueprint $table): void {
            $table->dropUnique('purchase_bills_company_receipt_unique');
            $table->dropIndex('purchase_bills_company_order_idx');
            $table->dropConstrainedForeignId('goods_receipt_id');
            $table->dropConstrainedForeignId('purchase_order_id');
        });
    }
};
