<?php

namespace App\Modules\Pos\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Models\Company;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosPreparationPrinter extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'code',
        'name',
        'target_area',
        'connection_type',
        'endpoint',
        'copy_count',
        'prep_time_target_minutes',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'copy_count' => 'integer',
            'prep_time_target_minutes' => 'integer',
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

    public function tickets(): HasMany
    {
        return $this->hasMany(PosPreparationTicket::class, 'printer_id');
    }
}
