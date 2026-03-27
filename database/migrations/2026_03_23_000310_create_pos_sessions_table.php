<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('cash_account_id')->constrained('cash_accounts')->restrictOnDelete();
            $table->string('session_number')->unique();
            $table->string('status', 20)->default('open');
            $table->decimal('opening_amount', 15, 2)->default(0);
            $table->decimal('expected_amount', 15, 2)->nullable();
            $table->decimal('closing_amount', 15, 2)->nullable();
            $table->decimal('variance_amount', 15, 2)->nullable();
            $table->text('opening_notes')->nullable();
            $table->text('closing_notes')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'branch_id', 'status']);
            $table->index(['opened_by', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_sessions');
    }
};
