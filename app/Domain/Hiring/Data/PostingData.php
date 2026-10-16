<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Data;

use App\Domain\Hiring\Models\JobPosting;
use App\Domain\Hiring\Support\Texts;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A posting as HR edits it (both languages). */
#[TypeScript]
final class PostingData extends Data
{
    /**
     * @param  array{en: string, ar: string}  $title
     * @param  array{en: string, ar: string}  $description
     * @param  array{en: string, ar: string}  $summary
     * @param  array{en: string, ar: string}  $location
     * @param  list<array{field: string, rule: mixed}>  $knockouts
     * @param  array{title: array{en: string, ar: string}, description: array{en: string, ar: string}}  $seo
     */
    public function __construct(
        public string $id,
        public string $slug,
        public string $url,
        public array $title,
        public array $description,
        public array $summary,
        public array $location,
        public string $workplace,
        public bool $showSalary,
        public ?string $formId,
        public array $knockouts,
        public array $seo,
        public string $status,
        public ?string $publishedAt,
        public ?string $closesOn,
        public int $applicants,
        public ?string $cognitoSourceId = null,
    ) {}

    public static function fromPosting(JobPosting $posting, ?string $formUlid): self
    {
        $both = fn (string $attribute): array => Texts::pair($posting, $attribute);
        $seo = $posting->seo ?? [];
        $pair = fn (mixed $v): array => ['en' => is_array($v) && is_string($v['en'] ?? null) ? $v['en'] : '', 'ar' => is_array($v) && is_string($v['ar'] ?? null) ? $v['ar'] : ''];

        return new self(
            $posting->ulid,
            $posting->slug,
            route('careers.show', $posting->slug),
            $both('title'),
            $both('description'),
            $both('summary'),
            $both('location'),
            $posting->workplace->value,
            $posting->show_salary,
            $formUlid,
            $posting->knockouts ?? [],
            ['title' => $pair($seo['title'] ?? null), 'description' => $pair($seo['description'] ?? null)],
            $posting->status->value,
            $posting->published_at?->toIso8601String(),
            $posting->closes_on?->toDateString(),
            Texts::count($posting, 'applications_count'),
            $posting->cognitoSource?->ulid,
        );
    }
}
