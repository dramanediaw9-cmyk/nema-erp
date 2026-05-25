<?php

namespace App\Modules\Core\Dashboard\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Core\Company\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNavigationFavorite extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'user_id',
        'module_key',
        'sort_order',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
