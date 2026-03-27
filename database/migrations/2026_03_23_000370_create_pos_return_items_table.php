<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_return_id')->constrained('pos_returns')->cascadeOnDelete();
            $table->foreignId('sales_invoice_item_id')->constrained('sales_invoice_items')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('description');
            $table->decimal('qty', 15, 3);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('line_total', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_return_items');
    }
};
