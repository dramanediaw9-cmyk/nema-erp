<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

class StaticAuthenticatedRouteSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_every_static_authenticated_get_route_avoids_missing_server_error_and_empty_html(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $this->actingAs($manager)->withSession([
            'current_tenant_id' => $manager->tenant_id,
            'current_company_id' => $manager->company_id,
            'current_branch_id' => $manager->branch_id,
        ]);

        $routes = collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(fn (Route $route): bool => in_array('GET', $route->methods(), true))
            ->filter(fn (Route $route): bool => ! str_contains($route->uri(), '{'))
            ->filter(fn (Route $route): bool => in_array('auth', $route->gatherMiddleware(), true))
            ->sortBy(fn (Route $route): string => $route->uri())
            ->values();

        $this->assertGreaterThanOrEqual(120, $routes->count(), 'L’inventaire des routes GET authentifiées semble incomplet.');

        $failures = [];

        foreach ($routes as $route) {
            $response = $this->get('/'.$route->uri());
            $status = $response->getStatusCode();
            $label = ($route->getName() ?: 'sans nom').' ['.$route->uri().']';

            if ($status === 404 || $status >= 500) {
                $failures[] = $label.' retourne HTTP '.$status;

                continue;
            }

            $contentType = (string) $response->headers->get('content-type', '');
            $content = $response->getContent();

            if (
                $status === 200
                && str_contains(strtolower($contentType), 'text/html')
                && is_string($content)
                && trim($content) === ''
            ) {
                $failures[] = $label.' retourne une page HTML vide';
            }
        }

        $this->assertSame([], $failures, implode(PHP_EOL, $failures));
    }
}
