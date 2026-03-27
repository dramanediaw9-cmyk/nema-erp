<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_tokens', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('token_hash')->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('integration_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('aggregate_type');
            $table->string('aggregate_id');
            $table->string('event_name');
            $table->json('payload');
            $table->string('status', 20)->default('pending');
            $table->timestamp('available_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->index(['status', 'available_at']);
        });

        Schema::table('journal_entries', function (Blueprint $table): void {
            $table->string('status', 20)->default('posted')->after('journal_code');
            $table->timestamp('posted_at')->nullable()->after('description');
            $table->string('immutable_hash')->nullable()->after('posted_at');
            $table->boolean('is_reversal')->default(false)->after('immutable_hash');
            $table->foreignId('reverses_journal_entry_id')->nullable()->after('is_reversal')->constrained('journal_entries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reverses_journal_entry_id');
            $table->dropColumn(['status', 'posted_at', 'immutable_hash', 'is_reversal']);
        });

        Schema::dropIfExists('integration_events');
        Schema::dropIfExists('api_tokens');
    }
};
