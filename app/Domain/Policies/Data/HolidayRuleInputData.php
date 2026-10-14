<?php

declare(strict_types=1);

namespace App\Domain\Policies\Data;

use App\Domain\Policies\Enums\HolidayType;
use App\Domain\Policies\Enums\ObservedRule;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A holiday rule as entered: fixed (month + day), nth weekday (month + weekday + nth, -1 = last)
 * or a one-off date. Hijri holidays are one-off dates entered each year and flagged for display.
 */
#[TypeScript]
final class HolidayRuleInputData extends Data
{
    /**
     * @param  array{en: string, ar?: string|null}  $name
     */
    public function __construct(
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

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'array'],
            'name.en' => ['required', 'string', 'max:80'],
            'name.ar' => ['nullable', 'string', 'max:80'],
            'type' => ['required', Rule::enum(HolidayType::class)],
            'month' => ['nullable', 'required_unless:type,one_off', 'integer', 'between:1,12'],
            'day' => ['nullable', 'required_if:type,fixed', 'integer', 'between:1,31'],
            'weekday' => ['nullable', 'required_if:type,nth_weekday', 'integer', 'between:1,7'],
            'nth' => ['nullable', 'required_if:type,nth_weekday', 'integer', Rule::in([-1, 1, 2, 3, 4, 5])],
            'date' => ['nullable', 'required_if:type,one_off', 'date_format:Y-m-d'],
            'durationDays' => ['integer', 'between:1,30'],
            'observed' => [Rule::enum(ObservedRule::class)],
            'hijriBased' => ['boolean'],
            'fromYear' => ['nullable', 'integer', 'between:1900,2200'],
            'toYear' => ['nullable', 'integer', 'between:1900,2200', 'gte:fromYear'],
        ];
    }
}
