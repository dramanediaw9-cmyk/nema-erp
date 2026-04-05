<?php

namespace App\Support;

use App\Models\User;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Core\Company\Models\Tenant;
use Illuminate\Support\Facades\Auth;

class CurrentWorkspace
{
    public function user(): ?User
    {
        return Auth::user();
    }

    public function tenant(): ?Tenant
    {
        $user = $this->user();

        if (! $user) {
            return null;
        }

        $tenantId = (int) session('current_tenant_id', $user->tenant_id ?? 0);

        if ($tenantId > 0) {
            return Tenant::query()->find($tenantId);
        }

        if ($user->company?->tenant_id) {
            return $user->company->tenant;
        }

        return Tenant::query()->first();
    }

    public function company(): ?Company
    {
        $user = $this->user();

        if (! $user) {
            return null;
        }

        $companyId = (int) session('current_company_id', $user->company_id ?? 0);

        return $companyId ? Company::query()->find($companyId) : $user->company;
    }

    public function branch(): ?Branch
    {
        $user = $this->user();

        if (! $user) {
            return null;
        }

        $branchId = (int) session('current_branch_id', $user->branch_id ?? 0);

        return $branchId ? Branch::query()->find($branchId) : $user->branch;
    }

    public function tenantId(): ?int
    {
        return $this->tenant()?->id;
    }

    public function companyId(): ?int
    {
        return $this->company()?->id;
    }

    public function branchId(): ?int
    {
        return $this->branch()?->id;
    }
}
