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

        DB::statement("ALTER TABLE stock_movements MODIFY movement_type ENUM('opening', 'purchase', 'sale', 'adjustment_in', 'adjustment_out') NOT NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE stock_movements MODIFY movement_type ENUM('opening', 'sale', 'adjustment_in', 'adjustment_out') NOT NULL");
    }
};
