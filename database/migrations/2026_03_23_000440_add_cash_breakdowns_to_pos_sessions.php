<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_sessions', function (Blueprint $table) {
            $table->json('opening_cash_breakdown')->nullable()->after('opening_amount');
            $table->json('closing_cash_breakdown')->nullable()->after('counted_breakdown');
        });
    }

    public function down(): void
    {
        Schema::table('pos_sessions', function (Blueprint $table) {
            $table->dropColumn(['opening_cash_breakdown', 'closing_cash_breakdown']);
        });
    }
};
