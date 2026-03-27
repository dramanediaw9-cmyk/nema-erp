<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expense_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('partners')->nullOnDelete();
            $table->foreignId('cash_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('expense_number');
            $table->date('expense_date');
            $table->string('description');
            $table->decimal('total', 15, 2);
            $table->enum('status', ['pending_approval', 'validated'])->default('validated');
            $table->enum('payment_status', ['unpaid', 'paid'])->default('unpaid');
            $table->date('payment_date')->nullable();
            $table->string('payment_method', 50)->nullable();
            $table->string('payment_reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'expense_number']);
            $table->index(['company_id', 'expense_date']);
            $table->index(['company_id', 'payment_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};