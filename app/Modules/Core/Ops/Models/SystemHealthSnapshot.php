<?php

namespace App\Modules\Core\Ops\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Modules\Core\Company\Models\Company;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemHealthSnapshot extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'scope',
        'overall_status',
        'warning_count',
        'failure_count',
        'checks',
        'meta',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'checks' => 'array',
            'meta' => 'array',
            'captured_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
