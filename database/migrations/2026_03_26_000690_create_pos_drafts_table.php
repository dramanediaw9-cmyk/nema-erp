<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_drafts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pos_session_id')->constrained('pos_sessions')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('partners')->nullOnDelete();
            $table->string('label');
            $table->date('sale_date');
            $table->string('method', 50)->default('cash');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->enum('discount_type', ['none', 'fixed', 'percent'])->default('none');
            $table->decimal('discount_value', 18, 2)->default(0);
            $table->json('items')->nullable();
            $table->unsignedInteger('items_count')->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->timestamp('last_activity_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'branch_id', 'pos_session_id']);
            $table->index(['pos_session_id', 'last_activity_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_drafts');
    }
};