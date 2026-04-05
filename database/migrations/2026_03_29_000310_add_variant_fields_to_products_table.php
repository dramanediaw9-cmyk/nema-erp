<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('parent_product_id')->nullable()->after('category_id')->constrained('products')->nullOnDelete();
            $table->boolean('is_variant')->default(false)->after('parent_product_id');
            $table->string('variant_label')->nullable()->after('is_variant');
            $table->string('variant_signature', 190)->nullable()->after('variant_label');
            $table->unique(['company_id', 'parent_product_id', 'variant_signature'], 'prd_variant_sig_unique');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropUnique('prd_variant_sig_unique');
            $table->dropConstrainedForeignId('parent_product_id');
            $table->dropColumn(['is_variant', 'variant_label', 'variant_signature']);
        });
    }
};
