<?php

namespace App\Modules\Core\Company\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Company\Models\Company;
use App\Support\ActivityLogger;
use App\Support\WorkspaceAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CompanyWorkspaceController extends Controller
{
    public function __invoke(
        Request $request,
        Company $company,
        WorkspaceAccess $access,
        ActivityLogger $activityLogger
    ): RedirectResponse {
        $user = $request->user();

        abort_if(! $user || ! $company->is_active || ! $access->canAccessCompany($user, $company), 403);

        $branch = $access->branchFor($user, $company->id);

        $request->session()->put('current_tenant_id', $company->tenant_id);
        $request->session()->put('current_company_id', $company->id);
        $request->session()->put('current_branch_id', $branch?->id);

        $activityLogger->log(
            'companies.switch',
            'Changement entreprise active',
            $company,
            ['branch_id' => $branch?->id]
        );

        return redirect()->route('dashboard')->with('success', 'Entreprise active : '.$company->name.'.');
    }
}
