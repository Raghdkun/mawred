<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Data;

use App\Domain\Hiring\Enums\Workplace;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class JobPostingInputData extends Data
{
    /**
     * @param  array{en: string, ar?: string|null}  $title
     * @param  array{en: string, ar?: string|null}  $description
     * @param  array{en?: string|null, ar?: string|null}|null  $summary
     * @param  array{en?: string|null, ar?: string|null}|null  $location
     * @param  list<array{field: string, rule: mixed}>  $knockouts
     * @param  array{title?: array{en?: string|null, ar?: string|null}, description?: array{en?: string|null, ar?: string|null}}|null  $seo
     */
    public function __construct(
        public array $title,
        public array $description,
        public ?array $summary = null,
        public ?array $location = null,
        public string $workplace = 'onsite',
        public bool $showSalary = false,
        public ?string $formId = null,
        public array $knockouts = [],
        public ?array $seo = null,
        public ?string $closesOn = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        $text = fn (int $max): array => ['nullable', 'string', "max:{$max}"];

        return [
            'title' => ['required', 'array'],
            'title.en' => ['required', 'string', 'max:120'],
            'title.ar' => $text(120),
            'description' => ['required', 'array'],
            'description.en' => ['required', 'string', 'max:12000'],
            'description.ar' => $text(12000),
            'summary' => ['nullable', 'array'],
            'summary.en' => $text(300),
            'summary.ar' => $text(300),
            'location' => ['nullable', 'array'],
            'location.en' => $text(120),
            'location.ar' => $text(120),
            'workplace' => ['required', Rule::enum(Workplace::class)],
            'showSalary' => ['boolean'],
            'formId' => ['nullable', 'string', 'size:26', 'exists:forms,ulid'],
            'knockouts' => ['array', 'max:20'],
            'knockouts.*.field' => ['required', 'string', 'max:40'],
            'knockouts.*.rule' => ['required'],
            'seo' => ['nullable', 'array'],
            'seo.title' => ['nullable', 'array'],
            'seo.title.en' => $text(70),
            'seo.title.ar' => $text(70),
            'seo.description' => ['nullable', 'array'],
            'seo.description.en' => $text(160),
            'seo.description.ar' => $text(160),
            'closesOn' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
