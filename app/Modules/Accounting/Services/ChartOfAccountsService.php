<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Account;

class ChartOfAccountsService
{
    public function ensureDefaultAccounts(int $companyId): void
    {
        foreach ($this->defaults() as $account) {
            Account::query()->updateOrCreate(
                [
                    'company_id' => $companyId,
                    'code' => $account['code'],
                ],
                [
                    'name' => $account['name'],
                    'type' => $account['type'],
                    'is_system' => true,
                    'is_active' => true,
                ]
            );
        }
    }

    public function defaults(): array
    {
        return [
            ['code' => '411000', 'name' => 'Clients', 'type' => 'asset'],
            ['code' => '401000', 'name' => 'Fournisseurs', 'type' => 'liability'],
            ['code' => '443100', 'name' => 'TVA collectee', 'type' => 'liability'],
            ['code' => '445100', 'name' => 'TVA deductible', 'type' => 'asset'],
            ['code' => '447100', 'name' => 'Retenues et taxes a reverser', 'type' => 'liability'],
            ['code' => '471000', 'name' => 'Crediteurs divers', 'type' => 'liability'],
            ['code' => '521000', 'name' => 'Banques', 'type' => 'asset'],
            ['code' => '531000', 'name' => 'Mobile money', 'type' => 'asset'],
            ['code' => '571000', 'name' => 'Caisse', 'type' => 'asset'],
            ['code' => '601000', 'name' => 'Achats de marchandises', 'type' => 'expense'],
            ['code' => '606300', 'name' => 'Fournitures et petit materiel', 'type' => 'expense'],
            ['code' => '613000', 'name' => 'Loyers', 'type' => 'expense'],
            ['code' => '625100', 'name' => 'Carburant et transport', 'type' => 'expense'],
            ['code' => '707000', 'name' => 'Ventes de marchandises', 'type' => 'income'],
        ];
    }
}
