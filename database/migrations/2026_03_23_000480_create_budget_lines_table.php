<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained()->cascadeOnDelete();
            $table->string('metric', 40);
            $table->unsignedTinyInteger('period_month');
            $table->decimal('amount', 15, 2);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['budget_id', 'metric', 'period_month']);
            $table->index(['metric', 'period_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_lines');
    }
};
