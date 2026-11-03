<?php

declare(strict_types=1);

/*
| ADR-0019: one image serves every host, so browsers read Reverb's public address from the page
| instead of build-time VITE_ variables, and the CSP allows exactly that origin.
*/
it('tells the browser where Reverb is and allows it in the policy', function (): void {
    config(['mawred.realtime' => ['key' => 'app-key', 'host' => 'hr.example.com', 'port' => 443, 'scheme' => 'https']]);

    $response = $this->get(route('login'))->assertOk();

    expect((string) $response->getContent())
        ->toContain('<meta name="mawred-reverb" data-key="app-key" data-host="hr.example.com" data-port="443" data-scheme="https">')
        ->and((string) $response->headers->get('Content-Security-Policy'))->toContain('wss://hr.example.com');
});
