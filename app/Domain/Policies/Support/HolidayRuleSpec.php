<?php

declare(strict_types=1);

namespace App\Domain\Policies\Support;

use App\Domain\Policies\Enums\HolidayType;
use App\Domain\Policies\Enums\ObservedRule;

/**
 * A holiday rule as plain values, so the calculator needs no database or container.
 */
final readonly class HolidayRuleSpec
{
    /**
     * @param  array<string, string>  $name
     */
    public function __construct(
        public string $id,
        public int $calendarId,
        public array $name,
        public HolidayType $type,
        public ?int $month = null,
        public ?int $day = null,
        public ?int $weekday = null,
        public ?int $nth = null,
        public ?string $date = null,
        public int $durationDays = 1,
        public ObservedRule $observed = ObservedRule::None,
        public bool $hijriBased = false,
        public ?int $fromYear = null,
        public ?int $toYear = null,
    ) {}
}
