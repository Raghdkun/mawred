<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Enums;

enum MessageStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';
    case Received = 'received';
}
