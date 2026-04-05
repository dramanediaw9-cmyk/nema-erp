<?php

namespace App\Modules\Core\Branch\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Models\Company;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BranchController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger)
    {
    }

    public function index(CurrentWorkspace $workspace): View
    {
        return view('branches.index', [
            'company' => $workspace->company(),
            'branches' => Branch::query()
                ->when($workspace->companyId(), fn ($query, $companyId) => $query->where('company_id', $companyId))
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->paginate(12),
        ]);
    }

    public function create(CurrentWorkspace $workspace): View|RedirectResponse
    {
        if (! $workspace->company()) {
            return redirect()->route('companies.index')->with('error', 'Créez d\'abord une entreprise pour ajouter des agences.');
        }

        return view('branches.create', [
            'branch' => new Branch(),
            'company' => $workspace->company(),
        ]);
    }

    public function store(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $company = $workspace->company();

        abort_if(! $company, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:branches,code,NULL,id,company_id,'.$company->id],
            'city' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $data['company_id'] = $company->id;
        $data['is_active'] = $request->boolean('is_active', true);
        $data['is_default'] = $request->boolean('is_default');

        if ($data['is_default']) {
            Branch::query()->where('company_id', $company->id)->update(['is_default' => false]);
        }

        $branch = Branch::query()->create($data);
        $this->activityLogger->log('branches.create', 'Création agence', $branch, $data);

        return redirect()->route('branches.index')->with('success', 'Agence créée avec succès.');
    }

    public function edit(Branch $branch, CurrentWorkspace $workspace): View
    {
        abort_if($workspace->companyId() !== $branch->company_id, 403);

        return view('branches.edit', [
            'branch' => $branch,
            'company' => $workspace->company(),
        ]);
    }

    public function update(Request $request, Branch $branch, CurrentWorkspace $workspace): RedirectResponse
    {
        abort_if($workspace->companyId() !== $branch->company_id, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:branches,code,'.$branch->id.',id,company_id,'.$branch->company_id],
            'city' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);
        $data['is_default'] = $request->boolean('is_default');

        if ($data['is_default']) {
            Branch::query()->where('company_id', $branch->company_id)->update(['is_default' => false]);
        }

        $branch->update($data);
        $this->activityLogger->log('branches.update', 'Mise à jour agence', $branch, $data);

        return redirect()->route('branches.index')->with('success', 'Agence mise à jour avec succès.');
    }

    public function switch(Request $request, Branch $branch, CurrentWorkspace $workspace): RedirectResponse
    {
        abort_if($workspace->companyId() !== $branch->company_id, 403);

        $request->session()->put('current_branch_id', $branch->id);
        $this->activityLogger->log('branches.switch', 'Changement agence active', $branch);

        return back()->with('success', 'Agence active changée.');
    }
}
