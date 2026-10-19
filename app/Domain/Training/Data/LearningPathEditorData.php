<?php

declare(strict_types=1);

namespace App\Domain\Training\Data;

use App\Domain\Forms\Support\FieldSpec;
use App\Domain\Training\Models\Course;
use App\Domain\Training\Models\LearningPath;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A learning path as trainers edit it: both languages and its course ids in order. */
#[TypeScript]
final class LearningPathEditorData extends Data
{
    /**
     * @param  array{en: string, ar: string}  $title
     * @param  array{en: string, ar: string}  $description
     * @param  list<string>  $courses
     */
    public function __construct(
        public string $id,
        public array $title,
        public array $description,
        public string $status,
        public array $courses,
    ) {}

    public static function fromPath(LearningPath $path): self
    {
        $pair = fn (mixed $v): array => ['en' => FieldSpec::text($v, 'en'), 'ar' => is_array($v) && is_string($v['ar'] ?? null) ? $v['ar'] : ''];

        return new self(
            $path->ulid,
            $pair($path->getTranslations('title')),
            $pair($path->getTranslations('description')),
            $path->status->value,
            array_values($path->courses->map(fn (Course $c): string => $c->ulid)->all()),
        );
    }
}
