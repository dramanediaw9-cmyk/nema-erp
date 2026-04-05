<?php

namespace App\Models\Concerns;

use App\Modules\Core\Company\Models\Company;
use App\Modules\Core\Company\Models\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::creating(function (Model $model): void {
            if (! $model->isFillable('tenant_id') && ! array_key_exists('tenant_id', $model->getAttributes())) {
                return;
            }

            if ($model->getAttribute('tenant_id')) {
                return;
            }

            $tenantId = null;
            $companyId = (int) ($model->getAttribute('company_id') ?? 0);

            if ($companyId > 0) {
                $tenantId = Company::query()->whereKey($companyId)->value('tenant_id');
            }

            if (! $tenantId && Auth::check()) {
                $tenantId = (int) session('current_tenant_id', Auth::user()->tenant_id ?? 0);
            }

            if (! $tenantId && $model instanceof Company) {
                $tenantId = Tenant::query()->value('id') ?: Tenant::query()->create([
                    'code' => 'TENANT-DEFAULT',
                    'name' => 'Tenant principal',
                    'slug' => 'tenant-principal',
                    'is_active' => true,
                ])->id;
            }

            if ($tenantId) {
                $model->forceFill(['tenant_id' => $tenantId]);
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
