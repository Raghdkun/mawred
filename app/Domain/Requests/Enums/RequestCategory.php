<?php

declare(strict_types=1);

namespace App\Domain\Requests\Enums;

/** Request center category pills (handoff 10). */
enum RequestCategory: string
{
    case Leave = 'leave';
    case Time = 'time';
    case Finance = 'finance';
    case Documents = 'documents';
    case People = 'people';
    case Assets = 'assets';
    case Training = 'training';
}
