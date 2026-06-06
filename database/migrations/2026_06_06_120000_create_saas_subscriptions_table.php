<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('plan', 30);
            $table->string('status', 30)->default('trialing');
            $table->unsignedInteger('user_limit');
            $table->unsignedInteger('branch_limit');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('terms_accepted_at')->nullable();
            $table->string('provider', 40)->nullable();
            $table->string('provider_reference', 190)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['plan', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_subscriptions');
    }
};
