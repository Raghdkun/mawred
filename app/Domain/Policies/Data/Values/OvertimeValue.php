<?php

declare(strict_types=1);

namespace App\Domain\Policies\Data\Values;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Overtime: when it starts counting, the multiplier per day type (night overrides when higher),
 * rounding and a monthly cap.
 */
#[TypeScript]
final class OvertimeValue extends Data
{
    public function __construct(
        public bool $enabled = true,
        public int $dailyThresholdMinutes = 0,
        public float $weekday = 1.5,
        public float $weekend = 2.0,
        public float $holiday = 2.0,
        public float $night = 1.75,
        public string $nightStartsAt = '22:00',
        public string $nightEndsAt = '06:00',
        public int $roundingMinutes = 15,
        public ?int $monthlyCapMinutes = null,
        public bool $requiresApproval = true,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'enabled' => ['boolean'],
            'dailyThresholdMinutes' => ['integer', 'between:0,600'],
            'weekday' => ['numeric', 'between:1,5'],
            'weekend' => ['numeric', 'between:1,5'],
            'holiday' => ['numeric', 'between:1,5'],
            'night' => ['numeric', 'between:1,5'],
            'nightStartsAt' => ['date_format:H:i'],
            'nightEndsAt' => ['date_format:H:i'],
            'roundingMinutes' => ['integer', 'between:1,60'],
            'monthlyCapMinutes' => ['nullable', 'integer', 'between:0,44640'],
            'requiresApproval' => ['boolean'],
        ];
    }
}
