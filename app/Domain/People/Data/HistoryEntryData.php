<?php

declare(strict_types=1);

namespace App\Domain\People\Data;

use App\Domain\People\Models\EmploymentHistoryEntry;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Timeline item. `salary` is null unless the viewer can read `employee.salary`.
 */
#[TypeScript]
final class HistoryEntryData extends Data
{
    /**
     * @param  array<string, mixed>  $changes
     * @param  array{minor: int, currency: string}|null  $salary
     */
    public function __construct(
        public string $id,
        public string $event,
        public string $effectiveOn,
        public array $changes,
        public ?array $salary,
        public ?string $note,
        public string $recordedAt,
    ) {}

    public static function fromEntry(EmploymentHistoryEntry $entry, bool $showSalary): self
    {
        $salary = $showSalary ? $entry->salary : null;

        return new self(
            id: $entry->ulid,
            event: $entry->event->value,
            effectiveOn: $entry->effective_on->toDateString(),
            changes: $entry->changes,
            salary: $salary === null ? null : ['minor' => $salary->minor, 'currency' => $salary->currency],
            note: $entry->note,
            recordedAt: $entry->created_at?->toIso8601String() ?? '',
        );
    }
}
