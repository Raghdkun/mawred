<?php

declare(strict_types=1);

namespace App\Support\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends a Content-Security-Policy with every web page (Phase 9a): scripts only from the app with
 * a per-request nonce (Vite and the few inline scripts carry it), no plugins, no framing by other
 * sites, forms only to the app. Styles allow inline attributes (Radix, charts and motion set
 * them). A response that already has its own policy (a CV preview) keeps it.
 */
final class ContentSecurityPolicy
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();
        $response = $next($request);

        if (! config('csp.enabled') || $response->headers->has('Content-Security-Policy')) {
            return $response;
        }

        $response->headers->set('Content-Security-Policy', $this->policy($nonce));

        return $response;
    }

    private function policy(string $nonce): string
    {
        /** @var list<string> $connect */
        $connect = config('csp.connect', []);
        $script = ["'self'", "'nonce-{$nonce}'"];
        $style = ["'self'", "'unsafe-inline'"];
        $connectSrc = ["'self'", ...$connect];
        $reverb = $this->reverbOrigin();

        if ($reverb !== null) {
            $connectSrc[] = $reverb;
        }

        // The Vite dev server serves scripts, styles and hot updates from its own origin.
        if (Vite::isRunningHot()) {
            $hot = rtrim((string) file_get_contents(Vite::hotFile()));
            $script[] = $hot;
            $style[] = $hot;
            $connectSrc[] = $hot;
            $connectSrc[] = preg_replace('#^http#', 'ws', $hot) ?? $hot;
        }

        $directives = [
            'default-src' => ["'self'"],
            'script-src' => $script,
            'style-src' => $style,
            'img-src' => ["'self'", 'data:', 'blob:'],
            'font-src' => ["'self'", 'data:'],
            'connect-src' => array_values(array_unique($connectSrc)),
            'media-src' => ["'self'", 'blob:'],
            'worker-src' => ["'self'", 'blob:'],
            'frame-src' => ["'self'"],
            'manifest-src' => ["'self'"],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'self'"],
        ];

        $report = config('csp.report_uri');
        $policy = implode('; ', array_map(fn (string $name, array $sources): string => $name.' '.implode(' ', $sources), array_keys($directives), $directives));

        return is_string($report) && $report !== '' ? $policy.'; report-uri '.$report : $policy;
    }

    /** Reverb when it runs on another origin than the app (local and test setups). */
    private function reverbOrigin(): ?string
    {
        $host = config('broadcasting.connections.reverb.options.host');
        $port = config('broadcasting.connections.reverb.options.port');
        $scheme = config('broadcasting.connections.reverb.options.scheme');

        if (! is_string($host) || $host === '') {
            return null;
        }

        $ws = $scheme === 'https' ? 'wss' : 'ws';

        return $ws.'://'.$host.(is_numeric($port) && ! in_array((int) $port, [80, 443], true) ? ':'.$port : '');
    }
}
