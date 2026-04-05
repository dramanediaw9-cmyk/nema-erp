<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_suppliers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('partners')->cascadeOnDelete();
            $table->string('supplier_product_code')->nullable();
            $table->string('supplier_product_name')->nullable();
            $table->decimal('min_qty', 12, 3)->nullable();
            $table->decimal('unit_cost', 14, 2)->nullable();
            $table->unsignedInteger('lead_time_days')->nullable();
            $table->boolean('is_preferred')->default(false);
            $table->timestamps();

            $table->unique(['product_id', 'supplier_id'], 'product_supplier_unique');
            $table->index(['company_id', 'supplier_id'], 'product_supplier_company_supplier_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_suppliers');
    }
};