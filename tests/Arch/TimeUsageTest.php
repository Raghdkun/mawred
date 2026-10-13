<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

/*
| ADR-0004: "today" only exists relative to a timezone. Domain code resolves local dates through
| LocalClock / BranchClock, never through the server clock.
*/
it('never derives calendar dates from the server clock in domain code', function (): void {
    $offenders = [];

    foreach (Finder::create()->files()->in(dirname(__DIR__, 2).'/app/Domain')->name('*.php') as $file) {
        if (preg_match('/(now|today)\(\)\s*->\s*(toDateString|format\(\s*[\'"]Y-m-d[\'"])/', $file->getContents()) === 1) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBeEmpty();
});
