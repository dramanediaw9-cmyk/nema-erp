<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_portal_actions', function (Blueprint $table): void {
            $table->longText('signature_image_data_url')->nullable()->after('signature_hash');
        });
    }

    public function down(): void
    {
        Schema::table('sales_portal_actions', function (Blueprint $table): void {
            $table->dropColumn('signature_image_data_url');
        });
    }
};
