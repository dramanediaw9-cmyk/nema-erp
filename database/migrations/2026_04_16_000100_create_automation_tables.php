<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code', 40);
            $table->string('name');
            $table->string('module_key', 40)->default('core');
            $table->string('signal_key', 80);
            $table->string('status', 20)->default('draft');
            $table->string('severity', 20)->default('warning');
            $table->string('action_type', 30)->default('internal_alert');
            $table->unsignedInteger('threshold_value')->default(1);
            $table->unsignedInteger('window_hours')->nullable();
            $table->unsignedInteger('cooldown_minutes')->default(240);
            $table->timestamp('last_evaluated_at')->nullable();
            $table->timestamp('last_triggered_at')->nullable();
            $table->unsignedInteger('last_value')->nullable();
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'signal_key']);
        });

        Schema::create('automation_executions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('automation_rule_id')->constrained('automation_rules')->cascadeOnDelete();
            $table->foreignId('notification_id')->nullable()->constrained('internal_notifications')->nullOnDelete();
            $table->string('signal_key', 80);
            $table->string('status', 20);
            $table->boolean('matched')->default(false);
            $table->unsignedInteger('observed_value')->default(0);
            $table->text('message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('executed_at');
            $table->timestamps();

            $table->index(['automation_rule_id', 'executed_at']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_executions');
        Schema::dropIfExists('automation_rules');
    }
};
