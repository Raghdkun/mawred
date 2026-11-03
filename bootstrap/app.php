<?php

declare(strict_types=1);

use App\Http\Middleware\ApplyImpersonation;
use App\Http\Middleware\EnsureSetupComplete;
use App\Http\Middleware\EnsureTwoFactorEnrolled;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetAuditContext;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TrackUserSession;
use App\Support\Errors\DomainRuleViolation;
use App\Support\Http\ApiErrorRenderer;
use App\Support\Http\InertiaErrorRenderer;
use App\Support\Http\Middleware\AssignRequestId;
use App\Support\Http\Middleware\ContentSecurityPolicy;
use App\Support\Http\Middleware\EnforceIdempotency;
use App\Support\Http\WebDomainErrorRenderer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function (): void {
            Route::group([], base_path('routes/hooks.php'));
            Route::group([], base_path('routes/health.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignRequestId::class);

        // The host's nginx terminates TLS and reaches the app over loopback or the Docker bridge
        // (ADR-0019); the app ports are never published publicly, so private ranges are the proxy.
        $middleware->trustProxies(at: ['127.0.0.1', '::1', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16']);

        $middleware->statefulApi();
        $middleware->api(append: [SetLocale::class, SetAuditContext::class, EnsureTwoFactorEnrolled::class]);

        $middleware->alias([
            'idempotent' => EnforceIdempotency::class,
        ]);

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            ContentSecurityPolicy::class,
            TrackUserSession::class,
            ApplyImpersonation::class,
            SetLocale::class,
            SetAuditContext::class,
            EnsureTwoFactorEnrolled::class,
            EnsureSetupComplete::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(new ApiErrorRenderer);
        $exceptions->render(new WebDomainErrorRenderer);
        $exceptions->dontReport(DomainRuleViolation::class);
        $exceptions->respond(new InertiaErrorRenderer);
    })->create();
