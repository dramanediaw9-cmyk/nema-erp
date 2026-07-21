<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RouteActionContractTest extends TestCase
{
    public function test_every_controller_route_targets_an_existing_action(): void
    {
        $invalid = [];

        foreach (Route::getRoutes() as $route) {
            $controller = $route->getAction('controller');
            if (! is_string($controller) || $controller === '') {
                continue;
            }

            [$class, $method] = str_contains($controller, '@')
                ? explode('@', $controller, 2)
                : [$controller, '__invoke'];

            if (! class_exists($class)) {
                $invalid[] = $route->uri().': classe '.$class.' absente';
            } elseif (! method_exists($class, $method)) {
                $invalid[] = $route->uri().': action '.$class.'@'.$method.' absente';
            }
        }

        $this->assertSame([], $invalid, implode(PHP_EOL, $invalid));
    }
}
