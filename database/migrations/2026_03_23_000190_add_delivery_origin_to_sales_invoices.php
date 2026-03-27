<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->foreignId('origin_delivery_note_id')->nullable()->after('customer_id')->constrained('delivery_notes')->nullOnDelete();
            $table->boolean('stock_posted')->default(false)->after('balance_due');
        });
    }

    public function down(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('origin_delivery_note_id');
            $table->dropColumn('stock_posted');
        });
    }
};
