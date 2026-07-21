<?php

namespace Tests\Feature;

use Tests\TestCase;

class EnvironmentIsolationTest extends TestCase
{
    public function test_suite_cannot_use_the_production_database(): void
    {
        $this->assertSame('testing', app()->environment());
        $connection = (string) config('database.default');

        $this->assertContains($connection, ['sqlite', 'mysql']);

        if ($connection === 'sqlite') {
            $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        } else {
            $database = (string) config('database.connections.mysql.database');

            $this->assertMatchesRegularExpression('/(?:^|[_-])(ci|test|testing)(?:$|[_-])/i', $database);
        }

        $this->assertContains(config('session.driver'), ['array', 'database']);
        $this->assertContains(config('cache.default'), ['array', 'database']);
    }
}
