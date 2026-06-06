<?php

namespace App\Support;

use App\Models\User;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Core\Company\Models\Tenant;

class WorkspaceAccess
{
    public function canAccessTenant(User $user, Tenant $tenant): bool
    {
        if ($user->hasRole('platform_admin')) {
            return true;
        }

        if ((int) $user->tenant_id === (int) $tenant->id) {
            return true;
        }

        return (int) ($user->company?->tenant_id ?? 0) === (int) $tenant->id;
    }

    public function tenantFor(User $user, ?int $tenantId = null): ?Tenant
    {
        if ($tenantId) {
            $tenant = Tenant::query()->find($tenantId);

            if ($tenant && $this->canAccessTenant($user, $tenant)) {
                return $tenant;
            }
        }

        if ($user->tenant_id) {
            $tenant = Tenant::query()->find($user->tenant_id);

            if ($tenant && $this->canAccessTenant($user, $tenant)) {
                return $tenant;
            }
        }

        if ($user->company?->tenant) {
            return $user->company->tenant;
        }

        if ($user->hasRole('platform_admin')) {
            return Tenant::query()->orderBy('id')->first();
        }

        return null;
    }

    public function canAccessCompany(User $user, Company $company): bool
    {
        if ($user->hasRole('platform_admin')) {
            return true;
        }

        if ($user->company_id) {
            return (int) $user->company_id === (int) $company->id;
        }

        if ($user->tenant_id) {
            return (int) $user->tenant_id === (int) $company->tenant_id;
        }

        return false;
    }

    public function companyFor(User $user, ?int $tenantId = null, ?int $companyId = null): ?Company
    {
        if ($companyId) {
            $company = Company::query()->find($companyId);

            if ($company && $this->canAccessCompany($user, $company)) {
                return $company;
            }
        }

        if ($user->company_id) {
            $company = Company::query()->find($user->company_id);

            if ($company && $this->canAccessCompany($user, $company)) {
                return $company;
            }
        }

        $query = Company::query();

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        } elseif (! $user->hasRole('platform_admin') && $user->tenant_id) {
            $query->where('tenant_id', $user->tenant_id);
        }

        return $query
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->first(fn (Company $company) => $this->canAccessCompany($user, $company));
    }

    public function canAccessBranch(User $user, Branch $branch): bool
    {
        if (! $branch->company || ! $this->canAccessCompany($user, $branch->company)) {
            return false;
        }

        if ($user->canAccessAllBranches()) {
            return true;
        }

        if (! $user->branch_id) {
            return true;
        }

        return (int) $user->branch_id === (int) $branch->id;
    }

    public function branchFor(User $user, ?int $companyId = null, ?int $branchId = null): ?Branch
    {
        if ($branchId) {
            $branch = Branch::query()->with('company')->find($branchId);

            if (
                $branch
                && (! $companyId || (int) $branch->company_id === (int) $companyId)
                && $this->canAccessBranch($user, $branch)
            ) {
                return $branch;
            }
        }

        if ($user->branch_id) {
            $branch = Branch::query()->with('company')->find($user->branch_id);

            if ($branch && (! $companyId || (int) $branch->company_id === (int) $companyId) && $this->canAccessBranch($user, $branch)) {
                return $branch;
            }
        }

        if (! $companyId) {
            return null;
        }

        return Branch::query()
            ->with('company')
            ->where('company_id', $companyId)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->first(fn (Branch $branch) => $this->canAccessBranch($user, $branch));
    }
}
