<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code', 40);
            $table->string('name');
            $table->string('partner_name');
            $table->string('connection_type', 30)->default('api');
            $table->string('sync_mode', 30)->default('bidirectional');
            $table->string('status', 30)->default('draft');
            $table->string('health_status', 30)->default('watch');
            $table->string('external_reference')->nullable();
            $table->dateTime('last_sync_at')->nullable();
            $table->dateTime('last_health_at')->nullable();
            $table->text('scope_summary')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'health_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_connections');
    }
};
