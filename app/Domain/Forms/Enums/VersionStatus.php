<?php

declare(strict_types=1);

namespace App\Domain\Forms\Enums;

enum VersionStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
