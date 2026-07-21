<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_profiles', function (Blueprint $table): void {
            $table->string('stock_policy', 20)->default('block')->after('allow_draft_orders');
            $table->boolean('show_stock_quantity')->default(true)->after('stock_policy');
            $table->boolean('show_product_images')->default(true)->after('show_stock_quantity');
            $table->boolean('group_products_by_category')->default(true)->after('show_product_images');
            $table->boolean('share_open_orders')->default(false)->after('group_products_by_category');
            $table->boolean('quick_cash_payment')->default(false)->after('share_open_orders');
            $table->boolean('cash_rounding_enabled')->default(false)->after('quick_cash_payment');
            $table->decimal('cash_rounding_precision', 12, 2)->default(5)->after('cash_rounding_enabled');
            $table->decimal('max_cash_variance', 15, 2)->nullable()->after('cash_rounding_precision');
            $table->boolean('allow_tips')->default(false)->after('max_cash_variance');
            $table->boolean('receipt_show_cashier')->default(true)->after('allow_tips');
            $table->boolean('receipt_show_address')->default(true)->after('receipt_show_cashier');
            $table->string('receipt_logo_path')->nullable()->after('receipt_show_address');
            $table->string('receipt_header', 255)->nullable()->after('receipt_logo_path');
            $table->string('receipt_footer', 255)->nullable()->after('receipt_header');
        });
    }

    public function down(): void
    {
        Schema::table('pos_profiles', function (Blueprint $table): void {
            $table->dropColumn([
                'stock_policy',
                'show_stock_quantity',
                'show_product_images',
                'group_products_by_category',
                'share_open_orders',
                'quick_cash_payment',
                'cash_rounding_enabled',
                'cash_rounding_precision',
                'max_cash_variance',
                'allow_tips',
                'receipt_show_cashier',
                'receipt_show_address',
                'receipt_logo_path',
                'receipt_header',
                'receipt_footer',
            ]);
        });
    }
};
