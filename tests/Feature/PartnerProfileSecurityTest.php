<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Partners\Models\Partner;
use App\Modules\Partners\Models\PartnerContact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerProfileSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_user_without_customer_management_permission_cannot_add_contact(): void
    {
        $cashier = User::query()->where('email', 'caissier@nema-erp.test')->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $cashier->company_id)->firstOrFail();

        $this->actingAs($cashier)
            ->withSession($this->workspaceSession($cashier))
            ->post(route('partners.contacts.store', $customer), [
                'name' => 'Contact interdit',
                'phone' => '+22370000000',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('partner_contacts', [
            'partner_id' => $customer->id,
            'name' => 'Contact interdit',
        ]);
    }

    public function test_manager_cannot_modify_partner_from_another_company(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $otherCompany = Company::query()->whereKeyNot($manager->company_id)->firstOrFail();
        $foreignPartner = Partner::query()->create([
            'tenant_id' => $otherCompany->tenant_id,
            'company_id' => $otherCompany->id,
            'type' => 'customer',
            'code' => 'CROSS-COMPANY-001',
            'name' => 'Client autre entreprise',
            'is_active' => true,
        ]);

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->post(route('partners.contacts.store', $foreignPartner), [
                'name' => 'Contact inter-entreprise',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('partner_contacts', [
            'partner_id' => $foreignPartner->id,
            'name' => 'Contact inter-entreprise',
        ]);
    }

    public function test_nested_contact_must_belong_to_partner_in_route(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $partners = Partner::query()
            ->customers()
            ->where('company_id', $manager->company_id)
            ->limit(2)
            ->get();

        $this->assertCount(2, $partners);

        $foreignContact = PartnerContact::query()->create([
            'tenant_id' => $manager->tenant_id,
            'company_id' => $manager->company_id,
            'partner_id' => $partners[1]->id,
            'name' => 'Contact du second client',
            'is_primary' => false,
        ]);

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->delete(route('partners.contacts.destroy', [$partners[0], $foreignContact]))
            ->assertForbidden();

        $this->assertDatabaseHas('partner_contacts', [
            'id' => $foreignContact->id,
            'partner_id' => $partners[1]->id,
        ]);
    }

    public function test_manager_can_add_valid_partner_address_and_bank_account(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $manager->company_id)->firstOrFail();

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->post(route('partners.addresses.store', $customer), [
                'label' => 'Livraison principale',
                'type' => 'shipping',
                'address_line' => 'Hamdallaye ACI 2000',
                'city' => 'Bamako',
                'country' => 'Mali',
                'is_primary' => 1,
            ])
            ->assertRedirect();

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->post(route('partners.bank-accounts.store', $customer), [
                'bank_name' => 'Banque de test',
                'account_name' => $customer->name,
                'account_number' => 'ML-TEST-0001',
                'is_primary' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('partner_addresses', [
            'partner_id' => $customer->id,
            'type' => 'shipping',
            'city' => 'Bamako',
            'is_primary' => true,
        ]);
        $this->assertDatabaseHas('partner_bank_accounts', [
            'partner_id' => $customer->id,
            'account_number' => 'ML-TEST-0001',
            'is_primary' => true,
        ]);
    }

    private function workspaceSession(User $user): array
    {
        return [
            'current_tenant_id' => $user->tenant_id,
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ];
    }
}
