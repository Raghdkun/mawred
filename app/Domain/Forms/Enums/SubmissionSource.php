<?php

declare(strict_types=1);

namespace App\Domain\Forms\Enums;

/** Where a submission came from: a signed-in person, the public link, or a builder test run. */
enum SubmissionSource: string
{
    case Internal = 'internal';
    case Public = 'public';
    case Test = 'test';
}
