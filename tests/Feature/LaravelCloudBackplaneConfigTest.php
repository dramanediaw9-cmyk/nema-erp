<?php

namespace Tests\Feature;

use Tests\TestCase;

class LaravelCloudBackplaneConfigTest extends TestCase
{
    public function test_session_defaults_to_redis_when_cloud_cache_store_is_redis(): void
    {
        $this->withTemporaryEnv([
            'CACHE_STORE' => 'redis',
            'SESSION_DRIVER' => null,
            'SESSION_STORE' => null,
        ], function (): void {
            $config = require base_path('config/session.php');

            $this->assertSame('redis', $config['driver']);
            $this->assertSame('redis', $config['store']);
        });
    }

    public function test_queue_defaults_to_redis_when_cloud_cache_store_is_redis(): void
    {
        $this->withTemporaryEnv([
            'CACHE_STORE' => 'redis',
            'QUEUE_CONNECTION' => null,
        ], function (): void {
            $config = require base_path('config/queue.php');

            $this->assertSame('redis', $config['default']);
        });
    }

    public function test_explicit_environment_values_still_override_cloud_defaults(): void
    {
        $this->withTemporaryEnv([
            'CACHE_STORE' => 'redis',
            'SESSION_DRIVER' => 'database',
            'SESSION_STORE' => null,
            'QUEUE_CONNECTION' => 'sync',
        ], function (): void {
            $session = require base_path('config/session.php');
            $queue = require base_path('config/queue.php');

            $this->assertSame('database', $session['driver']);
            $this->assertSame('sync', $queue['default']);
        });
    }

    private function withTemporaryEnv(array $values, callable $callback): void
    {
        $originals = [];

        foreach ($values as $key => $value) {
            $originals[$key] = array_key_exists($key, $_ENV) ? $_ENV[$key] : getenv($key);

            if ($value === null) {
                putenv($key);
                unset($_ENV[$key], $_SERVER[$key]);

                continue;
            }

            putenv($key.'='.$value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        try {
            $callback();
        } finally {
            foreach ($originals as $key => $value) {
                if ($value === false || $value === null) {
                    putenv($key);
                    unset($_ENV[$key], $_SERVER[$key]);

                    continue;
                }

                putenv($key.'='.$value);
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}
