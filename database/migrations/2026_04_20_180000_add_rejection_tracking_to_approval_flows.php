<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_steps', function (Blueprint $table) {
            if (! Schema::hasColumn('approval_steps', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('approved_at');
            }

            if (! Schema::hasColumn('approval_steps', 'rejected_by')) {
                $table->foreignId('rejected_by')->nullable()->after('rejected_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('approval_steps', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('rejected_by');
            }
        });

        Schema::table('sales_invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_invoices', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('cancelled_at');
            }

            if (! Schema::hasColumn('sales_invoices', 'rejected_by')) {
                $table->foreignId('rejected_by')->nullable()->after('rejected_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('sales_invoices', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('rejected_by');
            }
        });

        Schema::table('purchase_bills', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_bills', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('approved_at');
            }

            if (! Schema::hasColumn('purchase_bills', 'rejected_by')) {
                $table->foreignId('rejected_by')->nullable()->after('rejected_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('purchase_bills', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('rejected_by');
            }
        });

        Schema::table('expenses', function (Blueprint $table) {
            if (! Schema::hasColumn('expenses', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('approved_at');
            }

            if (! Schema::hasColumn('expenses', 'rejected_by')) {
                $table->foreignId('rejected_by')->nullable()->after('rejected_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('expenses', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('rejected_by');
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE approval_steps MODIFY status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending'");
            DB::statement("ALTER TABLE sales_invoices MODIFY status ENUM('pending_approval', 'validated', 'cancelled', 'rejected') NOT NULL DEFAULT 'validated'");
            DB::statement("ALTER TABLE purchase_bills MODIFY status ENUM('pending_approval', 'validated', 'rejected') NOT NULL DEFAULT 'validated'");
            DB::statement("ALTER TABLE expenses MODIFY status ENUM('pending_approval', 'validated', 'rejected') NOT NULL DEFAULT 'validated'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::table('approval_steps')
                ->where('status', 'rejected')
                ->update(['status' => 'pending']);

            DB::table('sales_invoices')
                ->where('status', 'rejected')
                ->update(['status' => 'pending_approval']);

            DB::table('purchase_bills')
                ->where('status', 'rejected')
                ->update(['status' => 'pending_approval']);

            DB::table('expenses')
                ->where('status', 'rejected')
                ->update(['status' => 'pending_approval']);

            DB::statement("ALTER TABLE approval_steps MODIFY status ENUM('pending', 'approved') NOT NULL DEFAULT 'pending'");
            DB::statement("ALTER TABLE sales_invoices MODIFY status ENUM('pending_approval', 'validated', 'cancelled') NOT NULL DEFAULT 'validated'");
            DB::statement("ALTER TABLE purchase_bills MODIFY status ENUM('pending_approval', 'validated') NOT NULL DEFAULT 'validated'");
            DB::statement("ALTER TABLE expenses MODIFY status ENUM('pending_approval', 'validated') NOT NULL DEFAULT 'validated'");
        }

        Schema::table('expenses', function (Blueprint $table) {
            if (Schema::hasColumn('expenses', 'rejected_by')) {
                $table->dropConstrainedForeignId('rejected_by');
            }

            if (Schema::hasColumn('expenses', 'rejected_at')) {
                $table->dropColumn(['rejected_at', 'rejection_reason']);
            }
        });

        Schema::table('purchase_bills', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_bills', 'rejected_by')) {
                $table->dropConstrainedForeignId('rejected_by');
            }

            if (Schema::hasColumn('purchase_bills', 'rejected_at')) {
                $table->dropColumn(['rejected_at', 'rejection_reason']);
            }
        });

        Schema::table('sales_invoices', function (Blueprint $table) {
            if (Schema::hasColumn('sales_invoices', 'rejected_by')) {
                $table->dropConstrainedForeignId('rejected_by');
            }

            if (Schema::hasColumn('sales_invoices', 'rejected_at')) {
                $table->dropColumn(['rejected_at', 'rejection_reason']);
            }
        });

        Schema::table('approval_steps', function (Blueprint $table) {
            if (Schema::hasColumn('approval_steps', 'rejected_by')) {
                $table->dropConstrainedForeignId('rejected_by');
            }

            if (Schema::hasColumn('approval_steps', 'rejected_at')) {
                $table->dropColumn(['rejected_at', 'rejection_reason']);
            }
        });
    }
};
