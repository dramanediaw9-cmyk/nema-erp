<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('asset_number');
            $table->string('name');
            $table->string('category')->nullable();
            $table->date('acquisition_date');
            $table->date('commissioning_date')->nullable();
            $table->date('depreciation_start_date');
            $table->string('depreciation_method', 30)->default('linear');
            $table->unsignedSmallInteger('useful_life_months');
            $table->decimal('acquisition_cost', 15, 2);
            $table->decimal('salvage_value', 15, 2)->default(0);
            $table->string('status', 30)->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'asset_number']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'category']);
            $table->index(['company_id', 'depreciation_start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_assets');
    }
};
