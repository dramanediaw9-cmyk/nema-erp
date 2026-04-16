<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_preparation_tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('pos_session_id')->nullable()->constrained('pos_sessions')->nullOnDelete();
            $table->foreignId('sales_invoice_id')->constrained('sales_invoices')->cascadeOnDelete();
            $table->foreignId('pos_profile_id')->nullable()->constrained('pos_profiles')->nullOnDelete();
            $table->foreignId('printer_id')->nullable()->constrained('pos_preparation_printers')->nullOnDelete();
            $table->foreignId('display_id')->nullable()->constrained('pos_preparation_displays')->nullOnDelete();
            $table->string('ticket_number', 60);
            $table->string('target_area', 120)->nullable();
            $table->string('status', 30)->default('queued');
            $table->string('priority', 30)->default('normal');
            $table->unsignedSmallInteger('target_minutes')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('served_at')->nullable();
            $table->longText('note_snapshot')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'ticket_number'], 'pos_prep_tickets_company_ticket_unique');
            $table->index(['company_id', 'branch_id', 'status'], 'pos_prep_tickets_company_branch_status_idx');
            $table->index(['sales_invoice_id', 'status'], 'pos_prep_tickets_invoice_status_idx');
        });

        Schema::create('pos_preparation_ticket_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('preparation_ticket_id')->constrained('pos_preparation_tickets')->cascadeOnDelete();
            $table->foreignId('sales_invoice_item_id')->constrained('sales_invoice_items')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('description', 255);
            $table->decimal('qty', 12, 3)->default(0);
            $table->string('status', 30)->default('queued');
            $table->string('combo_label', 120)->nullable();
            $table->json('menu_category_labels')->nullable();
            $table->json('tag_labels')->nullable();
            $table->timestamps();

            $table->unique(['preparation_ticket_id', 'sales_invoice_item_id'], 'pos_prep_ticket_items_ticket_invoice_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_preparation_ticket_items');
        Schema::dropIfExists('pos_preparation_tickets');
    }
};
