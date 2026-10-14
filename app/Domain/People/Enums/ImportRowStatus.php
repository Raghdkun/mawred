<?php

declare(strict_types=1);

namespace App\Domain\People\Enums;

enum ImportRowStatus: string
{
    case Valid = 'valid';
    case Invalid = 'invalid';
    case Done = 'done';
    case Failed = 'failed';
}
