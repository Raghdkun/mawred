<?php

declare(strict_types=1);

namespace App\Domain\People\Support\Imports;

use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\EmploymentType;
use App\Domain\Organization\Models\Grade;
use App\Domain\Organization\Models\Position;
use App\Domain\People\Models\CustomFieldDefinition;
use App\Support\Search\SearchText;
use Illuminate\Support\Collection;

/**
 * Reference data loaded once per import: active organization records matched by code or by
 * name in any language, branch phone regions, and active custom field definitions.
 */
final class ImportLookups
{
    /** @var array<string, array<string, string>> kind => normalised code/name => ulid */
    private array $refs = [];

    /** @var array<string, string|null> branch ulid => phone region */
    public array $branchRegions = [];

    /** @var array<int, string> branch id => ulid */
    public array $branchUlids = [];

    /** @var Collection<string, CustomFieldDefinition> */
    public Collection $customFields;

    public function __construct()
    {
        $kinds = [
            'branch' => [Branch::class, 'name'],
            'department' => [Department::class, 'name'],
            'position' => [Position::class, 'title'],
            'employment_type' => [EmploymentType::class, 'name'],
            'grade' => [Grade::class, 'name'],
        ];

        foreach ($kinds as $kind => [$model, $attribute]) {
            $this->refs[$kind] = [];

            foreach ($model::query()->where('is_active', true)->get() as $record) {
                $this->refs[$kind][SearchText::normalize($record->code)] = $record->ulid;

                foreach ((array) $record->getTranslations($attribute) as $name) {
                    if (is_string($name) && $name !== '') {
                        $this->refs[$kind][SearchText::normalize($name)] ??= $record->ulid;
                    }
                }

            }
        }

        foreach (Branch::query()->where('is_active', true)->get(['id', 'ulid', 'phone_region']) as $branch) {
            $this->branchRegions[$branch->ulid] = $branch->phone_region;
            $this->branchUlids[$branch->id] = $branch->ulid;
        }

        $this->customFields = CustomFieldDefinition::query()->where('is_active', true)->get()->keyBy('key');
    }

    public function ref(string $kind, string $value): ?string
    {
        return $this->refs[$kind][SearchText::normalize($value)] ?? null;
    }
}
