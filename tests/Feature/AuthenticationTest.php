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
        $this->withServerVariables(['HTTPS' => 'on'])
            ->get('/login')
            ->assertOk()
            ->assertDontSee('Compte démo administrateur')
            ->assertHeader('content-security-policy')
            ->assertHeader('cross-origin-opener-policy', 'same-origin')
            ->assertHeader('cross-origin-resource-policy', 'same-origin')
            ->assertHeader('origin-agent-cluster', '?1')
            ->assertHeader('permissions-policy', 'accelerometer=(), camera=(), geolocation=(), gyroscope=(), microphone=(), payment=(), usb=()')
            ->assertHeader('referrer-policy', 'strict-origin-when-cross-origin')
            ->assertHeader('strict-transport-security', 'max-age=31536000; includeSubDomains')
            ->assertHeader('x-content-type-options', 'nosniff')
            ->assertHeader('x-frame-options', 'DENY')
            ->assertHeader('x-permitted-cross-domain-policies', 'none');
    }

    public function test_login_screen_can_show_demo_hint_when_explicitly_enabled(): void
    {
        config()->set('nema.expose_demo_credentials', true);

        $this->get('/login')
            ->assertOk()
            ->assertSee('Compte démo administrateur')
            ->assertSee('admin@nema-erp.test')
            ->assertSee('password');
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

    public function test_demo_accounts_are_blocked_when_demo_login_is_disabled(): void
    {
        config()->set('nema.allow_demo_login', false);

        $this->post('/login', [
            'email' => 'admin@nema-erp.test',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_demo_accounts_can_log_in_when_demo_login_is_explicitly_enabled(): void
    {
        config()->set('nema.allow_demo_login', true);

        $response = $this->post('/login', [
            'email' => 'admin@nema-erp.test',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
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
