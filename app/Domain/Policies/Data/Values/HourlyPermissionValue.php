<?php

declare(strict_types=1);

namespace App\Domain\Policies\Data\Values;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Short permissions (leave a few hours early, come in late): monthly quota. */
#[TypeScript]
final class HourlyPermissionValue extends Data
{
    public function __construct(
        public int $minutesPerMonth = 240,
        public int $maxMinutesPerRequest = 120,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return ['minutesPerMonth' => ['integer', 'between:0,9600'], 'maxMinutesPerRequest' => ['integer', 'between:15,1440']];
    }
}
