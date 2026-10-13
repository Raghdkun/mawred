<?php

declare(strict_types=1);

/*
| Every user-facing server string (toasts, rule violations, notifications, validation hints) is
| written in English in code and must have an Arabic entry in lang/ar.json (D3: EN + AR).
*/
it('has an Arabic translation for every server string', function (): void {
    $root = dirname(__DIR__, 3);
    $arabic = json_decode((string) file_get_contents("{$root}/lang/ar.json"), true, flags: JSON_THROW_ON_ERROR);
    $missing = [];
    $found = 0;

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/app", FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        preg_match_all("/(?:\b__|\btrans|self::message|\\\$this->line)\(\s*'((?:[^'\\\\]|\\\\.)*)'/", (string) file_get_contents($file->getPathname()), $matches);

        foreach ($matches[1] as $key) {
            $key = str_replace("\\'", "'", $key);
            $found++;

            if (! array_key_exists($key, $arabic)) {
                $missing[] = $key;
            }
        }
    }

    expect($found)->toBeGreaterThan(50)
        ->and(array_values(array_unique($missing)))->toBe([]);
});
