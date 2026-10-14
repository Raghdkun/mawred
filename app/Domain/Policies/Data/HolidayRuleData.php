<?php

declare(strict_types=1);

namespace App\Domain\Policies\Data;

use App\Domain\Policies\Models\HolidayRule;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class HolidayRuleData extends Data
{
    /**
     * @param  array<string, string>  $name
     */
    public function __construct(
        public string $id,
        public array $name,
        public string $type,
        public ?int $month,
        public ?int $day,
        public ?int $weekday,
        public ?int $nth,
        public ?string $date,
        public int $durationDays,
        public string $observed,
        public bool $hijriBased,
        public ?int $fromYear,
        public ?int $toYear,
    ) {}

    public static function fromRule(HolidayRule $rule): self
    {
        /** @var array<string, string> $name */
        $name = $rule->getTranslations('name');

        return new self(
            id: $rule->ulid,
            name: $name,
            type: $rule->type->value,
            month: $rule->month,
            day: $rule->day,
            weekday: $rule->weekday,
            nth: $rule->nth,
            date: $rule->date?->toDateString(),
            durationDays: $rule->duration_days,
            observed: $rule->observed->value,
            hijriBased: $rule->hijri_based,
            fromYear: $rule->from_year,
            toYear: $rule->to_year,
        );
    }
}
