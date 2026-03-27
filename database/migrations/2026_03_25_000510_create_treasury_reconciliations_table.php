<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treasury_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cash_account_id')->constrained('cash_accounts')->cascadeOnDelete();
            $table->string('reconciliation_number');
            $table->date('statement_date');
            $table->string('statement_reference')->nullable();
            $table->decimal('statement_balance', 15, 2);
            $table->decimal('matched_total', 15, 2)->default(0);
            $table->decimal('book_balance', 15, 2)->default(0);
            $table->decimal('difference', 15, 2)->default(0);
            $table->unsignedInteger('payments_count')->default(0);
            $table->string('status', 30)->default('balanced');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'reconciliation_number']);
            $table->index(['company_id', 'statement_date']);
            $table->index(['company_id', 'cash_account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_reconciliations');
    }
};
