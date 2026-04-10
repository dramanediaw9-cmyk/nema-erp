<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Account;

class OhadaLocalizationService
{
    public function __construct(private readonly ChartOfAccountsService $chartOfAccountsService)
    {
    }

    public function profile(int $companyId): array
    {
        $this->chartOfAccountsService->ensureDefaultAccounts($companyId);

        $accounts = Account::query()
            ->where('company_id', $companyId)
            ->orderBy('code')
            ->get(['code', 'name', 'type']);

        $recommended = [
            ['code' => '101000', 'label' => 'Capital social', 'area' => 'Structure'],
            ['code' => '218300', 'label' => 'Materiel et outillage', 'area' => 'Immobilisations'],
            ['code' => '311000', 'label' => 'Stocks de marchandises', 'area' => 'Stocks'],
            ['code' => '401000', 'label' => 'Fournisseurs', 'area' => 'Tiers'],
            ['code' => '411000', 'label' => 'Clients', 'area' => 'Tiers'],
            ['code' => '421000', 'label' => 'Personnel remunerations dues', 'area' => 'Paie'],
            ['code' => '431000', 'label' => 'Organismes sociaux', 'area' => 'Paie'],
            ['code' => '443100', 'label' => 'TVA collectee', 'area' => 'Fiscalite'],
            ['code' => '445100', 'label' => 'TVA deductible', 'area' => 'Fiscalite'],
            ['code' => '447100', 'label' => 'Retenues et taxes a reverser', 'area' => 'Fiscalite'],
            ['code' => '521000', 'label' => 'Banques', 'area' => 'Tresorerie'],
            ['code' => '571000', 'label' => 'Caisse', 'area' => 'Tresorerie'],
            ['code' => '601000', 'label' => 'Achats de marchandises', 'area' => 'Charges'],
            ['code' => '661100', 'label' => 'Remunerations directes versees', 'area' => 'Charges'],
            ['code' => '662100', 'label' => 'Charges sociales patronales', 'area' => 'Charges'],
            ['code' => '707000', 'label' => 'Ventes de marchandises', 'area' => 'Produits'],
        ];

        $accountsByCode = $accounts->keyBy('code');
        $coverage = collect($recommended)->map(function (array $line) use ($accountsByCode): array {
            return $line + ['present' => $accountsByCode->has($line['code'])];
        });

        $classLabels = [
            '1' => 'Comptes de ressources stables',
            '2' => 'Actif immobilise',
            '3' => 'Stocks et en-cours',
            '4' => 'Tiers',
            '5' => 'Tresorerie',
            '6' => 'Charges',
            '7' => 'Produits',
            '8' => 'Comptes speciaux',
            '9' => 'Analytique et engagements',
        ];

        $classes = collect($classLabels)->map(function (string $label, string $prefix) use ($accounts): array {
            $classAccounts = $accounts->filter(fn ($account) => str_starts_with($account->code, $prefix));

            return [
                'class' => $prefix,
                'label' => $label,
                'count' => $classAccounts->count(),
                'sample_codes' => $classAccounts->take(4)->pluck('code')->values()->all(),
            ];
        })->values()->all();

        $presentCount = $coverage->where('present', true)->count();

        return [
            'standard' => 'SYSCOHADA revise',
            'currency' => 'XOF',
            'locale' => 'Mali / UEMOA',
            'coverage_rate' => round(($presentCount / max($coverage->count(), 1)) * 100, 1),
            'classes' => $classes,
            'recommended_accounts' => $coverage->values()->all(),
            'bridges' => [
                'tax' => [
                    'vat_collected' => '443100',
                    'vat_deductible' => '445100',
                    'withholding' => '447100',
                ],
                'payroll' => [
                    'payables' => '421000',
                    'social_security' => '431000',
                    'salary_expense' => '661100',
                    'employer_charges' => '662100',
                ],
                'manufacturing' => [
                    'stock' => '311000',
                    'purchases' => '601000',
                    'sales' => '707000',
                ],
            ],
            'runbooks' => [
                'chart' => route('accounting.accounts.index'),
                'tax' => route('accounting.tax-report.index'),
            ],
        ];
    }
}
