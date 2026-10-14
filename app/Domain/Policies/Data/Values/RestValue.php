<?php

declare(strict_types=1);

namespace App\Domain\Policies\Data\Values;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Rest between shifts, consecutive working days and the weekly maximum (scheduling checks). */
#[TypeScript]
final class RestValue extends Data
{
    public function __construct(
        public int $minRestHours = 11,
        public int $maxConsecutiveDays = 6,
        public int $maxWeeklyHours = 48,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'minRestHours' => ['integer', 'between:0,48'],
            'maxConsecutiveDays' => ['integer', 'between:1,31'],
            'maxWeeklyHours' => ['integer', 'between:1,168'],
        ];
    }
}
