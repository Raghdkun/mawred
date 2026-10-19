<?php

declare(strict_types=1);

namespace App\Domain\Reports\Enums;

enum RunStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Ready = 'ready';
    case Failed = 'failed';
}
