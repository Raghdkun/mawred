<?php

declare(strict_types=1);

namespace App\Domain\Training\Enums;

/** The catalog's filter pills (handoff 33). */
enum CourseCategory: string
{
    case Compliance = 'compliance';
    case Safety = 'safety';
    case Onboarding = 'onboarding';
    case Skills = 'skills';
    case Leadership = 'leadership';
    case Technical = 'technical';
}
