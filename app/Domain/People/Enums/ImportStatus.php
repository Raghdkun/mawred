<?php

declare(strict_types=1);

namespace App\Domain\People\Enums;

/**
 * uploaded → (mapping saved) → validating → validated → committing → completed. Any stage can
 * end in failed; uploaded and validated imports can be cancelled.
 */
enum ImportStatus: string
{
    case Uploaded = 'uploaded';
    case Validating = 'validating';
    case Validated = 'validated';
    case Committing = 'committing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function isBusy(): bool
    {
        return in_array($this, [self::Validating, self::Committing], true);
    }
}
