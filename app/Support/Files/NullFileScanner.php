<?php

declare(strict_types=1);

namespace App\Support\Files;

final class NullFileScanner implements FileScanner
{
    public function isClean(string $path): bool
    {
        return true;
    }
}
