<?php

declare(strict_types=1);

namespace App\Domain\Reports\Data;

use App\Domain\Access\Models\User;
use App\Domain\People\Models\Employee;
use App\Domain\Reports\Datasets\DatasetRegistry;
use App\Domain\Reports\Models\SavedReport;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A saved custom report in the library and the builder (D118, D119). */
#[TypeScript]
final class SavedReportData extends Data
{
    /**
     * @param  array<string, mixed>  $definition
     * @param  array{people: list<array{id: string, label: string}>, roles: list<string>}|null  $sharedWith  only for its owner
     */
    public function __construct(
        public string $id,
        public string $key,
        public string $name,
        public ?string $description,
        public string $dataset,
        public string $datasetTitle,
        public bool $mine,
        public ?string $owner,
        public array $definition,
        public ?array $sharedWith,
        public string $updatedAt,
    ) {}

    public static function fromSaved(SavedReport $report, User $viewer, string $locale): self
    {
        $dataset = resolve(DatasetRegistry::class)->find($report->dataset);
        $mine = $report->owner_user_id === $viewer->id;

        return new self(
            $report->ulid,
            $report->reportKey(),
            $report->name,
            $report->description,
            $report->dataset,
            $dataset === null ? $report->dataset : $dataset->title()[$locale === 'ar' ? 'ar' : 'en'],
            $mine,
            $mine ? null : $report->owner?->name,
            $mine ? $report->definition : [],
            $mine ? self::shares($report, $locale) : null,
            $report->updated_at->toIso8601String(),
        );
    }

    /**
     * @return array{people: list<array{id: string, label: string}>, roles: list<string>}
     */
    private static function shares(SavedReport $report, string $locale): array
    {
        $shares = $report->shares()->with('role')->get();
        $roles = [];

        foreach ($shares as $share) {
            if ($share->role !== null) {
                $roles[] = (string) $share->role->name;
            }
        }

        $people = array_values(Employee::query()->whereIn('user_id', $shares->pluck('user_id')->filter())->orderBy('name_en')->get()
            ->map(fn (Employee $e): array => ['id' => $e->ulid, 'label' => $e->displayName($locale)])->all());

        return ['people' => $people, 'roles' => $roles];
    }
}
