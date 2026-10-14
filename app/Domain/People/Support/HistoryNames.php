<?php

declare(strict_types=1);

namespace App\Domain\People\Support;

use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\EmploymentType;
use App\Domain\Organization\Models\Grade;
use App\Domain\Organization\Models\Position;
use App\Domain\People\Models\Employee;
use App\Domain\People\Models\EmploymentHistoryEntry;
use Illuminate\Support\Collection;

/**
 * Names for the public ids recorded in employment history (`changes.from` / `changes.to`), so the
 * timeline reads "Riyadh HQ → Dubai Marina" in either language. Archived records still resolve.
 */
final class HistoryNames
{
    private const array MODELS = [
        'branch' => [Branch::class, 'name'],
        'department' => [Department::class, 'name'],
        'position' => [Position::class, 'title'],
        'employment_type' => [EmploymentType::class, 'name'],
        'grade' => [Grade::class, 'name'],
    ];

    /**
     * @param  Collection<int, EmploymentHistoryEntry>  $entries
     * @return array<string, array<string, string>> ulid => name per locale
     */
    public static function for(Collection $entries): array
    {
        $ids = [];

        foreach ($entries as $entry) {
            foreach (['from', 'to'] as $side) {
                $values = $entry->changes[$side] ?? $entry->changes;

                if (! is_array($values)) {
                    continue;
                }

                foreach ($values as $kind => $ulid) {
                    if (is_string($kind) && is_string($ulid) && (isset(self::MODELS[$kind]) || $kind === 'manager')) {
                        $ids[$kind][] = $ulid;
                    }
                }
            }
        }

        $names = [];

        foreach (self::MODELS as $kind => [$model, $attribute]) {
            if (! isset($ids[$kind])) {
                continue;
            }

            foreach ($model::withTrashed()->whereIn('ulid', array_unique($ids[$kind]))->get() as $record) {
                /** @var array<string, string> $translations */
                $translations = $record->getTranslations($attribute);
                $names[$record->ulid] = $translations;
            }
        }

        if (isset($ids['manager'])) {
            foreach (Employee::withTrashed()->whereIn('ulid', array_unique($ids['manager']))->get(['ulid', 'name_en', 'name_ar', 'preferred_name']) as $manager) {
                $names[$manager->ulid] = array_filter(['en' => $manager->preferred_name ?? $manager->name_en, 'ar' => $manager->preferred_name ?? $manager->name_ar]);
            }
        }

        return $names;
    }
}
