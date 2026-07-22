<?php

namespace App\Modules\Core\Imports\Odoo\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Models\Company;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OdooProductImportRun extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'company_id',
        'branch_id',
        'odoo_connection_id',
        'requested_by',
        'mode',
        'status',
        'phase',
        'cursor_id',
        'source_total',
        'processed_count',
        'created_count',
        'updated_count',
        'skipped_count',
        'failed_count',
        'batch_count',
        'incremental_since',
        'sync_cutoff_at',
        'started_at',
        'heartbeat_at',
        'finished_at',
        'last_error',
        'options',
    ];

    protected function casts(): array
    {
        return [
            'cursor_id' => 'integer',
            'source_total' => 'integer',
            'processed_count' => 'integer',
            'created_count' => 'integer',
            'updated_count' => 'integer',
            'skipped_count' => 'integer',
            'failed_count' => 'integer',
            'batch_count' => 'integer',
            'incremental_since' => 'datetime',
            'sync_cutoff_at' => 'datetime',
            'started_at' => 'datetime',
            'heartbeat_at' => 'datetime',
            'finished_at' => 'datetime',
            'options' => 'array',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(OdooConnection::class, 'odoo_connection_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function errors(): HasMany
    {
        return $this->hasMany(OdooProductImportError::class)->latest();
    }

    public function progressPercent(): int
    {
        if ($this->status === 'completed') {
            return 100;
        }

        if ($this->source_total <= 0) {
            return 0;
        }

        return min(99, (int) floor(($this->processed_count / $this->source_total) * 100));
    }
}
