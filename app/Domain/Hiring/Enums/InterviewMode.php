<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Enums;

enum InterviewMode: string
{
    case Onsite = 'onsite';
    case Video = 'video';
    case Phone = 'phone';
}
