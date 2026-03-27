<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_terms', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->unsignedInteger('days')->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('price_lists', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('currency_code', 3)->default('XOF');
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('price_list_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('price_list_id')->constrained('price_lists')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('min_qty', 12, 3)->default(1);
            $table->decimal('price', 12, 2);
            $table->timestamps();
            $table->unique(['price_list_id', 'product_id', 'min_qty']);
        });

        Schema::create('tax_rules', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('scope', 20)->default('both');
            $table->string('tax_kind', 20)->default('vat');
            $table->decimal('rate', 5, 2)->default(0);
            $table->string('collect_account_code', 20)->nullable();
            $table->string('deductible_account_code', 20)->nullable();
            $table->boolean('is_default_sales')->default(false);
            $table->boolean('is_default_purchases')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('partner_contacts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->string('name');
            $table->string('role')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        Schema::create('partner_addresses', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('type', 20)->default('billing');
            $table->text('address_line');
            $table->string('city')->nullable();
            $table->string('country')->default('Mali');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        Schema::create('partner_bank_accounts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->string('bank_name');
            $table->string('account_name')->nullable();
            $table->string('account_number');
            $table->string('iban')->nullable();
            $table->string('swift_code')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        Schema::create('partner_mobile_wallets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->string('provider');
            $table->string('wallet_number');
            $table->string('account_name')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        Schema::table('partners', function (Blueprint $table): void {
            $table->foreignId('payment_term_id')->nullable()->after('opening_balance')->constrained('payment_terms')->nullOnDelete();
            $table->foreignId('price_list_id')->nullable()->after('payment_term_id')->constrained('price_lists')->nullOnDelete();
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('sale_tax_rule_id')->nullable()->after('sale_price')->constrained('tax_rules')->nullOnDelete();
            $table->foreignId('purchase_tax_rule_id')->nullable()->after('sale_tax_rule_id')->constrained('tax_rules')->nullOnDelete();
        });

        Schema::table('sales_invoices', function (Blueprint $table): void {
            $table->foreignId('payment_term_id')->nullable()->after('customer_id')->constrained('payment_terms')->nullOnDelete();
            $table->foreignId('price_list_id')->nullable()->after('payment_term_id')->constrained('price_lists')->nullOnDelete();
            $table->decimal('net_total', 12, 2)->default(0)->after('discount_total');
            $table->decimal('tax_total', 12, 2)->default(0)->after('net_total');
        });

        Schema::table('sales_invoice_items', function (Blueprint $table): void {
            $table->foreignId('tax_rule_id')->nullable()->after('discount_total')->constrained('tax_rules')->nullOnDelete();
            $table->decimal('tax_rate', 5, 2)->default(0)->after('tax_rule_id');
            $table->decimal('tax_amount', 12, 2)->default(0)->after('tax_rate');
        });

        Schema::table('purchase_bills', function (Blueprint $table): void {
            $table->foreignId('payment_term_id')->nullable()->after('supplier_id')->constrained('payment_terms')->nullOnDelete();
            $table->decimal('net_total', 12, 2)->default(0)->after('subtotal');
            $table->decimal('tax_total', 12, 2)->default(0)->after('net_total');
        });

        Schema::table('purchase_bill_items', function (Blueprint $table): void {
            $table->foreignId('tax_rule_id')->nullable()->after('unit_cost')->constrained('tax_rules')->nullOnDelete();
            $table->decimal('tax_rate', 5, 2)->default(0)->after('tax_rule_id');
            $table->decimal('tax_amount', 12, 2)->default(0)->after('tax_rate');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_bill_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('tax_rule_id');
            $table->dropColumn(['tax_rate', 'tax_amount']);
        });

        Schema::table('purchase_bills', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('payment_term_id');
            $table->dropColumn(['net_total', 'tax_total']);
        });

        Schema::table('sales_invoice_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('tax_rule_id');
            $table->dropColumn(['tax_rate', 'tax_amount']);
        });

        Schema::table('sales_invoices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('payment_term_id');
            $table->dropConstrainedForeignId('price_list_id');
            $table->dropColumn(['net_total', 'tax_total']);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('sale_tax_rule_id');
            $table->dropConstrainedForeignId('purchase_tax_rule_id');
        });

        Schema::table('partners', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('payment_term_id');
            $table->dropConstrainedForeignId('price_list_id');
        });

        Schema::dropIfExists('partner_mobile_wallets');
        Schema::dropIfExists('partner_bank_accounts');
        Schema::dropIfExists('partner_addresses');
        Schema::dropIfExists('partner_contacts');
        Schema::dropIfExists('tax_rules');
        Schema::dropIfExists('price_list_items');
        Schema::dropIfExists('price_lists');
        Schema::dropIfExists('payment_terms');
    }
};
