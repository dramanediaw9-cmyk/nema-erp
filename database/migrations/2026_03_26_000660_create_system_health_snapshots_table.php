<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_health_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('scope', 40)->default('company');
            $table->string('overall_status', 20);
            $table->unsignedInteger('warning_count')->default(0);
            $table->unsignedInteger('failure_count')->default(0);
            $table->json('checks');
            $table->json('meta')->nullable();
            $table->timestamp('captured_at');
            $table->timestamps();

            $table->index(['company_id', 'captured_at']);
            $table->index(['overall_status', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_health_snapshots');
    }
};