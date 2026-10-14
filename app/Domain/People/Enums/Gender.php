<?php

declare(strict_types=1);

namespace App\Domain\People\Enums;

enum Gender: string
{
    case Female = 'female';
    case Male = 'male';
}
