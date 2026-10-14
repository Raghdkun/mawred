<?php

declare(strict_types=1);

namespace App\Domain\Policies\Data;

use App\Domain\Organization\Models\Branch;
use App\Domain\Policies\Models\HolidayCalendar;
use App\Domain\Policies\Models\HolidayRule;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class HolidayCalendarData extends Data
{
    /**
     * @param  array<string, string>  $name
     * @param  list<array{id: string, name: array<string, string>}>  $branches
     * @param  list<HolidayRuleData>  $rules
     */
    public function __construct(
        public string $id,
        public string $code,
        public array $name,
        public ?string $countryCode,
        public ?string $presetKey,
        public bool $isActive,
        public array $branches,
        public array $rules,
    ) {}

    /**
     * Expects `branches` and `rules` loaded.
     */
    public static function fromCalendar(HolidayCalendar $calendar): self
    {
        /** @var array<string, string> $name */
        $name = $calendar->getTranslations('name');

        return new self(
            id: $calendar->ulid,
            code: $calendar->code,
            name: $name,
            countryCode: $calendar->country_code,
            presetKey: $calendar->preset_key,
            isActive: $calendar->is_active,
            branches: array_values($calendar->branches->map(function (Branch $branch): array {
                /** @var array<string, string> $branchName */
                $branchName = $branch->getTranslations('name');

                return ['id' => $branch->ulid, 'name' => $branchName];
            })->all()),
            rules: array_values($calendar->rules->map(fn (HolidayRule $rule): HolidayRuleData => HolidayRuleData::fromRule($rule))->all()),
        );
    }
}
