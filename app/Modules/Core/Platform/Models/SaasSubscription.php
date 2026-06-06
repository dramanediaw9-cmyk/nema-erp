<?php

namespace App\Modules\Core\Platform\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Modules\Core\Company\Models\Company;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaasSubscription extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'plan',
        'status',
        'user_limit',
        'branch_limit',
        'trial_ends_at',
        'starts_at',
        'ends_at',
        'terms_accepted_at',
        'provider',
        'provider_reference',
    ];

    protected function casts(): array
    {
        return [
            'user_limit' => 'integer',
            'branch_limit' => 'integer',
            'trial_ends_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
