<?php

namespace App\Modules\Commerce\Models;

use App\Models\User;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Models\Company;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CommerceChannel extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'branch_id',
        'code',
        'name',
        'channel_type',
        'status',
        'connector_name',
        'settlement_mode',
        'target_monthly_revenue',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'target_monthly_revenue' => 'decimal:2',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(CommerceChannelSnapshot::class, 'commerce_channel_id')
            ->orderByDesc('snapshot_date')
            ->orderByDesc('id');
    }

    public function latestSnapshot(): HasOne
    {
        return $this->hasOne(CommerceChannelSnapshot::class, 'commerce_channel_id')->latestOfMany('snapshot_date');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(CommerceChannelAction::class, 'commerce_channel_id')
            ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_date')
            ->orderBy('id');
    }
}
