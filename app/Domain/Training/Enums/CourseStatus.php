<?php

declare(strict_types=1);

namespace App\Domain\Training\Enums;

/** Courses and learning paths are drafted, published to the catalog, then archived. */
enum CourseStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
