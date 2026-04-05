<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_lots', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('goods_receipt_id')->nullable()->constrained('goods_receipts')->nullOnDelete();
            $table->foreignId('goods_receipt_item_id')->nullable()->constrained('goods_receipt_items')->nullOnDelete();
            $table->string('tracking_type', 12)->default('lot');
            $table->string('lot_number', 120)->nullable()->index();
            $table->string('serial_number', 120)->nullable();
            $table->date('expires_at')->nullable()->index();
            $table->date('received_at')->nullable();
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->decimal('quantity_received', 15, 3)->default(0);
            $table->decimal('quantity_available', 15, 3)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'product_id', 'serial_number'], 'prd_lot_serial_unique');
            $table->index(['company_id', 'product_id', 'tracking_type'], 'prd_lot_prod_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_lots');
    }
};
