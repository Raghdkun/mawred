<?php

declare(strict_types=1);

namespace App\Domain\Forms\Data;

use App\Domain\Forms\Models\FormSubmission;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A submission row; `answers` is only filled for the detail view (an audited read). */
#[TypeScript]
final class SubmissionData extends Data
{
    /**
     * @param  array<string, mixed>|null  $answers
     */
    public function __construct(
        public string $id,
        public string $createdAt,
        public string $source,
        public int $version,
        public ?string $submittedBy,
        public ?array $answers = null,
    ) {}

    /**
     * Expects `version`, `employee` and `submitter` loaded.
     */
    public static function fromSubmission(FormSubmission $submission, bool $withAnswers = false): self
    {
        return new self(
            id: $submission->ulid,
            createdAt: $submission->created_at->toIso8601String(),
            source: $submission->source->value,
            version: $submission->pinnedVersion()->number,
            submittedBy: $submission->employee?->displayName(app()->getLocale()) ?? $submission->submitter?->name,
            answers: $withAnswers ? $submission->data : null,
        );
    }
}
