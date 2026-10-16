<?php

declare(strict_types=1);

namespace App\Domain\Calendar\Enums;

enum ParticipantRole: string
{
    case Organizer = 'organizer';
    case Required = 'required';
    case Optional = 'optional';
}
