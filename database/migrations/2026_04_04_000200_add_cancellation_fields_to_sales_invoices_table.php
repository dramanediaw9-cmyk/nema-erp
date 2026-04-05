<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_invoices', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('approved_at');
            }

            if (! Schema::hasColumn('sales_invoices', 'cancelled_by')) {
                $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE sales_invoices MODIFY status ENUM('pending_approval', 'validated', 'cancelled') NOT NULL DEFAULT 'validated'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::table('sales_invoices')
                ->where('status', 'cancelled')
                ->update(['status' => 'pending_approval']);

            DB::statement("ALTER TABLE sales_invoices MODIFY status ENUM('pending_approval', 'validated') NOT NULL DEFAULT 'validated'");
        }

        Schema::table('sales_invoices', function (Blueprint $table) {
            if (Schema::hasColumn('sales_invoices', 'cancelled_by')) {
                $table->dropConstrainedForeignId('cancelled_by');
            }

            if (Schema::hasColumn('sales_invoices', 'cancelled_at')) {
                $table->dropColumn('cancelled_at');
            }
        });
    }
};
