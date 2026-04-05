<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_inbound_webhooks', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('integration_event_id')->nullable()->constrained('integration_events')->nullOnDelete();
            $table->string('source')->nullable();
            $table->string('event_name')->nullable();
            $table->string('external_id')->nullable();
            $table->string('status', 20)->default('accepted');
            $table->json('headers')->nullable();
            $table->json('payload')->nullable();
            $table->string('signature')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'event_name']);
            $table->index(['company_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_inbound_webhooks');
    }
};
