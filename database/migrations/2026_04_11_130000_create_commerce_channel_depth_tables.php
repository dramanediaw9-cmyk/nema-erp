<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commerce_channel_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('commerce_channel_id')->constrained('commerce_channels')->cascadeOnDelete();
            $table->date('snapshot_date');
            $table->decimal('gross_revenue', 15, 2)->default(0);
            $table->unsignedInteger('orders_count')->default(0);
            $table->decimal('average_order_value', 15, 2)->default(0);
            $table->decimal('conversion_rate', 5, 2)->default(0);
            $table->decimal('service_level', 5, 2)->default(0);
            $table->unsignedInteger('failed_orders_count')->default(0);
            $table->unsignedInteger('failed_payments_count')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'commerce_channel_id', 'snapshot_date'], 'commerce_channel_snapshots_unique');
            $table->index(['company_id', 'snapshot_date']);
        });

        Schema::create('commerce_channel_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('commerce_channel_id')->constrained('commerce_channels')->cascadeOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('action_type', 30)->default('campaign');
            $table->string('status', 30)->default('todo');
            $table->string('impact_level', 20)->default('normal');
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commerce_channel_actions');
        Schema::dropIfExists('commerce_channel_snapshots');
    }
};
