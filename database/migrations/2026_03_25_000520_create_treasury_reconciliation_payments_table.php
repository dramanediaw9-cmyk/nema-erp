<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treasury_reconciliation_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('treasury_reconciliation_id');
            $table->unsignedBigInteger('payment_id');
            $table->timestamps();

            $table->foreign('treasury_reconciliation_id', 'trp_reconciliation_fk')
                ->references('id')
                ->on('treasury_reconciliations')
                ->cascadeOnDelete();

            $table->foreign('payment_id', 'trp_payment_fk')
                ->references('id')
                ->on('payments')
                ->cascadeOnDelete();

            $table->unique('payment_id', 'trp_payment_unique');
            $table->index(['treasury_reconciliation_id', 'payment_id'], 'trp_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_reconciliation_payments');
    }
};
