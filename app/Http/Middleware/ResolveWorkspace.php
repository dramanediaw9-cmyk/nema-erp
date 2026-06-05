<?php

namespace App\Http\Middleware;

use App\Support\WorkspaceAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveWorkspace
{
    public function __construct(private WorkspaceAccess $access)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $tenant = $this->access->tenantFor(
            $user,
            (int) $request->session()->get('current_tenant_id', $user->tenant_id ?? 0) ?: null
        );

        $company = $this->access->companyFor(
            $user,
            $tenant?->id,
            (int) $request->session()->get('current_company_id', $user->company_id ?? 0) ?: null
        );

        $branch = $this->access->branchFor(
            $user,
            $company?->id,
            (int) $request->session()->get('current_branch_id', $user->branch_id ?? 0) ?: null
        );

        $request->session()->put('current_tenant_id', $tenant?->id);
        $request->session()->put('current_company_id', $company?->id);
        $request->session()->put('current_branch_id', $branch?->id);

        return $next($request);
    }
}
