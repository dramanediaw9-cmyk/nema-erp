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

class OdooConnection extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'created_by',
        'updated_by',
        'name',
        'protocol',
        'url',
        'database',
        'username',
        'secret',
        'batch_size',
        'stock_location_ids',
        'verify_ssl',
        'import_images',
        'import_stock',
        'is_active',
        'health_status',
        'last_error',
        'last_tested_at',
        'last_sync_at',
    ];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'stock_location_ids' => 'array',
            'batch_size' => 'integer',
            'verify_ssl' => 'boolean',
            'import_images' => 'boolean',
            'import_stock' => 'boolean',
            'is_active' => 'boolean',
            'last_tested_at' => 'datetime',
            'last_sync_at' => 'datetime',
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

    public function runs(): HasMany
    {
        return $this->hasMany(OdooProductImportRun::class)->latest();
    }
}
