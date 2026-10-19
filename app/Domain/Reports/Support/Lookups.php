<?php

declare(strict_types=1);

namespace App\Domain\Reports\Support;

use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Department;

/** Branch and department names by id for report rows, loaded once per run. */
final class Lookups
{
    /** @var array<int, string>|null */
    private ?array $branches = null;

    /** @var array<int, string>|null */
    private ?array $departments = null;

    public function __construct(private readonly string $locale) {}

    public function branch(?int $id): string
    {
        $this->branches ??= $this->names(Branch::class);

        return $id === null ? '—' : ($this->branches[$id] ?? '—');
    }

    public function department(?int $id): string
    {
        $this->departments ??= $this->names(Department::class);

        return $id === null ? '—' : ($this->departments[$id] ?? '—');
    }

    /**
     * Someone's name the way the profile shows it.
     */
    public static function person(?string $preferred, ?string $en, ?string $ar, string $locale): string
    {
        if ($preferred !== null && $preferred !== '') {
            return $preferred;
        }

        return $locale === 'ar' && $ar !== null && $ar !== '' ? $ar : (string) $en;
    }

    /**
     * @param  class-string<Branch|Department>  $model
     * @return array<int, string>
     */
    private function names(string $model): array
    {
        $names = [];

        foreach ($model::withTrashed()->get(['id', 'name']) as $row) {
            $translations = $row->getTranslations('name');
            $name = $translations[$this->locale] ?? $translations['en'] ?? '';
            $names[$row->id] = is_string($name) ? $name : '';
        }

        return $names;
    }
}
