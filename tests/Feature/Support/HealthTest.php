<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;

/*
| ADR-0019: liveness and readiness for Caddy, the deploy script and the uptime ping. Readiness
| needs the database, Redis and the private disk; Gotenberg only degrades it.
*/
beforeEach(function (): void {
    Redis::shouldReceive('connection->ping')->andReturn(true)->byDefault();
    Http::fake([rtrim((string) config('services.gotenberg.url'), '/').'/health' => Http::response(['status' => 'up'])]);
});

it('answers liveness without a session', function (): void {
    $this->getJson(route('health.live'))
        ->assertOk()
        ->assertExactJson(['status' => 'ok'])
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertCookieMissing((string) config('session.cookie'));
});

it('is ready when its dependencies answer', function (): void {
    $this->getJson(route('health.ready'))
        ->assertOk()
        ->assertExactJson(['status' => 'ok', 'checks' => ['database' => 'ok', 'redis' => 'ok', 'storage' => 'ok', 'gotenberg' => 'ok']]);
});

it('stays ready but degraded without Gotenberg', function (): void {
    Http::fake(['*' => fn () => throw new ConnectionException('refused')]);

    $this->getJson(route('health.ready'))
        ->assertOk()
        ->assertJsonPath('status', 'degraded')
        ->assertJsonPath('checks.gotenberg', 'failed');
});

it('takes no traffic when Redis is down', function (): void {
    Redis::shouldReceive('connection->ping')->andThrow(new RedisException('Connection refused'));

    $this->getJson(route('health.ready'))
        ->assertServiceUnavailable()
        ->assertJsonPath('status', 'down')
        ->assertJsonPath('checks.redis', 'failed')
        ->assertJsonPath('checks.database', 'ok');
});
