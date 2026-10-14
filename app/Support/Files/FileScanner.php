<?php

declare(strict_types=1);

namespace App\Support\Files;

/**
 * Malware scan for uploaded files (ADR-0008). `NullFileScanner` unless `CLAMAV_HOST` is set,
 * in which case clamd scans every upload before it is accepted.
 */
interface FileScanner
{
    /** True when the file is clean (or scanning is off). */
    public function isClean(string $path): bool;
}
