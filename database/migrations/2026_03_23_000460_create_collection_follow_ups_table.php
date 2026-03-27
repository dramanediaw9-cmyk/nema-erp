<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sales_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('partners')->cascadeOnDelete();
            $table->date('action_date');
            $table->string('action_type', 50);
            $table->string('outcome', 50)->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_phone', 50)->nullable();
            $table->decimal('promised_amount', 15, 2)->nullable();
            $table->date('promised_date')->nullable();
            $table->date('next_action_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'action_date']);
            $table->index(['sales_invoice_id', 'action_date']);
            $table->index(['customer_id', 'next_action_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_follow_ups');
    }
};