<?php

declare(strict_types=1);

namespace App\Domain\Leave\Data;

use App\Domain\Leave\Models\LeaveBlackout;
use App\Domain\Policies\Models\LeaveType;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A blackout period in the admin list. */
#[TypeScript]
final class LeaveBlackoutData extends Data
{
    /**
     * @param  array<string, string>  $name
     * @param  array<string, string>|null  $branchName
     * @param  array<string, string>|null  $departmentName
     * @param  list<array{id: string, name: array<string, string>}>  $leaveTypes
     */
    public function __construct(
        public string $id,
        public array $name,
        public string $startsOn,
        public string $endsOn,
        public ?string $branchId,
        public ?array $branchName,
        public ?string $departmentId,
        public ?array $departmentName,
        public array $leaveTypes,
    ) {}

    /**
     * @param  array<int, LeaveType>  $types  leave types by id
     */
    public static function fromModel(LeaveBlackout $blackout, array $types): self
    {
        $leaveTypes = [];

        foreach ($blackout->leave_type_ids ?? [] as $id) {
            $type = $types[(int) $id] ?? null;

            if ($type instanceof LeaveType) {
                $leaveTypes[] = ['id' => $type->ulid, 'name' => self::names($type->getTranslations('name'))];
            }
        }

        return new self(
            id: $blackout->ulid,
            name: self::names($blackout->getTranslations('name')),
            startsOn: $blackout->starts_on->toDateString(),
            endsOn: $blackout->ends_on->toDateString(),
            branchId: $blackout->branch?->ulid,
            branchName: $blackout->branch === null ? null : self::names($blackout->branch->getTranslations('name')),
            departmentId: $blackout->department?->ulid,
            departmentName: $blackout->department === null ? null : self::names($blackout->department->getTranslations('name')),
            leaveTypes: $leaveTypes,
        );
    }

    /**
     * @param  array<mixed>  $translations
     * @return array<string, string>
     */
    private static function names(array $translations): array
    {
        $names = [];

        foreach ($translations as $locale => $value) {
            if (is_string($value)) {
                $names[(string) $locale] = $value;
            }
        }

        return $names;
    }
}
