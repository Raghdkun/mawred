<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Browsers report Content-Security-Policy violations here (Phase 9a). Each is logged (without
 * query strings, which may carry tokens) and the latest are kept for a day so checks can read
 * them. Rate limited; the body is never trusted beyond a few short strings.
 */
final class CspReportController extends Controller
{
    public const string CACHE_KEY = 'csp:violations';

    public function __invoke(Request $request): Response
    {
        $body = json_decode((string) $request->getContent(), true);
        $report = is_array($body) && is_array($body['csp-report'] ?? null) ? $body['csp-report'] : null;

        if ($report !== null) {
            $strip = fn (mixed $v): string => is_string($v) ? mb_substr(strtok($v, '?') ?: '', 0, 300) : '';
            $violation = [
                'document' => $strip($report['document-uri'] ?? null),
                'directive' => $strip($report['effective-directive'] ?? $report['violated-directive'] ?? null),
                'blocked' => $strip($report['blocked-uri'] ?? null),
                'source' => $strip($report['source-file'] ?? null),
            ];

            Log::warning('CSP violation', $violation);
            /** @var list<array<string, string>> $recent */
            $recent = Cache::get(self::CACHE_KEY, []);
            Cache::put(self::CACHE_KEY, array_slice([...$recent, $violation], -50), now()->addDay());
        }

        return response()->noContent();
    }
}
