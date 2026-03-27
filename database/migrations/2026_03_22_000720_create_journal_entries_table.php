<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('journal_number', 50)->unique();
            $table->string('journal_code', 10);
            $table->date('entry_date');
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('reference', 100)->nullable();
            $table->string('description');
            $table->decimal('total_debit', 15, 2)->default(0);
            $table->decimal('total_credit', 15, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'entry_date']);
            $table->index(['company_id', 'journal_code']);
            $table->index(['source_type', 'source_id']);
            $table->unique(['company_id', 'source_type', 'source_id', 'journal_code'], 'journal_entries_source_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
