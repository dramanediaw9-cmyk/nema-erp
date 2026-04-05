<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Services\ChartOfAccountsService;
use App\Support\CurrentWorkspace;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function __construct(private readonly ChartOfAccountsService $chartOfAccountsService)
    {
    }

    public function index(CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $this->chartOfAccountsService->ensureDefaultAccounts($companyId);

        return view('accounting.accounts.index', [
            'accounts' => Account::query()
                ->where('company_id', $companyId)
                ->orderBy('code')
                ->paginate(50),
        ]);
    }
}
