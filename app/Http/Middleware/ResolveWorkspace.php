<?php

namespace App\Http\Middleware;

use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Core\Company\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveWorkspace
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $tenantId = (int) $request->session()->get('current_tenant_id', $user->tenant_id ?? 0);
        $companyId = (int) $request->session()->get('current_company_id', $user->company_id ?? 0);
        $branchId = (int) $request->session()->get('current_branch_id', $user->branch_id ?? 0);

        if (! $tenantId && $user->company?->tenant_id) {
            $tenantId = (int) $user->company->tenant_id;
        }

        if (! $tenantId) {
            $tenantId = (int) Tenant::query()->value('id');
        }

        if (! $companyId && $user->company_id === null) {
            $companyId = (int) Company::query()
                ->when($tenantId > 0, fn ($query) => $query->where('tenant_id', $tenantId))
                ->value('id');
        }

        if ($tenantId) {
            $request->session()->put('current_tenant_id', $tenantId);
        }

        if ($companyId) {
            $request->session()->put('current_company_id', $companyId);
        }

        if ($companyId) {
            $branch = Branch::query()
                ->where('company_id', $companyId)
                ->when($branchId > 0, fn ($query) => $query->whereKey($branchId))
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->first();

            if ($branch) {
                $request->session()->put('current_branch_id', $branch->id);
            }
        }

        return $next($request);
    }
}
