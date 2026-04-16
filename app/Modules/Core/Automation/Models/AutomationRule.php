<?php

namespace App\Modules\Core\Automation\Models;

use App\Models\User;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Models\Company;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AutomationRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'branch_id',
        'owner_id',
        'code',
        'name',
        'module_key',
        'signal_key',
        'status',
        'severity',
        'action_type',
        'threshold_value',
        'window_hours',
        'cooldown_minutes',
        'last_evaluated_at',
        'last_triggered_at',
        'last_value',
        'description',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'threshold_value' => 'integer',
            'window_hours' => 'integer',
            'cooldown_minutes' => 'integer',
            'last_value' => 'integer',
            'last_evaluated_at' => 'datetime',
            'last_triggered_at' => 'datetime',
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

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function executions(): HasMany
    {
        return $this->hasMany(AutomationExecution::class)->latest('executed_at');
    }

    public function latestExecution(): HasOne
    {
        return $this->hasOne(AutomationExecution::class)->latestOfMany('executed_at');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
