<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    // Isola cache e rate limiter (throttle) entre os testes, mesmo que o .env.testing aponte para Redis.
    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        app('cache')->purge('array');
        // O RateLimiter é singleton e já nasceu com o store original (Redis); troca só o cache,
        // preservando os limiters nomeados registrados no boot.
        $limiter = app(\Illuminate\Cache\RateLimiter::class);
        (new \ReflectionProperty($limiter, 'cache'))->setValue($limiter, app('cache')->store('array'));
    }
}
