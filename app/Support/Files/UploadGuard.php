<?php

declare(strict_types=1);

namespace App\Support\Files;

use App\Support\Errors\DomainRuleViolation;
use Illuminate\Http\UploadedFile;

/**
 * Refuses an upload the virus scanner flags (ADR-0008), before anything keeps or reads it. Every
 * path that stores a file someone sent goes through here or through `StoreFormUpload`.
 */
final readonly class UploadGuard
{
    public function __construct(private FileScanner $scanner) {}

    public function assertClean(UploadedFile $file, string $field = 'file'): void
    {
        if (! $this->scanner->isClean($file->getRealPath() ?: '')) {
            $message = __('This file was flagged by the virus scanner and was not saved.');

            throw new DomainRuleViolation('files.infected', $message, [$field => [$message]]);
        }
    }
}
