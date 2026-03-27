<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_sessions', function (Blueprint $table) {
            $table->json('expected_breakdown')->nullable()->after('expected_amount');
            $table->json('counted_breakdown')->nullable()->after('closing_amount');
            $table->json('variance_breakdown')->nullable()->after('variance_amount');
        });
    }

    public function down(): void
    {
        Schema::table('pos_sessions', function (Blueprint $table) {
            $table->dropColumn(['expected_breakdown', 'counted_breakdown', 'variance_breakdown']);
        });
    }
};
