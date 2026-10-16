<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Data;

use App\Domain\Hiring\Models\JobPosting;
use App\Domain\Hiring\Models\JobRequisition;
use App\Domain\Hiring\Support\Texts;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A posting as the public careers site shows it (D87), in the visitor's language. */
#[TypeScript]
final class CareersPostingData extends Data
{
    /**
     * @param  array{min: int|null, max: int|null, currency: string}|null  $salary
     */
    public function __construct(
        public string $slug,
        public string $title,
        public ?string $summary,
        public string $description,
        public ?string $location,
        public string $workplace,
        public ?string $employmentType,
        public ?string $department,
        public string $branch,
        public ?array $salary,
        public ?string $publishedAt,
        public ?string $closesOn,
    ) {}

    public static function fromPosting(JobPosting $posting): self
    {
        $locale = app()->getLocale();
        /** @var JobRequisition $requisition */
        $requisition = $posting->requisition;
        $text = fn (string $attribute): ?string => Texts::one($posting, $attribute, $locale);
        $salary = $posting->show_salary && $requisition->currency !== null && ($requisition->salary_min_minor !== null || $requisition->salary_max_minor !== null)
            ? ['min' => $requisition->salary_min_minor, 'max' => $requisition->salary_max_minor, 'currency' => $requisition->currency]
            : null;

        return new self(
            $posting->slug,
            $text('title') ?? '',
            $text('summary'),
            $text('description') ?? '',
            $text('location') ?? Texts::one($requisition->branch, 'name', $locale),
            $posting->workplace->value,
            Texts::one($requisition->employmentType, 'name', $locale),
            Texts::one($requisition->department, 'name', $locale),
            Texts::one($requisition->branch, 'name', $locale) ?? '',
            $salary,
            $posting->published_at?->toIso8601String(),
            $posting->closes_on?->toDateString(),
        );
    }
}
