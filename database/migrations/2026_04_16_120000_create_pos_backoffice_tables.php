<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_payment_methods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('cash_account_id')->nullable()->constrained('cash_accounts')->nullOnDelete();
            $table->string('method_code', 40);
            $table->string('label', 100);
            $table->string('transaction_label', 100)->nullable();
            $table->boolean('requires_reference')->default(false);
            $table->boolean('supports_change')->default(false);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'method_code']);
        });

        Schema::create('pos_loyalty_programs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('code', 40);
            $table->string('name', 120);
            $table->string('program_type', 30)->default('discount');
            $table->string('trigger_mode', 30)->default('ticket_total');
            $table->string('reward_unit', 30)->default('percent');
            $table->decimal('reward_value', 14, 2)->default(0);
            $table->decimal('min_ticket_total', 14, 2)->default(0);
            $table->date('active_from')->nullable();
            $table->date('active_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('pos_stored_value_cards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('partner_id')->nullable()->constrained('partners')->nullOnDelete();
            $table->string('card_type', 30)->default('gift_card');
            $table->string('code', 80);
            $table->string('holder_name', 120)->nullable();
            $table->string('currency_code', 10)->nullable();
            $table->decimal('balance', 14, 2)->default(0);
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->string('status', 30)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('pos_preparation_printers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('code', 40);
            $table->string('name', 120);
            $table->string('target_area', 80)->nullable();
            $table->string('connection_type', 30)->default('network');
            $table->string('endpoint', 255)->nullable();
            $table->unsignedSmallInteger('copy_count')->default(1);
            $table->unsignedSmallInteger('prep_time_target_minutes')->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('pos_preparation_displays', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('code', 40);
            $table->string('name', 120);
            $table->string('target_area', 80)->nullable();
            $table->string('display_mode', 30)->default('kitchen');
            $table->string('endpoint', 255)->nullable();
            $table->unsignedSmallInteger('refresh_seconds')->default(20);
            $table->unsignedSmallInteger('prep_time_target_minutes')->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('pos_note_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name', 120);
            $table->string('usage', 30)->default('receipt');
            $table->longText('content');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('pos_combo_choices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('parent_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('code', 40);
            $table->string('name', 120);
            $table->json('component_product_ids')->nullable();
            $table->string('pricing_mode', 30)->default('sum');
            $table->decimal('price_override', 14, 2)->nullable();
            $table->unsignedSmallInteger('max_selectable')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('pos_menu_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name', 120);
            $table->string('color', 20)->nullable();
            $table->json('product_ids')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('pos_product_tags', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name', 120);
            $table->string('color', 20)->nullable();
            $table->json('product_ids')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('pos_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('cash_account_id')->nullable()->constrained('cash_accounts')->nullOnDelete();
            $table->foreignId('price_list_id')->nullable()->constrained('price_lists')->nullOnDelete();
            $table->foreignId('loyalty_program_id')->nullable()->constrained('pos_loyalty_programs')->nullOnDelete();
            $table->foreignId('note_template_id')->nullable()->constrained('pos_note_templates')->nullOnDelete();
            $table->foreignId('default_printer_id')->nullable()->constrained('pos_preparation_printers')->nullOnDelete();
            $table->foreignId('default_display_id')->nullable()->constrained('pos_preparation_displays')->nullOnDelete();
            $table->string('code', 40);
            $table->string('name', 120);
            $table->json('active_payment_methods')->nullable();
            $table->json('cash_denomination_preset')->nullable();
            $table->boolean('open_with_cash_control')->default(true);
            $table->boolean('auto_print_receipt')->default(true);
            $table->boolean('allow_draft_orders')->default(true);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_profiles');
        Schema::dropIfExists('pos_product_tags');
        Schema::dropIfExists('pos_menu_categories');
        Schema::dropIfExists('pos_combo_choices');
        Schema::dropIfExists('pos_note_templates');
        Schema::dropIfExists('pos_preparation_displays');
        Schema::dropIfExists('pos_preparation_printers');
        Schema::dropIfExists('pos_stored_value_cards');
        Schema::dropIfExists('pos_loyalty_programs');
        Schema::dropIfExists('pos_payment_methods');
    }
};
