<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('odoo_connections', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('protocol', 20)->default('jsonrpc');
            $table->string('url');
            $table->string('database');
            $table->string('username');
            $table->text('secret');
            $table->unsignedSmallInteger('batch_size')->default(250);
            $table->json('stock_location_ids')->nullable();
            $table->boolean('verify_ssl')->default(true);
            $table->boolean('import_images')->default(true);
            $table->boolean('import_stock')->default(true);
            $table->boolean('is_active')->default(true);
            $table->string('health_status', 20)->default('untested');
            $table->text('last_error')->nullable();
            $table->dateTime('last_tested_at')->nullable();
            $table->dateTime('last_sync_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'name']);
            $table->index(['company_id', 'is_active']);
        });

        Schema::create('odoo_product_import_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('odoo_connection_id')->constrained('odoo_connections')->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('mode', 20)->default('incremental');
            $table->string('status', 20)->default('queued');
            $table->string('phase', 30)->default('templates');
            $table->unsignedBigInteger('cursor_id')->default(0);
            $table->unsignedBigInteger('source_total')->default(0);
            $table->unsignedBigInteger('processed_count')->default(0);
            $table->unsignedBigInteger('created_count')->default(0);
            $table->unsignedBigInteger('updated_count')->default(0);
            $table->unsignedBigInteger('skipped_count')->default(0);
            $table->unsignedBigInteger('failed_count')->default(0);
            $table->unsignedBigInteger('batch_count')->default(0);
            $table->dateTime('incremental_since')->nullable();
            $table->dateTime('sync_cutoff_at')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('heartbeat_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('options')->nullable();
            $table->timestamps();

            $table->index(['odoo_connection_id', 'status']);
            $table->index(['company_id', 'created_at']);
        });

        Schema::create('odoo_product_mappings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('odoo_connection_id')->constrained('odoo_connections')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('odoo_model', 40);
            $table->unsignedBigInteger('odoo_id');
            $table->unsignedBigInteger('odoo_template_id')->nullable();
            $table->string('source_hash', 64)->nullable();
            $table->dateTime('odoo_write_date')->nullable();
            $table->dateTime('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['odoo_connection_id', 'odoo_model', 'odoo_id'], 'odoo_product_external_unique');
            $table->index(['company_id', 'product_id']);
            $table->index(['odoo_connection_id', 'odoo_template_id']);
        });

        Schema::create('odoo_product_import_errors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('odoo_product_import_run_id')->constrained('odoo_product_import_runs')->cascadeOnDelete();
            $table->string('odoo_model', 40)->nullable();
            $table->unsignedBigInteger('odoo_id')->nullable();
            $table->string('phase', 30)->nullable();
            $table->text('message');
            $table->json('context')->nullable();
            $table->boolean('retryable')->default(false);
            $table->timestamps();

            $table->index(['odoo_product_import_run_id', 'created_at'], 'odoo_import_error_run_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('odoo_product_import_errors');
        Schema::dropIfExists('odoo_product_mappings');
        Schema::dropIfExists('odoo_product_import_runs');
        Schema::dropIfExists('odoo_connections');
    }
};
