<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integration_connections', function (Blueprint $table): void {
            $table->string('authentication_mode', 30)->default('api_key')->after('connection_type');
            $table->string('secret_health_status', 30)->default('watch')->after('health_status');
            $table->foreignId('secret_owner_id')->nullable()->after('owner_id')->constrained('users')->nullOnDelete();
            $table->dateTime('secret_last_rotated_at')->nullable()->after('last_health_at');
            $table->dateTime('secret_rotation_due_at')->nullable()->after('secret_last_rotated_at');
            $table->dateTime('secret_expires_at')->nullable()->after('secret_rotation_due_at');
            $table->text('secret_notes')->nullable()->after('scope_summary');

            $table->index(['company_id', 'secret_health_status']);
            $table->index(['company_id', 'secret_rotation_due_at']);
            $table->index(['company_id', 'secret_expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('integration_connections', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('secret_owner_id');
            $table->dropIndex(['company_id', 'secret_health_status']);
            $table->dropIndex(['company_id', 'secret_rotation_due_at']);
            $table->dropIndex(['company_id', 'secret_expires_at']);
            $table->dropColumn([
                'authentication_mode',
                'secret_health_status',
                'secret_last_rotated_at',
                'secret_rotation_due_at',
                'secret_expires_at',
                'secret_notes',
            ]);
        });
    }
};
