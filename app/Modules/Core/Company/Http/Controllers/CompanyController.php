<?php

namespace App\Modules\Core\Company\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Company\Models\Company;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger)
    {
    }

    public function index(CurrentWorkspace $workspace): View
    {
        $query = Company::query()->latest();

        if (! $this->canAccessAllCompanies($workspace)) {
            abort_if(! $workspace->companyId(), 403);
            $query->whereKey($workspace->companyId());
        }

        return view('companies.index', [
            'companies' => $query->paginate(12),
        ]);
    }

    public function create(CurrentWorkspace $workspace): View
    {
        $this->ensurePlatformAdmin($workspace);

        return view('companies.create', [
            'company' => new Company(),
        ]);
    }

    public function store(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $this->ensurePlatformAdmin($workspace);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'nif' => ['nullable', 'string', 'max:100', 'unique:companies,nif'],
            'rccm' => ['nullable', 'string', 'max:100', 'unique:companies,rccm'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'currency_code' => ['required', 'string', 'size:3'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        $company = Company::query()->create($data);
        $this->activityLogger->log('companies.create', 'Création entreprise', $company, $data);

        return redirect()->route('companies.index')->with('success', 'Entreprise créée avec succès.');
    }

    public function edit(Company $company, CurrentWorkspace $workspace): View
    {
        $this->authorizeCompanyAccess($company, $workspace);

        return view('companies.edit', compact('company'));
    }

    public function update(Request $request, Company $company, CurrentWorkspace $workspace): RedirectResponse
    {
        $this->authorizeCompanyAccess($company, $workspace);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'nif' => ['nullable', 'string', 'max:100', 'unique:companies,nif,'.$company->id],
            'rccm' => ['nullable', 'string', 'max:100', 'unique:companies,rccm,'.$company->id],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'currency_code' => ['required', 'string', 'size:3'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        $company->update($data);
        $this->activityLogger->log('companies.update', 'Mise à jour entreprise', $company, $data);

        return redirect()->route('companies.index')->with('success', 'Entreprise mise à jour avec succès.');
    }

    private function authorizeCompanyAccess(Company $company, CurrentWorkspace $workspace): void
    {
        if ($this->canAccessAllCompanies($workspace)) {
            return;
        }

        abort_if($workspace->companyId() !== $company->id, 403);
    }

    private function ensurePlatformAdmin(CurrentWorkspace $workspace): void
    {
        abort_unless($this->canAccessAllCompanies($workspace), 403);
    }

    private function canAccessAllCompanies(CurrentWorkspace $workspace): bool
    {
        return (bool) $workspace->user()?->hasRole('platform_admin');
    }
}
