<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_sessions', function (Blueprint $table): void {
            $table->timestamp('unlocked_at')->nullable()->after('closed_by');
            $table->foreignId('unlocked_by')->nullable()->after('unlocked_at')->constrained('users')->nullOnDelete();
            $table->text('unlock_reason')->nullable()->after('unlocked_by');
        });
    }

    public function down(): void
    {
        Schema::table('pos_sessions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('unlocked_by');
            $table->dropColumn(['unlocked_at', 'unlock_reason']);
        });
    }
};
