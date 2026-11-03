<?php

declare(strict_types=1);

/*
| ADR-0019: the host's nginx terminates TLS and forwards over loopback or the Docker bridge. The
| app trusts its forwarded scheme and client address from there, and from nowhere else.
*/
it('trusts the forwarded scheme and client address from the local proxy', function (): void {
    $response = $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.1'])
        ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Port' => '443', 'X-Forwarded-For' => '203.0.113.9'])
        ->get(route('dashboard'));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toStartWith('https://')
        ->and(request()->ip())->toBe('203.0.113.9');
});

it('ignores forwarded headers sent from anywhere else', function (): void {
    $response = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
        ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-For' => '203.0.113.9'])
        ->get(route('dashboard'));

    expect($response->headers->get('Location'))->toStartWith('http://')
        ->and(request()->ip())->toBe('198.51.100.7');
});
