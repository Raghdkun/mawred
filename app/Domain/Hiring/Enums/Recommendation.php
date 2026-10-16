<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Enums;

/** An interviewer's overall call (D90), scored 1–4 for the card average. */
enum Recommendation: string
{
    case StrongNo = 'strong_no';
    case No = 'no';
    case Yes = 'yes';
    case StrongYes = 'strong_yes';

    public function score(): int
    {
        return match ($this) {
            self::StrongNo => 1,
            self::No => 2,
            self::Yes => 3,
            self::StrongYes => 4,
        };
    }
}
