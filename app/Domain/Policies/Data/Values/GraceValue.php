<?php

declare(strict_types=1);

namespace App\Domain\Policies\Data\Values;

use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Lateness / early-leave grace in minutes. Within the grace nothing counts. Beyond it, minutes
 * count from the scheduled time (`scheduled`) or only past the grace (`grace_end`) (D50).
 */
#[TypeScript]
final class GraceValue extends Data
{
    public function __construct(
        public int $lateMinutes = 15,
        public int $earlyLeaveMinutes = 0,
        public string $countFrom = 'scheduled',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'lateMinutes' => ['integer', 'between:0,240'],
            'earlyLeaveMinutes' => ['integer', 'between:0,240'],
            'countFrom' => [Rule::in(['scheduled', 'grace_end'])],
        ];
    }
}
