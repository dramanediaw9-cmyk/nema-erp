<?php

namespace App\Modules\Core\Platform\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Modules\Core\Company\Models\Company;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorePulseSnapshot extends Model
{
    use HasFactory;
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'status',
        'score',
        'sla_target',
        'sla_met',
        'signals',
        'metrics',
        'recommendations',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'sla_target' => 'integer',
            'sla_met' => 'boolean',
            'signals' => 'array',
            'metrics' => 'array',
            'recommendations' => 'array',
            'captured_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
