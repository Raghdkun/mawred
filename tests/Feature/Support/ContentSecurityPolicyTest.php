<?php

declare(strict_types=1);

use App\Http\Controllers\Public\CspReportController;
use Illuminate\Support\Facades\Cache;

/*
| Phase 9a: every web page carries a Content-Security-Policy whose script nonce matches the
| page's own inline and Vite scripts; browsers report violations to the app.
*/
it('sends a nonce-based policy that matches the page\'s scripts', function (): void {
    $response = $this->get(route('login'))->assertOk();
    $policy = (string) $response->headers->get('Content-Security-Policy');

    expect($policy)->toContain("default-src 'self'")
        ->toContain("object-src 'none'")
        ->toContain("frame-ancestors 'self'")
        ->toContain("form-action 'self'")
        ->toContain('https://tiles.openfreemap.org')
        ->toContain('report-uri /hooks/csp-report')
        ->not->toContain("'unsafe-eval'");

    preg_match("/'nonce-([^']+)'/", $policy, $match);
    expect($match[1] ?? null)->not->toBeNull()
        ->and((string) $response->getContent())->toContain('nonce="'.$match[1].'"');

    // A new page, a new nonce.
    expect((string) $this->get(route('login'))->headers->get('Content-Security-Policy'))->not->toContain($match[1]);
});

it('records violation reports without query strings', function (): void {
    Cache::forget(CspReportController::CACHE_KEY);

    $this->call('POST', route('hooks.csp'), [], [], [], ['CONTENT_TYPE' => 'application/csp-report'], (string) json_encode(['csp-report' => [
        'document-uri' => 'https://hr.example.com/people?token=secret',
        'effective-directive' => 'script-src-elem',
        'blocked-uri' => 'https://evil.example.com/x.js?y=1',
    ]]))->assertNoContent();

    expect(Cache::get(CspReportController::CACHE_KEY))->toBe([[
        'document' => 'https://hr.example.com/people',
        'directive' => 'script-src-elem',
        'blocked' => 'https://evil.example.com/x.js',
        'source' => '',
    ]]);
});
