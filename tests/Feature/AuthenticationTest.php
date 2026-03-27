<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_login_screen_is_available(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_manager_can_log_in_and_reach_dashboard(): void
    {
        $response = $this->post('/login', [
            'email' => 'manager@nema-erp.test',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();

        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $this->assertSame($user->company_id, session('current_company_id'));
        $this->assertSame($user->branch_id, session('current_branch_id'));
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $user->update(['is_active' => false]);

        $this->post('/login', [
            'email' => 'manager@nema-erp.test',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
