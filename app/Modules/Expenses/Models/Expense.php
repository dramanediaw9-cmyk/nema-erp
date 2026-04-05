<?php

namespace App\Modules\Expenses\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasDocumentCollaboration;
use App\Models\User;
use App\Modules\Core\Approvals\Models\ApprovalStep;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Partners\Models\Partner;
use App\Modules\Treasury\Models\CashAccount;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Expense extends Model
{
    use HasFactory;
    use BelongsToTenant;
    use HasDocumentCollaboration;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'expense_category_id',
        'supplier_id',
        'cash_account_id',
        'expense_number',
        'expense_date',
        'description',
        'total',
        'status',
        'payment_status',
        'payment_date',
        'payment_method',
        'payment_reference',
        'notes',
        'approved_at',
        'approved_by',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'payment_date' => 'date',
            'approved_at' => 'datetime',
            'total' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'supplier_id');
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function approvalSteps(): MorphMany
    {
        return $this->morphMany(ApprovalStep::class, 'approvable')->orderBy('step_order');
    }

    public function isPendingApproval(): bool
    {
        return $this->status === 'pending_approval';
    }
}
