<?php

namespace App\Modules\Core\Company\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentTerm extends Model
{
    use HasFactory;
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'code',
        'name',
        'days',
        'description',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'days' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function partners(): HasMany
    {
        return $this->hasMany(\App\Modules\Partners\Models\Partner::class);
    }
}
