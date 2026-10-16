<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One of a candidate's applications on their profile: where it stands, answers, CV, history. */
#[TypeScript]
final class ApplicationDetailData extends Data
{
    /**
     * @param  array{id: string, number: string, title: string, open: bool}  $job
     * @param  list<StageData>  $stages
     * @param  list<string>  $knockouts
     * @param  array<string, mixed>|null  $schema
     * @param  array<string, mixed>  $answers
     * @param  array<string, string>  $display
     * @param  array{url: string, name: string, mime: string}|null  $cv
     * @param  list<ApplicationEventData>  $events
     * @param  array{update: bool}  $can
     */
    public function __construct(
        public string $id,
        public array $job,
        public string $stageId,
        public array $stages,
        public string $status,
        public string $source,
        public string $appliedAt,
        public string $stageEnteredAt,
        public array $knockouts,
        public ?string $rejectionReason,
        public ?array $schema,
        public array $answers,
        public array $display,
        public ?array $cv,
        public array $events,
        public array $can,
    ) {}
}
