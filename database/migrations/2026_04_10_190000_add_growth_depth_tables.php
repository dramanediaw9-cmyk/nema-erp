<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_leave_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->string('leave_number');
            $table->string('leave_type', 30)->default('annual');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('total_days', 8, 2)->default(0);
            $table->string('status', 30)->default('draft');
            $table->string('coverage_plan')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'leave_number']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('payroll_slips', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payroll_run_id')->nullable()->constrained('payroll_runs')->nullOnDelete();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->string('slip_number');
            $table->decimal('base_salary', 15, 2)->default(0);
            $table->decimal('gross_amount', 15, 2)->default(0);
            $table->decimal('deductions_amount', 15, 2)->default(0);
            $table->decimal('employer_contributions_amount', 15, 2)->default(0);
            $table->decimal('net_amount', 15, 2)->default(0);
            $table->string('status', 30)->default('draft');
            $table->string('payout_mode', 30)->default('bank');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'slip_number']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('payroll_slip_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_slip_id')->constrained('payroll_slips')->cascadeOnDelete();
            $table->string('line_type', 30)->default('earning');
            $table->string('code', 40);
            $table->string('label');
            $table->decimal('amount', 15, 2)->default(0);
            $table->unsignedInteger('sequence')->default(1);
            $table->timestamps();

            $table->index(['payroll_slip_id', 'sequence']);
        });

        Schema::create('manufacturing_boms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code');
            $table->string('item_name');
            $table->decimal('output_quantity', 12, 3)->default(1);
            $table->string('status', 30)->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('manufacturing_bom_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('manufacturing_bom_id')->constrained('manufacturing_boms')->cascadeOnDelete();
            $table->string('component_code')->nullable();
            $table->string('component_name');
            $table->decimal('quantity', 12, 3)->default(0);
            $table->string('unit', 20)->default('u');
            $table->decimal('wastage_rate', 8, 2)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedInteger('sequence')->default(1);
            $table->timestamps();

            $table->index(['manufacturing_bom_id', 'sequence']);
        });

        Schema::table('production_orders', function (Blueprint $table): void {
            $table->foreignId('bill_of_material_id')->nullable()->after('reference')->constrained('manufacturing_boms')->nullOnDelete();
            $table->decimal('material_cost_estimate', 15, 2)->default(0)->after('completed_quantity');
            $table->decimal('actual_material_cost', 15, 2)->default(0)->after('material_cost_estimate');
        });
    }

    public function down(): void
    {
        Schema::table('production_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('bill_of_material_id');
            $table->dropColumn(['material_cost_estimate', 'actual_material_cost']);
        });

        Schema::dropIfExists('manufacturing_bom_lines');
        Schema::dropIfExists('manufacturing_boms');
        Schema::dropIfExists('payroll_slip_lines');
        Schema::dropIfExists('payroll_slips');
        Schema::dropIfExists('hr_leave_requests');
    }
};
