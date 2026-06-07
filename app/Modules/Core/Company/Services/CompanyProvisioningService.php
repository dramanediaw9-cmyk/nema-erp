<?php

namespace App\Modules\Core\Company\Services;

use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Services\ChartOfAccountsService;
use App\Modules\Core\Access\Models\Permission;
use App\Modules\Core\Access\Models\Role;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Core\Company\Models\DocumentSequence;
use App\Modules\Core\Company\Models\Setting;
use App\Modules\Core\Company\Models\TaxRule;
use App\Modules\Core\Onboarding\Services\SectorStarterService;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Treasury\Models\CashAccount;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CompanyProvisioningService
{
    public function __construct(
        private readonly SectorProfileService $sectorProfiles,
        private readonly SectorStarterService $sectorStarter,
        private readonly ChartOfAccountsService $chartOfAccounts,
    ) {}

    public function provision(Company $company, array $options = []): array
    {
        return DB::transaction(function () use ($company, $options): array {
            $profile = (string) ($options['sector_profile'] ?? SectorProfileService::DEFAULT_PROFILE);

            if (! $this->sectorProfiles->isExplicitlyConfigured($company->id)) {
                $this->sectorProfiles->updateProfile($company->id, $company->tenant_id, $profile);
            }

            $branch = $this->ensureBranch($company, $options);
            $warehouse = $this->ensureWarehouse($company, $branch);
            $role = $this->ensureAdministratorRole($company);

            $this->ensureGeneralSettings($company, $branch);
            $this->ensureDocumentSequences($company);
            $this->ensureAccountingPeriods($company);
            $this->ensureTaxRule($company);
            $this->ensureCashAccount($company, $branch);
            $this->chartOfAccounts->ensureDefaultAccounts($company->id);
            $starter = $this->sectorStarter->apply($company);

            return compact('branch', 'warehouse', 'role', 'starter');
        });
    }

    private function ensureBranch(Company $company, array $options): Branch
    {
        $existing = Branch::query()
            ->where('company_id', $company->id)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        return Branch::query()->create([
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'name' => trim((string) ($options['branch_name'] ?? 'Siège')) ?: 'Siège',
            'code' => 'SIEGE',
            'city' => trim((string) ($options['city'] ?? 'Bamako')) ?: null,
            'address' => $company->address,
            'is_active' => true,
            'is_default' => true,
        ]);
    }

    private function ensureWarehouse(Company $company, Branch $branch): Warehouse
    {
        return Warehouse::query()->firstOrCreate(
            [
                'company_id' => $company->id,
                'code' => 'DEP-SIEGE',
            ],
            [
                'tenant_id' => $company->tenant_id,
                'branch_id' => $branch->id,
                'name' => 'Dépôt principal',
                'is_default' => true,
                'is_active' => true,
            ]
        );
    }

    private function ensureAdministratorRole(Company $company): Role
    {
        $role = Role::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'slug' => 'company_admin',
            ],
            [
                'tenant_id' => $company->tenant_id,
                'name' => 'Administrateur entreprise',
                'description' => 'Responsable principal de cet espace Nema.',
                'is_system' => true,
            ]
        );

        $role->permissions()->sync(Permission::query()->pluck('id'));

        return $role;
    }

    private function ensureGeneralSettings(Company $company, Branch $branch): void
    {
        Setting::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'key' => 'general',
            ],
            [
                'tenant_id' => $company->tenant_id,
                'value' => [
                    'country' => 'Mali',
                    'timezone' => 'Africa/Bamako',
                    'default_branch_id' => $branch->id,
                ],
            ]
        );
    }

    private function ensureCashAccount(Company $company, Branch $branch): void
    {
        CashAccount::query()->firstOrCreate(
            [
                'company_id' => $company->id,
                'name' => 'Caisse principale',
            ],
            [
                'tenant_id' => $company->tenant_id,
                'branch_id' => $branch->id,
                'type' => 'cash',
                'account_number' => null,
                'opening_balance' => 0,
                'is_active' => true,
            ]
        );
    }

    private function ensureTaxRule(Company $company): void
    {
        TaxRule::query()->firstOrCreate(
            [
                'company_id' => $company->id,
                'code' => 'TVA18',
            ],
            [
                'tenant_id' => $company->tenant_id,
                'name' => 'TVA 18%',
                'scope' => 'both',
                'tax_kind' => 'vat',
                'rate' => 18,
                'collect_account_code' => '443100',
                'deductible_account_code' => '445100',
                'is_default_sales' => true,
                'is_default_purchases' => true,
                'is_active' => true,
            ]
        );
    }

    private function ensureAccountingPeriods(Company $company): void
    {
        $year = (int) now()->format('Y');

        foreach (range(1, 12) as $month) {
            $start = Carbon::create($year, $month, 1)->startOfMonth();

            AccountingPeriod::query()->firstOrCreate(
                [
                    'company_id' => $company->id,
                    'start_date' => $start->toDateString(),
                    'end_date' => $start->copy()->endOfMonth()->toDateString(),
                ],
                [
                    'name' => sprintf('Période %02d/%d', $month, $year),
                    'status' => 'open',
                ]
            );
        }
    }

    private function ensureDocumentSequences(Company $company): void
    {
        foreach ($this->documentSequences() as $documentType => $definition) {
            DocumentSequence::query()->firstOrCreate(
                [
                    'company_id' => $company->id,
                    'document_type' => $documentType,
                ],
                [
                    'tenant_id' => $company->tenant_id,
                    'prefix' => $definition['prefix'],
                    'next_number' => 1,
                    'padding' => $definition['padding'],
                ]
            );
        }
    }

    private function documentSequences(): array
    {
        return [
            'sales_quote' => ['prefix' => 'DEV-{BRANCH}-{YEAR}-', 'padding' => 5],
            'sales_order' => ['prefix' => 'CMD-{BRANCH}-{YEAR}-', 'padding' => 5],
            'delivery_note' => ['prefix' => 'BL-{BRANCH}-{YEAR}-', 'padding' => 5],
            'sales_invoice' => ['prefix' => 'FAC-{BRANCH}-{YEAR}-', 'padding' => 5],
            'sales_credit_note' => ['prefix' => 'AVO-{BRANCH}-{YEAR}-', 'padding' => 5],
            'purchase_bill' => ['prefix' => 'ACH-{BRANCH}-{YEAR}-', 'padding' => 5],
            'purchase_credit_note' => ['prefix' => 'AVF-{BRANCH}-{YEAR}-', 'padding' => 5],
            'purchase_order' => ['prefix' => 'BCF-{BRANCH}-{YEAR}-', 'padding' => 5],
            'purchase_request' => ['prefix' => 'DA-{BRANCH}-{YEAR}-', 'padding' => 5],
            'goods_receipt' => ['prefix' => 'BRF-{BRANCH}-{YEAR}-', 'padding' => 5],
            'stock_transfer' => ['prefix' => 'TRF-{BRANCH}-{YEAR}-', 'padding' => 5],
            'stock_count' => ['prefix' => 'INV-{BRANCH}-{YEAR}-', 'padding' => 5],
            'pos_session' => ['prefix' => 'POS-{BRANCH}-{YEAR}-', 'padding' => 5],
            'pos_return' => ['prefix' => 'RET-{BRANCH}-{YEAR}-', 'padding' => 5],
            'payment' => ['prefix' => 'ENC-{BRANCH}-{YEAR}-', 'padding' => 5],
            'treasury_reconciliation' => ['prefix' => 'RAP-{BRANCH}-{YEAR}-', 'padding' => 5],
            'expense' => ['prefix' => 'DEP-{BRANCH}-{YEAR}-', 'padding' => 5],
            'journal_entry' => ['prefix' => 'JRN-{JOURNAL}-{YEAR}-', 'padding' => 5],
            'fixed_asset' => ['prefix' => 'IMO-{BRANCH}-{YEAR}-', 'padding' => 5],
        ];
    }
}
