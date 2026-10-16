<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Data;

use App\Domain\Hiring\Models\JobRequisition;
use App\Domain\Hiring\Support\Texts;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A requisition row in the Requisitions table (handoff 28) and the header of its board. */
#[TypeScript]
final class RequisitionData extends Data
{
    /**
     * @param  array{active: int, total: int, postings: int}  $counts
     */
    public function __construct(
        public string $id,
        public string $number,
        public string $title,
        public string $status,
        public string $reason,
        public string $branch,
        public ?string $department,
        public ?string $position,
        public int $headcount,
        public ?string $hiringManager,
        public ?string $requestId,
        public ?int $salaryMinMinor,
        public ?int $salaryMaxMinor,
        public ?string $currency,
        public ?string $targetStartOn,
        public ?string $justification,
        public ?string $openedAt,
        public string $createdAt,
        public array $counts,
    ) {}

    public static function fromRequisition(JobRequisition $requisition): self
    {
        $locale = app()->getLocale();
        $requisition->loadMissing(['branch', 'department', 'position', 'hiringManager', 'request']);

        return new self(
            $requisition->ulid,
            $requisition->number,
            Texts::one($requisition, 'title', $locale) ?? $requisition->number,
            $requisition->status->value,
            $requisition->reason->value,
            Texts::one($requisition->branch, 'name', $locale) ?? '—',
            Texts::one($requisition->department, 'name', $locale),
            Texts::one($requisition->position, 'title', $locale),
            $requisition->headcount,
            $requisition->hiringManager?->displayName($locale),
            $requisition->request?->ulid,
            $requisition->salary_min_minor,
            $requisition->salary_max_minor,
            $requisition->currency,
            $requisition->target_start_on?->toDateString(),
            $requisition->justification,
            $requisition->opened_at?->toIso8601String(),
            $requisition->created_at->toIso8601String(),
            [
                'active' => Texts::count($requisition, 'active_count'),
                'total' => Texts::count($requisition, 'applications_count'),
                'postings' => Texts::count($requisition, 'postings_count'),
            ],
        );
    }
}
