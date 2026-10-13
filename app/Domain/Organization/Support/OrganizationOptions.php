<?php

declare(strict_types=1);

namespace App\Domain\Organization\Support;

use App\Domain\Organization\Data\CatalogEntryData;
use App\Domain\Organization\Enums\CatalogKind;
use App\Domain\Organization\Models\CostCenter;
use App\Domain\Organization\Models\EmploymentType;
use App\Domain\Organization\Models\Grade;

/**
 * Short select lists shared by the organization forms. Cost centres and grades stay small
 * (tens of rows), so they ship as props; branches, departments and positions use async pickers.
 */
final class OrganizationOptions
{
    /**
     * @return array{costCenters: list<CatalogEntryData>}
     */
    public static function costCenters(): array
    {
        return ['costCenters' => self::entries(CatalogKind::CostCenter)];
    }

    /**
     * @return array{grades: list<CatalogEntryData>}
     */
    public static function grades(): array
    {
        return ['grades' => self::entries(CatalogKind::Grade)];
    }

    /**
     * @return list<CatalogEntryData>
     */
    public static function entries(CatalogKind $kind, bool $activeOnly = true): array
    {
        $model = $kind->modelClass();

        return array_values($model::query()
            ->when($activeOnly, fn ($query) => $query->where('is_active', true))
            ->when($kind->hasRank(), fn ($query) => $query->orderByDesc('rank'))
            ->orderBy('code')
            ->get()
            ->map(fn (EmploymentType|Grade|CostCenter $entry): CatalogEntryData => CatalogEntryData::fromEntry($entry))
            ->all());
    }
}
