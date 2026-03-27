<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table): void {
            $table->decimal('pos_cash_received', 18, 2)->default(0)->after('balance_due');
            $table->decimal('pos_change_due', 18, 2)->default(0)->after('pos_cash_received');
        });

        Schema::table('pos_drafts', function (Blueprint $table): void {
            $table->json('payments')->nullable()->after('items');
            $table->decimal('cash_received_amount', 18, 2)->default(0)->after('total');
        });
    }

    public function down(): void
    {
        Schema::table('pos_drafts', function (Blueprint $table): void {
            $table->dropColumn(['payments', 'cash_received_amount']);
        });

        Schema::table('sales_invoices', function (Blueprint $table): void {
            $table->dropColumn(['pos_cash_received', 'pos_change_due']);
        });
    }
};