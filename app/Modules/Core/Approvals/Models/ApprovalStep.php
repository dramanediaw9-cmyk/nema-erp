<?php

namespace App\Modules\Core\Approvals\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ApprovalStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'approvable_type',
        'approvable_id',
        'module',
        'step_order',
        'code',
        'label',
        'rule',
        'status',
        'assigned_to',
        'due_at',
        'delegated_by',
        'delegated_at',
        'escalated_at',
        'meta',
        'approved_at',
        'approved_by',
        'rejected_at',
        'rejected_by',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'delegated_at' => 'datetime',
            'escalated_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function assignedApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function delegatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegated_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isOverdue(): bool
    {
        return $this->isPending() && $this->due_at?->isPast() === true;
    }
}
