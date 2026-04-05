<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_portal_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->morphs('actionable');
            $table->string('action_type', 40);
            $table->string('signer_name');
            $table->string('signer_phone', 60)->nullable();
            $table->string('signer_title', 120)->nullable();
            $table->string('signer_company', 120)->nullable();
            $table->text('signer_note')->nullable();
            $table->boolean('accepted_terms')->default(false);
            $table->string('signature_hash', 64);
            $table->dateTime('signed_at');
            $table->decimal('deposit_amount', 15, 2)->nullable();
            $table->string('deposit_method', 40)->nullable();
            $table->string('deposit_reference', 120)->nullable();
            $table->text('deposit_note')->nullable();
            $table->date('deposit_expected_at')->nullable();
            $table->json('properties')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'action_type'], 'sales_portal_actions_company_type_idx');
            $table->index(['company_id', 'signed_at'], 'sales_portal_actions_company_signed_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_portal_actions');
    }
};
