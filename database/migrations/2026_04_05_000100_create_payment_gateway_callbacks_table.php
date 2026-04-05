<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateway_callbacks', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sales_invoice_id')->constrained('sales_invoices')->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('cash_account_id')->nullable()->constrained('cash_accounts')->nullOnDelete();
            $table->string('channel', 40);
            $table->string('gateway_status', 20)->default('pending');
            $table->string('processing_status', 30)->default('received');
            $table->decimal('amount', 15, 2);
            $table->string('reference', 120);
            $table->string('external_reference', 120)->nullable();
            $table->string('payer_name', 120)->nullable();
            $table->string('payer_phone', 60)->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('received_at');
            $table->dateTime('processed_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['sales_invoice_id', 'channel', 'reference'], 'pgc_invoice_chan_ref_uq');
            $table->index(['company_id', 'channel', 'gateway_status'], 'pgc_company_chan_status_idx');
            $table->index(['company_id', 'processing_status'], 'pgc_company_proc_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_callbacks');
    }
};
