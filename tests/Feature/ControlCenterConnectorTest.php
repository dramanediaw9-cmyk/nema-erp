<?php

namespace Tests\Feature;

use App\Modules\Core\Platform\Services\ControlCenterConnectorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ControlCenterConnectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_connector_does_not_send_without_a_server_secret(): void
    {
        config()->set('services.nema_control_center.connector_token', null);
        Http::fake();

        $result = app(ControlCenterConnectorService::class)->sync();

        $this->assertSame('skipped', $result['status']);
        Http::assertNothingSent();
    }

    public function test_connector_uses_bearer_auth_and_excludes_sensitive_session_fields(): void
    {
        config()->set('services.nema_control_center.url', 'https://control.example.test/platform-ingest');
        config()->set('services.nema_control_center.connector_token', 'nema_live_'.str_repeat('a', 48));
        Http::fake(['https://control.example.test/*' => Http::response(['accepted' => true], 202)]);

        $result = app(ControlCenterConnectorService::class)->sync();

        $this->assertSame('accepted', $result['status']);
        Http::assertSent(function ($request): bool {
            $payload = $request->data();

            return $request->hasHeader('Authorization', 'Bearer nema_live_'.str_repeat('a', 48))
                && $payload['schema_version'] === 1
                && array_key_exists('organizations', $payload)
                && array_key_exists('users', $payload)
                && array_key_exists('sessions', $payload)
                && ! str_contains(json_encode($payload), 'ip_address')
                && ! str_contains(json_encode($payload), 'password')
                && ! str_contains(json_encode($payload), 'remember_token');
        });
    }
}
