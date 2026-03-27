<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->morphs('allocatable');
            $table->decimal('allocated_amount', 15, 2);
            $table->timestamps();

            $table->index(['payment_id', 'allocatable_type', 'allocatable_id'], 'payment_allocation_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
    }
};
