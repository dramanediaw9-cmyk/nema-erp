<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_pulse_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('status', 32);
            $table->unsignedSmallInteger('score');
            $table->unsignedSmallInteger('sla_target')->default(75);
            $table->boolean('sla_met')->default(false);
            $table->json('signals');
            $table->json('metrics');
            $table->json('recommendations')->nullable();
            $table->timestamp('captured_at')->index();
            $table->timestamps();
            $table->index(['company_id', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_pulse_snapshots');
    }
};
