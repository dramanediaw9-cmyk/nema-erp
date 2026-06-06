<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountProfileTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_user_can_open_their_account_page(): void
    {
        $user = $this->manager();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('account.profile.edit'))
            ->assertOk()
            ->assertSee('Mon compte')
            ->assertSee($user->email)
            ->assertSee($user->company->name)
            ->assertSee($user->branch->name);
    }

    public function test_user_can_update_only_their_personal_information(): void
    {
        $user = $this->manager();
        $originalCompanyId = $user->company_id;
        $originalBranchId = $user->branch_id;

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->put(route('account.profile.update'), [
                'name' => 'Responsable Nema',
                'email' => 'responsable@nema-erp.test',
                'phone' => '+223 70 00 00 00',
                'company_id' => 999999,
                'branch_id' => 999999,
                'is_active' => false,
            ])
            ->assertRedirect(route('account.profile.edit'))
            ->assertSessionHas('success');

        $user->refresh();

        $this->assertSame('Responsable Nema', $user->name);
        $this->assertSame('responsable@nema-erp.test', $user->email);
        $this->assertSame('+223 70 00 00 00', $user->phone);
        $this->assertSame($originalCompanyId, $user->company_id);
        $this->assertSame($originalBranchId, $user->branch_id);
        $this->assertTrue($user->is_active);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action' => 'account.profile.update',
        ]);
    }

    public function test_user_cannot_use_an_existing_email_address(): void
    {
        $user = $this->manager();
        $otherUser = User::query()->whereKeyNot($user->id)->firstOrFail();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->put(route('account.profile.update'), [
                'name' => $user->name,
                'email' => $otherUser->email,
                'phone' => $user->phone,
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_current_password_is_required_to_change_password(): void
    {
        $user = $this->manager();
        $originalPassword = $user->password;

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->put(route('account.profile.password'), [
                'current_password' => 'incorrect',
                'password' => 'Nema-Securite-2026!',
                'password_confirmation' => 'Nema-Securite-2026!',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertSame($originalPassword, $user->fresh()->password);
    }

    public function test_user_can_change_their_password(): void
    {
        $user = $this->manager();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->put(route('account.profile.password'), [
                'current_password' => 'password',
                'password' => 'Nema-Securite-2026!',
                'password_confirmation' => 'Nema-Securite-2026!',
            ])
            ->assertRedirect(route('account.profile.edit'))
            ->assertSessionHas('success');

        $this->assertTrue(Hash::check('Nema-Securite-2026!', $user->fresh()->password));
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action' => 'account.password.update',
        ]);
    }

    private function manager(): User
    {
        return User::query()
            ->with(['company', 'branch'])
            ->where('email', 'manager@nema-erp.test')
            ->firstOrFail();
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
