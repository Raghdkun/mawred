<?php

declare(strict_types=1);

namespace App\Domain\Organization\Support;

use App\Domain\Access\Models\User;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Queries\DepartmentTreeQuery;
use App\Support\Search\SearchHitData;
use App\Support\Search\SearchProvider;
use App\Support\Search\SearchText;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Departments for global search (organization.view, reference data).
 */
final readonly class DepartmentSearchProvider implements SearchProvider
{
    public function type(): string
    {
        return 'departments';
    }

    public function ability(): string
    {
        return 'organization.view';
    }

    public function search(Authenticatable $viewer, string $term, int $limit): array
    {
        if (! $viewer instanceof User) {
            return [];
        }

        $locale = app()->getLocale();

        return array_values(resolve(DepartmentTreeQuery::class)->search($term)->visibleTo($viewer)
            ->reorder()
            ->orderByRaw('similarity(search_text, ?) desc', [SearchText::normalize($term)])
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(function (Department $record) use ($locale): SearchHitData {
                $name = $record->getTranslation('name', $locale);

                return new SearchHitData(
                    type: 'departments',
                    id: $record->ulid,
                    title: is_string($name) ? $name : $record->code,
                    subtitle: $record->code,
                    url: "/organization/departments?focus={$record->ulid}",
                    status: $record->is_active ? null : 'inactive',
                );
            })
            ->all());
    }
}
