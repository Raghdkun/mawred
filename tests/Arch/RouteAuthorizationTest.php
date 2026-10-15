<?php

declare(strict_types=1);

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class);

/*
| ADR-0005: every route that isn't deliberately public requires authentication (controllers and
| closures alike). Authorization (policies / Gate) happens inside each action; this guards
| against a route shipping without an auth layer at all.
*/
const PUBLIC_ROUTE_NAMES = [
    'login', 'login.store', 'logout', 'password.*', 'two-factor.login*', 'passkey.*', 'well-known.*',
    'setup.claim.*', 'invitations.*', 'locale.update', 'storage.*', 'verification.verify',
    'public-forms.*', // D53: hardened with ALTCHA, honeypot, fill time and rate limits
    'action-links.*', // ADR-0009: single-use hashed tokens; sensitive types require sign-in (D24)
    'hooks.*', // ADR-0015: inbound webhooks authenticate with URL tokens or signatures
];

/** Package-owned endpoints with their own gates (Horizon, Pulse, Scramble, Sanctum, health…). */
const VENDOR_URI_PREFIXES = ['horizon', 'pulse', 'docs/api', 'sanctum/', '_boost', '_scramble', 'livewire', 'up', 'broadcasting/', 'storage/', '_ignition', 'telescope'];

function unprotectedRoutes(): array
{
    return collect(RouteFacade::getRoutes()->getRoutes())
        ->reject(fn (Route $route): bool => $route->getName() !== null && Str::is(PUBLIC_ROUTE_NAMES, $route->getName()))
        ->reject(fn (Route $route) => Str::startsWith($route->uri(), VENDOR_URI_PREFIXES))
        ->reject(fn (Route $route) => collect($route->gatherMiddleware())->contains(fn ($m): bool => is_string($m) && (str_starts_with($m, 'auth') || $m === 'guest')))
        ->map(fn (Route $route): string => implode('|', $route->methods()).' '.$route->uri())
        ->values()
        ->all();
}

it('requires authentication on every non-public route', function (): void {
    expect(unprotectedRoutes())->toBe([]);
});

it('detects an unprotected route', function (): void {
    RouteFacade::get('_arch_probe_unprotected', fn (): string => 'oops');

    expect(unprotectedRoutes())->toContain('GET|HEAD _arch_probe_unprotected');
});
