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
            if (! Schema::hasColumn('sales_invoices', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('validated_at');
            }

            if (! Schema::hasColumn('sales_invoices', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE sales_invoices MODIFY status ENUM('pending_approval', 'validated') NOT NULL DEFAULT 'validated'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE sales_invoices MODIFY status ENUM('validated') NOT NULL DEFAULT 'validated'");
        }

        Schema::table('sales_invoices', function (Blueprint $table) {
            if (Schema::hasColumn('sales_invoices', 'approved_by')) {
                $table->dropConstrainedForeignId('approved_by');
            }

            if (Schema::hasColumn('sales_invoices', 'approved_at')) {
                $table->dropColumn('approved_at');
            }
        });
    }
};
