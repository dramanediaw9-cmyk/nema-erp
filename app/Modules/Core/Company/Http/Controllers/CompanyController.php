<?php

namespace App\Modules\Core\Company\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Core\Company\Services\CompanyProvisioningService;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly CompanyProvisioningService $provisioning,
    ) {}

    public function index(CurrentWorkspace $workspace): View
    {
        $query = Company::query()->latest();

        if (! $this->canAccessAllCompanies($workspace)) {
            abort_if(! $workspace->companyId(), 403);
            $query->whereKey($workspace->companyId());
        }

        $companies = $query->paginate(12);

        return view('companies.index', [
            'companies' => $companies,
            'readinessByCompany' => $this->readinessFor($companies->getCollection()),
        ]);
    }

    public function create(CurrentWorkspace $workspace): View
    {
        $this->ensurePlatformAdmin($workspace);

        return view('companies.create', [
            'company' => new Company,
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

        [$company, $provisioned] = DB::transaction(function () use ($data): array {
            $company = Company::query()->create($data);
            $provisioned = $this->provisioning->provision($company);

            return [$company, $provisioned];
        });

        $this->activityLogger->log('companies.create', 'Création entreprise', $company, $data);

        $request->session()->put([
            'current_tenant_id' => (int) $company->getAttribute('tenant_id'),
            'current_company_id' => $company->id,
            'current_branch_id' => $provisioned['branch']->id,
        ]);

        return redirect()->route('onboarding.index')
            ->with('success', 'Entreprise créée et modules initialisés. Vous pouvez maintenant préparer vos données métier.');
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

    public function provision(Company $company, CurrentWorkspace $workspace): RedirectResponse
    {
        $this->authorizeCompanyAccess($company, $workspace);

        $this->provisioning->provision($company);
        $this->activityLogger->log('companies.provision', 'Réparation socle entreprise', $company, [
            'company_id' => $company->id,
        ]);

        return redirect()->route('companies.index')->with('success', 'Socle client vérifié et réparé automatiquement.');
    }

    private function readinessFor($companies): array
    {
        $companyIds = $companies->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($companyIds === []) {
            return [];
        }

        $activeBranches = $this->countByCompany('branches', $companyIds, ['is_active' => true]);
        $activeWarehouses = $this->countByCompany('warehouses', $companyIds, ['is_active' => true]);
        $activeCashAccounts = $this->countByCompany('cash_accounts', $companyIds, ['is_active' => true]);
        $activeUsers = $this->countByCompany('users', $companyIds, ['is_active' => true]);
        $roles = $this->countByCompany('roles', $companyIds);
        $settings = $this->countByCompany('settings', $companyIds);
        $taxRules = $this->countByCompany('tax_rules', $companyIds, ['is_active' => true]);
        $sequences = $this->countByCompany('document_sequences', $companyIds);

        $readiness = [];

        foreach ($companies as $company) {
            $identityReady = filled($company->name)
                && filled($company->currency_code)
                && (filled($company->phone) || filled($company->email))
                && (filled($company->nif) || filled($company->rccm));

            $items = [
                [
                    'label' => 'Identité société',
                    'ready' => $identityReady,
                    'detail' => $identityReady ? 'Nom, contact, devise et fiscalité OK' : 'Compléter contact, NIF ou RCCM',
                    'url' => route('companies.edit', $company),
                ],
                [
                    'label' => 'Agence',
                    'ready' => ($activeBranches[$company->id] ?? 0) > 0,
                    'detail' => ($activeBranches[$company->id] ?? 0).' active(s)',
                    'url' => route('branches.index'),
                ],
                [
                    'label' => 'Entrepôt',
                    'ready' => ($activeWarehouses[$company->id] ?? 0) > 0,
                    'detail' => ($activeWarehouses[$company->id] ?? 0).' actif(s)',
                    'url' => route('warehouses.index'),
                ],
                [
                    'label' => 'Caisse',
                    'ready' => ($activeCashAccounts[$company->id] ?? 0) > 0,
                    'detail' => ($activeCashAccounts[$company->id] ?? 0).' compte(s)',
                    'url' => route('cash-accounts.index'),
                ],
                [
                    'label' => 'Utilisateurs',
                    'ready' => ($activeUsers[$company->id] ?? 0) > 0,
                    'detail' => ($activeUsers[$company->id] ?? 0).' actif(s)',
                    'url' => route('users.index'),
                ],
                [
                    'label' => 'Rôles',
                    'ready' => ($roles[$company->id] ?? 0) > 0,
                    'detail' => ($roles[$company->id] ?? 0).' rôle(s)',
                    'url' => route('roles.index'),
                ],
                [
                    'label' => 'Taxes',
                    'ready' => ($taxRules[$company->id] ?? 0) > 0,
                    'detail' => ($taxRules[$company->id] ?? 0).' règle(s)',
                    'url' => route('settings.index'),
                ],
                [
                    'label' => 'Documents',
                    'ready' => ($sequences[$company->id] ?? 0) >= 5,
                    'detail' => ($sequences[$company->id] ?? 0).' numérotation(s)',
                    'url' => route('settings.index'),
                ],
                [
                    'label' => 'Paramètres',
                    'ready' => ($settings[$company->id] ?? 0) > 0,
                    'detail' => ($settings[$company->id] ?? 0).' bloc(s)',
                    'url' => route('settings.index'),
                ],
                [
                    'label' => 'Logo imprimés',
                    'ready' => filled($company->logo_path),
                    'detail' => filled($company->logo_path) ? 'Logo prêt' : 'Logo à ajouter',
                    'url' => route('settings.index'),
                ],
            ];

            $readyCount = collect($items)->where('ready', true)->count();

            $readiness[$company->id] = [
                'score' => (int) round(($readyCount / count($items)) * 100),
                'ready_count' => $readyCount,
                'total_count' => count($items),
                'items' => $items,
                'imports_url' => route('imports.index'),
                'audit_url' => route('activity-logs.index'),
            ];
        }

        return $readiness;
    }

    private function countByCompany(string $table, array $companyIds, array $where = []): array
    {
        $query = DB::table($table)
            ->whereIn('company_id', $companyIds)
            ->select('company_id', DB::raw('count(*) as total'))
            ->groupBy('company_id');

        foreach ($where as $column => $value) {
            $query->where($column, $value);
        }

        return $query->pluck('total', 'company_id')->map(fn ($value) => (int) $value)->all();
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
