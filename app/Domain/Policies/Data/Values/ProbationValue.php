<?php

declare(strict_types=1);

namespace App\Domain\Policies\Data\Values;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Probation length (sets the default probation end at hire) and notice periods. */
#[TypeScript]
final class ProbationValue extends Data
{
    public function __construct(
        public int $probationDays = 90,
        public int $extensionDays = 0,
        public int $employeeNoticeDays = 30,
        public int $employerNoticeDays = 30,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'probationDays' => ['integer', 'between:0,365'],
            'extensionDays' => ['integer', 'between:0,365'],
            'employeeNoticeDays' => ['integer', 'between:0,365'],
            'employerNoticeDays' => ['integer', 'between:0,365'],
        ];
    }
}
