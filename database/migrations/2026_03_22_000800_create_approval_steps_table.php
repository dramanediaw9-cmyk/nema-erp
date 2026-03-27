<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->morphs('approvable');
            $table->string('module', 50);
            $table->unsignedSmallInteger('step_order');
            $table->string('code', 100);
            $table->string('label');
            $table->string('rule', 50);
            $table->enum('status', ['pending', 'approved'])->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['approvable_type', 'approvable_id', 'step_order'], 'approval_steps_document_order_unique');
            $table->index(['company_id', 'module', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_steps');
    }
};
