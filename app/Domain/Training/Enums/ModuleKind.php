<?php

declare(strict_types=1);

namespace App\Domain\Training\Enums;

/** What a course module holds (D98). */
enum ModuleKind: string
{
    case Text = 'text';
    case Video = 'video';
    case File = 'file';
    case Quiz = 'quiz';
}
