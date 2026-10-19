<?php

declare(strict_types=1);

namespace App\Domain\Training\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A learning path: its title, description and courses in order. */
#[TypeScript]
final class LearningPathInputData extends Data
{
    /**
     * @param  array{en: string, ar?: string|null}  $title
     * @param  array{en?: string|null, ar?: string|null}|null  $description
     * @param  list<string>  $courses  course ULIDs in order
     */
    public function __construct(
        public array $title,
        public ?array $description = null,
        public array $courses = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'title' => ['required', 'array'],
            'title.en' => ['required', 'string', 'max:160'],
            'title.ar' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'array'],
            'description.en' => ['nullable', 'string', 'max:4000'],
            'description.ar' => ['nullable', 'string', 'max:4000'],
            'courses' => ['required', 'array', 'min:2', 'max:30'],
            'courses.*' => ['string', 'size:26', 'distinct', 'exists:courses,ulid'],
        ];
    }
}
