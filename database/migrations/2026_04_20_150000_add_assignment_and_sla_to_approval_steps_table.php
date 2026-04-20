<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_steps', function (Blueprint $table) {
            $table->foreignId('assigned_to')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('due_at')->nullable()->after('assigned_to');
            $table->foreignId('delegated_by')->nullable()->after('due_at')->constrained('users')->nullOnDelete();
            $table->timestamp('delegated_at')->nullable()->after('delegated_by');
            $table->timestamp('escalated_at')->nullable()->after('delegated_at');
            $table->json('meta')->nullable()->after('escalated_at');

            $table->index(['company_id', 'status', 'assigned_to'], 'approval_steps_company_status_assigned_index');
            $table->index(['company_id', 'status', 'due_at'], 'approval_steps_company_status_due_index');
        });
    }

    public function down(): void
    {
        Schema::table('approval_steps', function (Blueprint $table) {
            $table->dropIndex('approval_steps_company_status_assigned_index');
            $table->dropIndex('approval_steps_company_status_due_index');
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropColumn('due_at');
            $table->dropConstrainedForeignId('delegated_by');
            $table->dropColumn(['delegated_at', 'escalated_at', 'meta']);
        });
    }
};
