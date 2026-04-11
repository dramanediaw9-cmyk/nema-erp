<?php

namespace Database\Seeders\Core;

use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Core\Company\Models\DocumentSequence;
use App\Modules\Core\Company\Models\PaymentTerm;
use App\Modules\Core\Company\Models\PriceList;
use App\Modules\Core\Company\Models\Setting;
use App\Modules\Core\Company\Models\TaxRule;
use App\Modules\Core\Company\Models\Tenant;
use App\Modules\Core\Integrations\Models\ApiToken;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Database\Seeder;

class DemoCompanySeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->updateOrCreate(
            ['code' => 'TENANT-NEMA'],
            [
                'name' => 'Nema Groupe',
                'slug' => 'nema-groupe',
                'is_active' => true,
            ]
        );

        $company = Company::query()->updateOrCreate(
            ['name' => 'Nema Distribution'],
            [
                'tenant_id' => $tenant->id,
                'legal_name' => 'Nema Distribution SARL',
                'nif' => 'ML2026NEMA001',
                'rccm' => 'ML-BKO-RCCM-2026-B-001',
                'phone' => '+223 76 00 00 00',
                'email' => 'contact@nema-erp.test',
                'address' => 'ACI 2000, Bamako, Mali',
                'currency_code' => 'XOF',
                'is_active' => true,
            ]
        );

        $retailCompany = Company::query()->updateOrCreate(
            ['name' => 'Nema Retail Sud'],
            [
                'tenant_id' => $tenant->id,
                'legal_name' => 'Nema Retail Sud SARL',
                'nif' => 'ML2026NEMA002',
                'rccm' => 'ML-SEG-RCCM-2026-B-002',
                'phone' => '+223 76 00 00 06',
                'email' => 'retail@nema-erp.test',
                'address' => 'Quartier commercial, Segou, Mali',
                'currency_code' => 'XOF',
                'is_active' => true,
            ]
        );

        $bamako = Branch::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'BKO'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Agence Bamako',
                'city' => 'Bamako',
                'address' => 'ACI 2000, Bamako',
                'is_active' => true,
                'is_default' => true,
            ]
        );

        $sikasso = Branch::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'SIK'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Agence Sikasso',
                'city' => 'Sikasso',
                'address' => 'Centre commercial, Sikasso',
                'is_active' => true,
                'is_default' => false,
            ]
        );

        $segou = Branch::query()->updateOrCreate(
            ['company_id' => $retailCompany->id, 'code' => 'SEG'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Agence Segou',
                'city' => 'Segou',
                'address' => 'Zone commerciale, Segou',
                'is_active' => true,
                'is_default' => true,
            ]
        );

        Warehouse::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'DEP-BKO-PRINC'],
            [
                'tenant_id' => $tenant->id,
                'branch_id' => $bamako->id,
                'name' => 'Depot principal Bamako',
                'is_default' => true,
                'is_active' => true,
            ]
        );

        Warehouse::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'DEP-BKO-SEC'],
            [
                'tenant_id' => $tenant->id,
                'branch_id' => $bamako->id,
                'name' => 'Depot secondaire Bamako',
                'is_default' => false,
                'is_active' => true,
            ]
        );

        Warehouse::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'DEP-SIK-PRINC'],
            [
                'tenant_id' => $tenant->id,
                'branch_id' => $sikasso->id,
                'name' => 'Depot principal Sikasso',
                'is_default' => true,
                'is_active' => true,
            ]
        );

        Warehouse::query()->updateOrCreate(
            ['company_id' => $retailCompany->id, 'code' => 'DEP-SEG-PRINC'],
            [
                'tenant_id' => $tenant->id,
                'branch_id' => $segou->id,
                'name' => 'Depot principal Segou',
                'is_default' => true,
                'is_active' => true,
            ]
        );

        foreach ([
            ['document_type' => 'sales_quote', 'prefix' => 'DEV-{BRANCH}-{YEAR}-', 'next_number' => 1, 'padding' => 5],
            ['document_type' => 'sales_order', 'prefix' => 'CMD-{BRANCH}-{YEAR}-', 'next_number' => 1, 'padding' => 5],
            ['document_type' => 'delivery_note', 'prefix' => 'BL-{BRANCH}-{YEAR}-', 'next_number' => 1, 'padding' => 5],
            ['document_type' => 'sales_invoice', 'prefix' => 'FAC-{BRANCH}-{YEAR}-', 'next_number' => 1, 'padding' => 5],
            ['document_type' => 'sales_credit_note', 'prefix' => 'AVO-{BRANCH}-{YEAR}-', 'next_number' => 1, 'padding' => 5],
            ['document_type' => 'purchase_bill', 'prefix' => 'ACH-{BRANCH}-{YEAR}-', 'next_number' => 1, 'padding' => 5],
            ['document_type' => 'purchase_order', 'prefix' => 'BCF-{BRANCH}-{YEAR}-', 'next_number' => 1, 'padding' => 5],
            ['document_type' => 'purchase_request', 'prefix' => 'DA-{BRANCH}-{YEAR}-', 'next_number' => 1, 'padding' => 5],
            ['document_type' => 'goods_receipt', 'prefix' => 'BRF-{BRANCH}-{YEAR}-', 'next_number' => 1, 'padding' => 5],
            ['document_type' => 'stock_transfer', 'prefix' => 'TRF-{BRANCH}-{YEAR}-', 'next_number' => 1, 'padding' => 5],
            ['document_type' => 'stock_count', 'prefix' => 'INV-{BRANCH}-{YEAR}-', 'next_number' => 1, 'padding' => 5],
            ['document_type' => 'pos_session', 'prefix' => 'POS-{BRANCH}-{YEAR}-', 'next_number' => 1, 'padding' => 5],
            ['document_type' => 'pos_return', 'prefix' => 'RET-{BRANCH}-{YEAR}-', 'next_number' => 1, 'padding' => 5],
            ['document_type' => 'payment', 'prefix' => 'ENC-{BRANCH}-{YEAR}-', 'next_number' => 1, 'padding' => 5],
            ['document_type' => 'treasury_reconciliation', 'prefix' => 'RAP-{BRANCH}-{YEAR}-', 'next_number' => 1, 'padding' => 5],
            ['document_type' => 'expense', 'prefix' => 'DEP-{BRANCH}-{YEAR}-', 'next_number' => 1, 'padding' => 5],
            ['document_type' => 'journal_entry', 'prefix' => 'JRN-{JOURNAL}-{YEAR}-', 'next_number' => 1, 'padding' => 5],
            ['document_type' => 'fixed_asset', 'prefix' => 'IMO-{BRANCH}-{YEAR}-', 'next_number' => 1, 'padding' => 5],
        ] as $sequence) {
            $documentSequence = DocumentSequence::query()->firstOrNew([
                'company_id' => $company->id,
                'document_type' => $sequence['document_type'],
            ]);

            $documentSequence->tenant_id = $tenant->id;
            $documentSequence->prefix = $sequence['prefix'];
            $documentSequence->padding = $sequence['padding'];

            if (! $documentSequence->exists) {
                $documentSequence->next_number = $sequence['next_number'];
            }

            $documentSequence->save();
        }

        Setting::query()->updateOrCreate(
            ['company_id' => $company->id, 'key' => 'general'],
            [
                'tenant_id' => $tenant->id,
                'value' => [
                    'country' => 'Mali',
                    'timezone' => 'Africa/Bamako',
                    'default_branch_id' => $bamako->id,
                ],
            ]
        );

        Setting::query()->updateOrCreate(
            ['company_id' => $retailCompany->id, 'key' => 'general'],
            [
                'tenant_id' => $tenant->id,
                'value' => [
                    'country' => 'Mali',
                    'timezone' => 'Africa/Bamako',
                    'default_branch_id' => $segou->id,
                ],
            ]
        );

        PaymentTerm::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'PT-CASH'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Comptant',
                'days' => 0,
                'description' => 'Reglement immediat',
                'is_default' => true,
                'is_active' => true,
            ]
        );

        PaymentTerm::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'PT-30'],
            [
                'tenant_id' => $tenant->id,
                'name' => '30 jours fin de mois',
                'days' => 30,
                'description' => 'Paiement a 30 jours',
                'is_default' => false,
                'is_active' => true,
            ]
        );

        PriceList::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'DETAIL'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Tarif detail',
                'currency_code' => 'XOF',
                'description' => 'Prix public comptoir',
                'is_default' => true,
                'is_active' => true,
            ]
        );

        PriceList::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'GROS'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Tarif grossiste',
                'currency_code' => 'XOF',
                'description' => 'Tarif reserve aux clients grossistes',
                'is_default' => false,
                'is_active' => true,
            ]
        );

        TaxRule::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'TVA18'],
            [
                'tenant_id' => $tenant->id,
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

        ApiToken::query()->updateOrCreate(
            ['company_id' => $company->id, 'name' => 'Connecteur demo'],
            [
                'tenant_id' => $tenant->id,
                'token_hash' => hash('sha256', 'nema_demo_api_token'),
                'created_by' => null,
            ]
        );
    }
}
