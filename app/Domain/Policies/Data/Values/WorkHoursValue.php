<?php

declare(strict_types=1);

namespace App\Domain\Policies\Data\Values;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Standard day: start, end and unpaid break. Falls back to the branch hours (D46). */
#[TypeScript]
final class WorkHoursValue extends Data
{
    public function __construct(
        public string $startsAt,
        public string $endsAt,
        public int $breakMinutes = 60,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'startsAt' => ['required', 'date_format:H:i'],
            'endsAt' => ['required', 'date_format:H:i', 'different:startsAt'],
            'breakMinutes' => ['integer', 'between:0,480'],
        ];
    }

    /** Scheduled working minutes for one day (overnight shifts wrap past midnight). */
    public function scheduledMinutes(): int
    {
        [$sh, $sm] = array_map(intval(...), explode(':', $this->startsAt));
        [$eh, $em] = array_map(intval(...), explode(':', $this->endsAt));
        $minutes = ($eh * 60 + $em) - ($sh * 60 + $sm);

        return max(0, ($minutes <= 0 ? $minutes + 1440 : $minutes) - $this->breakMinutes);
    }
}
