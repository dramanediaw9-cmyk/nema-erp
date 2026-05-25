<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_credit_note_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_credit_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_bill_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('qty', 15, 3);
            $table->decimal('unit_cost', 15, 2);
            $table->foreignId('tax_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('tax_rate', 8, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_credit_note_items');
    }
};
