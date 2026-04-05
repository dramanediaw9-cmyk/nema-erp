<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->string('customer_reference')->nullable()->after('requested_delivery_date');
            $table->string('source_document')->nullable()->after('customer_reference');
            $table->string('salesperson_name')->nullable()->after('source_document');
            $table->date('commitment_date')->nullable()->after('salesperson_name');
            $table->text('delivery_instruction')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropColumn([
                'customer_reference',
                'source_document',
                'salesperson_name',
                'commitment_date',
                'delivery_instruction',
            ]);
        });
    }
};
