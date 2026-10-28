<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\Health\ReadinessProbe;
use Illuminate\Http\JsonResponse;

/**
 * Liveness and readiness for Caddy, the deploy script and the external uptime ping (ADR-0019).
 * Answers name each check and whether it passed, nothing more.
 */
final class HealthController extends Controller
{
    public function live(): JsonResponse
    {
        return response()->json(['status' => 'ok'])->header('Cache-Control', 'no-store');
    }

    public function ready(ReadinessProbe $probe): JsonResponse
    {
        $checks = $probe->run();
        $ready = ReadinessProbe::isReady($checks);
        $status = match (true) {
            ! $ready => 'down',
            in_array(false, $checks, true) => 'degraded',
            default => 'ok',
        };

        return response()->json([
            'status' => $status,
            'checks' => array_map(fn (bool $passed): string => $passed ? 'ok' : 'failed', $checks),
        ], $ready ? 200 : 503)->header('Cache-Control', 'no-store');
    }
}
