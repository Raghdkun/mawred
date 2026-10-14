<?php

declare(strict_types=1);

namespace App\Domain\Policies\Data\Values;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Lateness / early-leave grace in minutes. */
#[TypeScript]
final class GraceValue extends Data
{
    public function __construct(
        public int $lateMinutes = 15,
        public int $earlyLeaveMinutes = 0,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return ['lateMinutes' => ['integer', 'between:0,240'], 'earlyLeaveMinutes' => ['integer', 'between:0,240']];
    }
}
