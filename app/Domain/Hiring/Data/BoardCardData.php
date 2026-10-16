<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Data;

use App\Domain\Access\Models\User;
use App\Domain\Hiring\Enums\InterviewStatus;
use App\Domain\Hiring\Models\Candidate;
use App\Domain\Hiring\Models\HiringTag;
use App\Domain\Hiring\Models\Interview;
use App\Domain\Hiring\Models\JobApplication;
use App\Domain\Hiring\Models\PipelineStage;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A kanban card (handoff 29): who, how long in the stage, tags and flags. */
#[TypeScript]
final class BoardCardData extends Data
{
    /**
     * @param  list<string>  $tags
     * @param  list<string>  $knockouts
     * @param  list<string>  $interviewers  panel of the next interview
     */
    public function __construct(
        public string $id,
        public string $candidateId,
        public string $name,
        public string $stageId,
        public string $status,
        public string $source,
        public string $appliedAt,
        public string $stageEnteredAt,
        public bool $knockedOut,
        public array $knockouts,
        public array $tags,
        public bool $possibleDuplicate,
        public ?string $rejectionReason,
        public ?float $score = null,
        public array $interviewers = [],
        public ?string $nextInterviewAt = null,
    ) {}

    public static function fromApplication(JobApplication $application): self
    {
        $locale = app()->getLocale() === 'ar' ? 'ar' : 'en';
        $candidate = $application->candidate;
        $stage = $application->stage;
        $next = $application->relationLoaded('interviews')
            ? $application->interviews->first(fn (Interview $i): bool => in_array($i->status, [InterviewStatus::AwaitingBooking, InterviewStatus::Scheduled], true))
            : null;

        return new self(
            $application->ulid,
            $candidate instanceof Candidate ? $candidate->ulid : '',
            $candidate instanceof Candidate ? $candidate->name : '—',
            $stage instanceof PipelineStage ? $stage->ulid : '',
            $application->status->value,
            $application->source->value,
            $application->applied_at->toIso8601String(),
            $application->stage_entered_at->toIso8601String(),
            $application->knocked_out,
            array_map(fn (array $k): string => $k['label'][$locale], $application->knockout_reasons ?? []),
            $candidate instanceof Candidate ? array_values($candidate->tags->map(fn (HiringTag $tag): string => $tag->name)->all()) : [],
            $candidate instanceof Candidate && $candidate->possible_duplicate_of_id !== null,
            $application->rejection_reason?->value,
            $application->score === null ? null : (float) $application->score,
            $next instanceof Interview ? array_values($next->panel->map(fn (User $u): string => $u->name)->all()) : [],
            $next?->starts_at?->toIso8601String(),
        );
    }
}
