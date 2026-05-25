<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE payments MODIFY payment_type ENUM('customer_receipt', 'customer_refund', 'supplier_payment', 'pos_refund', 'internal_transfer', 'other') NOT NULL DEFAULT 'customer_receipt'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE payments MODIFY payment_type ENUM('customer_receipt', 'supplier_payment', 'pos_refund', 'internal_transfer', 'other') NOT NULL DEFAULT 'customer_receipt'");
    }
};
