<?php

declare(strict_types=1);

namespace App\Domain\Training\Data;

use App\Domain\Training\Enums\CourseCategory;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A course's details as trainers edit them (D98–D100, D103). */
#[TypeScript]
final class CourseInputData extends Data
{
    /**
     * @param  array{en: string, ar?: string|null}  $title
     * @param  array{en?: string|null, ar?: string|null}|null  $summary
     * @param  array{en?: string|null, ar?: string|null}|null  $description
     * @param  array{branches?: list<string>, departments?: list<string>, positions?: list<string>, employmentTypes?: list<string>, hiredWithinDays?: int|null}|null  $audience
     */
    public function __construct(
        public array $title,
        public ?array $summary = null,
        public ?array $description = null,
        public string $category = 'skills',
        public ?int $durationMinutes = null,
        public bool $requiresApproval = false,
        public bool $issuesCertificate = true,
        public ?int $validityMonths = null,
        public ?string $ownerId = null,
        public ?array $audience = null,
        public ?int $dueDays = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        $text = fn (int $max): array => ['nullable', 'string', "max:{$max}"];
        $ulids = ['array', 'max:200'];

        return [
            'title' => ['required', 'array'],
            'title.en' => ['required', 'string', 'max:160'],
            'title.ar' => $text(160),
            'summary' => ['nullable', 'array'],
            'summary.en' => $text(300),
            'summary.ar' => $text(300),
            'description' => ['nullable', 'array'],
            'description.en' => $text(8000),
            'description.ar' => $text(8000),
            'category' => ['required', Rule::enum(CourseCategory::class)],
            'durationMinutes' => ['nullable', 'integer', 'min:1', 'max:6000'],
            'requiresApproval' => ['boolean'],
            'issuesCertificate' => ['boolean'],
            'validityMonths' => ['nullable', 'integer', 'min:1', 'max:120'],
            'ownerId' => ['nullable', 'string', 'size:26', 'exists:users,ulid'],
            'audience' => ['nullable', 'array'],
            'audience.branches' => $ulids,
            'audience.branches.*' => ['string', 'size:26'],
            'audience.departments' => $ulids,
            'audience.departments.*' => ['string', 'size:26'],
            'audience.positions' => $ulids,
            'audience.positions.*' => ['string', 'size:26'],
            'audience.employmentTypes' => $ulids,
            'audience.employmentTypes.*' => ['string', 'size:26'],
            'audience.hiredWithinDays' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'dueDays' => ['nullable', 'integer', 'min:1', 'max:730'],
        ];
    }
}
