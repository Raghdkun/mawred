<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** An interview on the candidate profile, with the viewer's scorecard and, when allowed, the others'. */
#[TypeScript]
final class InterviewData extends Data
{
    /**
     * @param  list<array{id: string, name: string}>  $panel
     * @param  list<ScorecardData>  $scorecards  others' submitted scorecards the viewer may read (D82)
     * @param  array{score: bool, manage: bool, readOthers: bool}  $can
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $status,
        public string $mode,
        public ?string $location,
        public int $durationMinutes,
        public string $timezone,
        public ?string $startsAt,
        public ?string $endsAt,
        public ?string $bookingUrl,
        public array $panel,
        public ?ScorecardData $mine,
        public array $scorecards,
        public int $submitted,
        public array $can,
    ) {}
}
