<?php

namespace App\Modules\Pos\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Models\Company;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosLoyaltyProgram extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'code',
        'name',
        'program_type',
        'trigger_mode',
        'reward_unit',
        'reward_value',
        'min_ticket_total',
        'active_from',
        'active_to',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'reward_value' => 'decimal:2',
            'min_ticket_total' => 'decimal:2',
            'active_from' => 'date',
            'active_to' => 'date',
            'is_active' => 'boolean',
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
}
