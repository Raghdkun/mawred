<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Enums;

/**
 * What a pipeline stage means, whatever HR calls it. The first stage is always `applied` and the
 * last is always `hired`; offers and interviews hook onto their kinds in Phase 6b.
 */
enum StageKind: string
{
    case Applied = 'applied';
    case Screening = 'screening';
    case Interview = 'interview';
    case Offer = 'offer';
    case Hired = 'hired';
}
