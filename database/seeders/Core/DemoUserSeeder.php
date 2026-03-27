<?php

namespace Database\Seeders\Core;

use App\Models\User;
use App\Modules\Core\Access\Models\Role;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Models\Company;
use Illuminate\Database\Seeder;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->where('name', 'Nema Distribution')->firstOrFail();
        $branch = Branch::query()->where('company_id', $company->id)->where('code', 'BKO')->firstOrFail();

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@nema-erp.test'],
            [
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'name' => 'Admin Nema ERP',
                'phone' => '+223 70 00 00 01',
                'password' => 'password',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $admin->roles()->sync(Role::query()->where('slug', 'platform_admin')->pluck('id')->all());

        $director = User::query()->updateOrCreate(
            ['email' => 'dg@nema-erp.test'],
            [
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'name' => 'Direction Generale',
                'phone' => '+223 70 00 00 02',
                'password' => 'password',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $director->roles()->sync(Role::query()->where('company_id', $company->id)->where('slug', 'director')->pluck('id')->all());

        $manager = User::query()->updateOrCreate(
            ['email' => 'manager@nema-erp.test'],
            [
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'name' => 'Gestionnaire Societe',
                'phone' => '+223 70 00 00 03',
                'password' => 'password',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $manager->roles()->sync(Role::query()->where('company_id', $company->id)->where('slug', 'company_admin')->pluck('id')->all());

        $operations = User::query()->updateOrCreate(
            ['email' => 'ops@nema-erp.test'],
            [
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'name' => 'Agent Operations',
                'phone' => '+223 70 00 00 04',
                'password' => 'password',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $operations->roles()->sync(Role::query()->where('company_id', $company->id)->where('slug', 'operations_officer')->pluck('id')->all());

        $cashier = User::query()->updateOrCreate(
            ['email' => 'caissier@nema-erp.test'],
            [
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'name' => 'Caissier Comptoir',
                'phone' => '+223 70 00 00 05',
                'password' => 'password',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $cashier->roles()->sync(Role::query()->where('company_id', $company->id)->where('slug', 'cashier')->pluck('id')->all());
    }
}
