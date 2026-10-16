<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Enums;

/** A seat on a requisition's hiring team; members see every candidate for it (D82). */
enum MemberRole: string
{
    case Recruiter = 'recruiter';
    case HiringManager = 'hiring_manager';
    case Coordinator = 'coordinator';
}
