<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Enums;

/** Human work: an approval decision or a to-do (Assign Task node). */
enum TaskKind: string
{
    case Approval = 'approval';
    case Task = 'task';
}
