<?php

declare(strict_types=1);

namespace App\Domain\Access\Enums;

/**
 * Field-level permission on a sensitive field (ADR-0005 / ADR-0007). Absence means no access.
 */
enum FieldAccess: string
{
    case Read = 'read';
    case Write = 'write';

    public function allowsRead(): bool
    {
        return true;
    }

    public function allowsWrite(): bool
    {
        return $this === self::Write;
    }
}
