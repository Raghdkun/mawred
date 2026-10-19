<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

/*
| Every queue a job names must have a Horizon supervisor, or its jobs sit in Redis forever in
| production (the local worker hides it). Phase 8 found workflows, notifications, imports and
| integrations unserved.
*/
it('serves every queue a job is put on', function (): void {
    $horizon = require dirname(__DIR__, 2).'/config/horizon.php';
    $served = array_unique(array_merge(...array_map(fn (array $supervisor): array => $supervisor['queue'], array_values($horizon['defaults']))));
    $named = [];

    foreach (Finder::create()->files()->in(dirname(__DIR__, 2).'/app')->name('*.php') as $file) {
        preg_match_all("/(?:#\\[Queue\\('|onQueue\\(')([a-z-]+)'/", $file->getContents(), $matches);
        $named = [...$named, ...$matches[1]];
    }

    expect(array_values(array_diff(array_unique($named), $served)))->toBe([]);
});
