<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('partner_id')->nullable()->constrained('partners')->nullOnDelete();
            $table->string('lead_name');
            $table->string('title');
            $table->string('contact_name')->nullable();
            $table->string('contact_phone', 50)->nullable();
            $table->string('contact_email')->nullable();
            $table->string('source', 100)->nullable();
            $table->string('stage', 30)->default('new');
            $table->decimal('expected_amount', 15, 2)->nullable();
            $table->date('expected_close_date')->nullable();
            $table->date('last_contact_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'stage']);
            $table->index(['company_id', 'expected_close_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_opportunities');
    }
};
