<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Enums;

enum PostingStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Closed = 'closed';
}
