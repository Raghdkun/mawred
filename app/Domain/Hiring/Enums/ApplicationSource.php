<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Enums;

enum ApplicationSource: string
{
    case Careers = 'careers';
    case Cognito = 'cognito';
    case Manual = 'manual';
    case Referral = 'referral';
    case TalentPool = 'talent_pool';
}
