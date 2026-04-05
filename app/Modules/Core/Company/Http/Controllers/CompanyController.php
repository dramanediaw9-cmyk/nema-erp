<?php

namespace App\Modules\Core\Company\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Company\Models\Company;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger)
    {
    }

    public function index(): View
    {
        return view('companies.index', [
            'companies' => Company::query()->latest()->paginate(12),
        ]);
    }

    public function create(): View
    {
        return view('companies.create', [
            'company' => new Company(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
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

    public function edit(Company $company): View
    {
        return view('companies.edit', compact('company'));
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
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
}
