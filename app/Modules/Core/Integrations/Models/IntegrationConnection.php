<?php

namespace App\Modules\Core\Integrations\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Models\Company;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationConnection extends Model
{
    use HasFactory;
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'owner_id',
        'code',
        'name',
        'partner_name',
        'connection_type',
        'sync_mode',
        'status',
        'health_status',
        'external_reference',
        'last_sync_at',
        'last_health_at',
        'scope_summary',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'last_sync_at' => 'datetime',
            'last_health_at' => 'datetime',
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
}
