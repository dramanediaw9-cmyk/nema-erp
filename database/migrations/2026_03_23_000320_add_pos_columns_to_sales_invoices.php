<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->string('sale_channel', 30)->default('standard')->after('origin_delivery_note_id');
            $table->foreignId('pos_session_id')->nullable()->after('sale_channel')->constrained('pos_sessions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pos_session_id');
            $table->dropColumn('sale_channel');
        });
    }
};
