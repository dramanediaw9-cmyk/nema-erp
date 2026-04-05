<?php

namespace App\Modules\Treasury\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Treasury\Models\CashAccount;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CashAccountController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger)
    {
    }

    public function index(CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        return view('cash-accounts.index', [
            'accounts' => CashAccount::query()
                ->with('branch')
                ->where('company_id', $companyId)
                ->orderBy('name')
                ->paginate(15),
        ]);
    }

    public function create(CurrentWorkspace $workspace): View
    {
        abort_if(! $workspace->companyId(), 403);

        return view('cash-accounts.create', [
            'account' => new CashAccount(['is_active' => true, 'type' => 'cash']),
            'branches' => $workspace->company()?->branches()->orderBy('name')->get() ?? collect(),
        ]);
    }

    public function store(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $data = $this->validateAccount($request, $companyId);
        $data['company_id'] = $companyId;
        $data['is_active'] = $request->boolean('is_active', true);

        $account = CashAccount::query()->create($data);
        $this->activityLogger->log('cash_accounts.create', 'Creation compte de tresorerie', $account);

        return redirect()->route('cash-accounts.index')->with('success', 'Compte de tresorerie cree avec succes.');
    }

    public function edit(CashAccount $cashAccount, CurrentWorkspace $workspace): View
    {
        abort_if($workspace->companyId() !== $cashAccount->company_id, 403);

        return view('cash-accounts.edit', [
            'account' => $cashAccount,
            'branches' => $workspace->company()?->branches()->orderBy('name')->get() ?? collect(),
        ]);
    }

    public function update(Request $request, CashAccount $cashAccount, CurrentWorkspace $workspace): RedirectResponse
    {
        abort_if($workspace->companyId() !== $cashAccount->company_id, 403);

        $data = $this->validateAccount($request, $cashAccount->company_id, $cashAccount->id);
        $data['is_active'] = $request->boolean('is_active', true);

        $cashAccount->update($data);
        $this->activityLogger->log('cash_accounts.update', 'Mise a jour compte de tresorerie', $cashAccount);

        return redirect()->route('cash-accounts.index')->with('success', 'Compte de tresorerie mis a jour avec succes.');
    }

    private function validateAccount(Request $request, int $companyId, ?int $ignoreId = null): array
    {
        return $request->validate([
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('cash_accounts', 'name')->where(fn ($query) => $query->where('company_id', $companyId))->ignore($ignoreId),
            ],
            'type' => ['required', Rule::in(['cash', 'bank', 'mobile_money'])],
            'account_number' => ['nullable', 'string', 'max:255'],
            'opening_balance' => ['required', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
