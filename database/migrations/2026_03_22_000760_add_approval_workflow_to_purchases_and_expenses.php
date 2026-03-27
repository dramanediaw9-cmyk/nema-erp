<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_bills', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_bills', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('validated_at');
            }

            if (! Schema::hasColumn('purchase_bills', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            }
        });

        Schema::table('expenses', function (Blueprint $table) {
            if (! Schema::hasColumn('expenses', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('notes');
            }

            if (! Schema::hasColumn('expenses', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE purchase_bills MODIFY status ENUM('pending_approval', 'validated') NOT NULL DEFAULT 'validated'");
            DB::statement("ALTER TABLE expenses MODIFY status ENUM('pending_approval', 'validated') NOT NULL DEFAULT 'validated'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE purchase_bills MODIFY status ENUM('validated') NOT NULL DEFAULT 'validated'");
            DB::statement("ALTER TABLE expenses MODIFY status ENUM('validated') NOT NULL DEFAULT 'validated'");
        }

        Schema::table('purchase_bills', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_bills', 'approved_by')) {
                $table->dropConstrainedForeignId('approved_by');
            }

            if (Schema::hasColumn('purchase_bills', 'approved_at')) {
                $table->dropColumn('approved_at');
            }
        });

        Schema::table('expenses', function (Blueprint $table) {
            if (Schema::hasColumn('expenses', 'approved_by')) {
                $table->dropConstrainedForeignId('approved_by');
            }

            if (Schema::hasColumn('expenses', 'approved_at')) {
                $table->dropColumn('approved_at');
            }
        });
    }
};