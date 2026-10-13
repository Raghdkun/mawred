<?php

declare(strict_types=1);

namespace App\Support\Actors;

enum ActorType: string
{
    case User = 'user';
    case System = 'system';
    case Integration = 'integration';
    case Token = 'token';
}
