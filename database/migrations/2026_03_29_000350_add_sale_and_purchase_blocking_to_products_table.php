<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('sale_blocked')->default(false)->after('purchase_ok');
            $table->text('sale_block_reason')->nullable()->after('sale_blocked');
            $table->boolean('purchase_blocked')->default(false)->after('sale_block_reason');
            $table->text('purchase_block_reason')->nullable()->after('purchase_blocked');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn([
                'sale_blocked',
                'sale_block_reason',
                'purchase_blocked',
                'purchase_block_reason',
            ]);
        });
    }
};
