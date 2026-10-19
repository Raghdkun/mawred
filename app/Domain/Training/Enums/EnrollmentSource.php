<?php

declare(strict_types=1);

namespace App\Domain\Training\Enums;

/** How someone came to be enrolled. */
enum EnrollmentSource: string
{
    /** They enrolled (or asked to) from the catalog. */
    case SelfService = 'self';
    /** A manager, trainer or HR enrolled them. */
    case Assigned = 'assigned';
    /** The course's audience includes them (D100). */
    case Audience = 'audience';
    /** They joined a learning path that includes the course. */
    case Path = 'path';
    /** HR enrolled them from an approved training request (D105). */
    case Request = 'request';
    /** Their certificate is about to expire (D103). */
    case Renewal = 'renewal';
}
