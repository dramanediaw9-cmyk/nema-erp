<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('partners')->cascadeOnDelete();
            $table->string('delivery_number')->unique();
            $table->date('delivery_date');
            $table->string('status', 32)->default('issued');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->foreignId('converted_sales_invoice_id')->nullable()->constrained('sales_invoices')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'delivery_date']);
            $table->index(['company_id', 'branch_id', 'status']);
            $table->index(['sales_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_notes');
    }
};
