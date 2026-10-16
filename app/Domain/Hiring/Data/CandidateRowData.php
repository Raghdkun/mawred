<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Data;

use App\Domain\Hiring\Models\Candidate;
use App\Domain\Hiring\Models\HiringTag;
use App\Domain\Hiring\Models\JobApplication;
use App\Domain\Hiring\Support\Texts;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A candidate in the talent pool list. */
#[TypeScript]
final class CandidateRowData extends Data
{
    /**
     * @param  list<string>  $tags
     * @param  list<array{id: string, job: string, stage: string, status: string}>  $applications
     */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $location,
        public array $tags,
        public bool $talentPool,
        public bool $possibleDuplicate,
        public string $lastActivityAt,
        public array $applications,
    ) {}

    public static function fromCandidate(Candidate $candidate): self
    {
        $locale = app()->getLocale();

        return new self(
            $candidate->ulid,
            $candidate->name,
            $candidate->location,
            array_values($candidate->tags->map(fn (HiringTag $t): string => $t->name)->all()),
            $candidate->talent_pool,
            $candidate->possible_duplicate_of_id !== null,
            $candidate->last_activity_at->toIso8601String(),
            array_values($candidate->applications->map(fn (JobApplication $a): array => [
                'id' => $a->ulid,
                'job' => Texts::one($a->requisition, 'title', $locale) ?? '—',
                'stage' => Texts::one($a->stage, 'name', $locale) ?? '—',
                'status' => $a->status->value,
            ])->all()),
        );
    }
}
