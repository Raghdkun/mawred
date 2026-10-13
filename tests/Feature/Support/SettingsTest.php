<?php

declare(strict_types=1);

use App\Domain\Setup\Support\Settings;

it('stores, reads and groups settings', function (): void {
    $settings = resolve(Settings::class);

    $settings->set('company.name', ['en' => 'Acme', 'ar' => 'أكمي']);
    $settings->set('company.default_locale', 'en');

    expect($settings->get('company.default_locale'))->toBe('en')
        ->and($settings->group('company'))->toHaveKeys(['name', 'default_locale'])
        ->and($settings->get('company.missing', 'fallback'))->toBe('fallback');

    $settings->forget('company.default_locale');

    expect(resolve(Settings::class)->has('company.default_locale'))->toBeFalse();
});

it('rejects paths without a group', function (): void {
    expect(fn () => resolve(Settings::class)->set('nogroup', 1))->toThrow(InvalidArgumentException::class);
});
