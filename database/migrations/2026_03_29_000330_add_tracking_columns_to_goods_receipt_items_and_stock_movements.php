<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receipt_items', function (Blueprint $table): void {
            $table->string('lot_number', 120)->nullable()->after('line_total');
            $table->date('expires_at')->nullable()->after('lot_number');
            $table->json('serial_numbers')->nullable()->after('expires_at');
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->foreignId('product_lot_id')->nullable()->after('product_id')->constrained('product_lots')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('product_lot_id');
        });

        Schema::table('goods_receipt_items', function (Blueprint $table): void {
            $table->dropColumn(['lot_number', 'expires_at', 'serial_numbers']);
        });
    }
};
