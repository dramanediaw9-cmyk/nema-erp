<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->string('pos_sync_key', 80)->nullable()->after('pos_session_id');
            $table->unique(['company_id', 'pos_sync_key'], 'sales_invoices_company_pos_sync_unique');
        });
    }

    public function down(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->dropUnique('sales_invoices_company_pos_sync_unique');
            $table->dropColumn('pos_sync_key');
        });
    }
};