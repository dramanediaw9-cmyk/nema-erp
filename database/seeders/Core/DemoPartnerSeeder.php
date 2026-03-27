<?php

namespace Database\Seeders\Core;

use App\Modules\Core\Company\Models\Company;
use App\Modules\Partners\Models\Partner;
use Illuminate\Database\Seeder;

class DemoPartnerSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->where('name', 'Nema Distribution')->firstOrFail();

        $partners = [
            ['type' => 'customer', 'code' => 'CLI-COMPTOIR', 'name' => 'Client comptoir', 'phone' => null, 'email' => null, 'city' => 'Bamako', 'address' => 'Vente au comptoir', 'opening_balance' => 0],
            ['type' => 'customer', 'code' => 'C0001', 'name' => 'Sahel Market', 'phone' => '+223 70 11 22 33', 'email' => 'contact@sahel-market.ml', 'city' => 'Bamako', 'address' => 'Hamdallaye ACI 2000, Bamako', 'opening_balance' => 0],
            ['type' => 'customer', 'code' => 'C0002', 'name' => 'Boutique Djoliba', 'phone' => '+223 76 55 44 33', 'email' => 'djoliba@demo.ml', 'city' => 'Sikasso', 'address' => 'Centre-ville, Sikasso', 'opening_balance' => 150000],
            ['type' => 'supplier', 'code' => 'F0001', 'name' => 'Mali Fournitures Pro', 'phone' => '+223 74 10 10 10', 'email' => 'sales@mfpro.ml', 'city' => 'Bamako', 'address' => 'Zone industrielle, Bamako', 'opening_balance' => 0],
            ['type' => 'supplier', 'code' => 'F0002', 'name' => 'Agro Import Mali', 'phone' => '+223 79 90 90 90', 'email' => 'import@agro.ml', 'city' => 'Segou', 'address' => 'Quartier commercial, Segou', 'opening_balance' => 325000],
        ];

        foreach ($partners as $partner) {
            Partner::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => $partner['code']],
                $partner + ['company_id' => $company->id, 'is_active' => true]
            );
        }
    }
}
