<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deployment_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('commercial_offer', 30)->default('growth');
            $table->string('deployment_mode', 30)->default('local');
            $table->string('lifecycle_stage', 30)->default('setup');
            $table->string('hosting_target', 40)->default('on_prem');
            $table->string('support_tier', 30)->default('essential');
            $table->string('monitoring_level', 30)->default('basic');
            $table->string('backup_strategy', 30)->default('manual');
            $table->string('update_channel', 30)->default('manual');
            $table->unsignedInteger('target_users')->nullable();
            $table->unsignedInteger('target_branches')->nullable();
            $table->timestamp('go_live_target_at')->nullable();
            $table->timestamp('last_release_at')->nullable();
            $table->timestamp('last_backup_verified_at')->nullable();
            $table->timestamp('last_restore_drill_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('company_id');
            $table->index(['tenant_id', 'commercial_offer']);
            $table->index(['tenant_id', 'deployment_mode']);
            $table->index(['tenant_id', 'lifecycle_stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deployment_profiles');
    }
};
