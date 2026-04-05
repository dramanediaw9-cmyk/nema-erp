<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_event_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('integration_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 30)->default('webhook');
            $table->string('target_url')->nullable();
            $table->string('status', 20)->default('failed');
            $table->unsignedInteger('attempt_number')->default(1);
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->json('request_payload')->nullable();
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->json('response_headers')->nullable();
            $table->text('response_body')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['integration_event_id', 'attempt_number'], 'ied_event_attempt_idx');
            $table->index(['company_id', 'status'], 'ied_company_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_event_deliveries');
    }
};
