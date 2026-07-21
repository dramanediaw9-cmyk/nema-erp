<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicApplicationContractTest extends TestCase
{
    public function test_login_page_is_branded_accessible_and_secured(): void
    {
        config()->set('app.url', 'https://erp.nematechnologies.com');

        $response = $this->withServerVariables(['HTTPS' => 'on'])->get('/login');

        $response
            ->assertOk()
            ->assertSee('Nema ERP')
            ->assertSee('Connexion - Nema ERP', false)
            ->assertSee('http-equiv="Content-Security-Policy"', false)
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
            ->assertHeader('Cross-Origin-Resource-Policy', 'same-origin');

        $this->assertStringContainsString("default-src 'self'", (string) $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("frame-ancestors 'none'", (string) $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('max-age=', (string) $response->headers->get('Strict-Transport-Security'));
    }

    public function test_health_and_error_pages_have_stable_public_contracts(): void
    {
        $this->get('/up')->assertOk();
        $this->get('/codex-contract-missing')->assertNotFound()->assertSee('Nema ERP');
    }

    public function test_guests_are_redirected_to_login_from_protected_areas(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/point-de-vente')->assertRedirect('/login');
    }

    public function test_empty_login_form_returns_understandable_validation_errors(): void
    {
        $this->from('/login')
            ->post('/login', [])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email', 'password']);
    }
}
