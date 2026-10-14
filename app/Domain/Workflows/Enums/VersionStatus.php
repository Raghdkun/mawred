<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Enums;

/** A workflow version's state; published versions are frozen. */
enum VersionStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
