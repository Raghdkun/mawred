<?php

declare(strict_types=1);

namespace App\Domain\Policies\Data;

use App\Domain\Policies\Support\HolidayOccurrence;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A holiday day as the calendar shows it (screen 21). */
#[TypeScript]
final class HolidayDayData extends Data
{
    /**
     * @param  array<string, string>  $name
     */
    public function __construct(
        public string $ruleId,
        public array $name,
        public string $date,
        public string $observedOn,
        public bool $hijriBased,
        public int $day,
        public int $days,
    ) {}

    public static function fromOccurrence(HolidayOccurrence $occurrence): self
    {
        return new self($occurrence->ruleId, $occurrence->name, $occurrence->date, $occurrence->observedOn, $occurrence->hijriBased, $occurrence->day, $occurrence->days);
    }
}
