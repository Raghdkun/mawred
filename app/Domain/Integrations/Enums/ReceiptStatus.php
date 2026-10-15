<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Enums;

/** What happened to a Cognito payload (submission log, handoff 41). */
enum ReceiptStatus: string
{
    case Received = 'received';
    case Processed = 'processed';
    case NeedsPerson = 'needs_person';
    case Failed = 'failed';
    case Duplicate = 'duplicate';
    case LateUpdate = 'late_update';
    case Sample = 'sample';
    case Ignored = 'ignored';
    case Discarded = 'discarded';

    /** An admin can run it again. */
    public function canReplay(): bool
    {
        return in_array($this, [self::Failed, self::LateUpdate, self::Sample, self::Ignored, self::Received], true);
    }
}
