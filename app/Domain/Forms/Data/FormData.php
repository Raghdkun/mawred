<?php

declare(strict_types=1);

namespace App\Domain\Forms\Data;

use App\Domain\Forms\Models\Form;
use App\Domain\Forms\Models\FormVersion;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A form in lists and the builder header. `status`: `draft` (never published), `published`, or
 * `changes` (published, with an unpublished draft).
 */
#[TypeScript]
final class FormData extends Data
{
    /**
     * @param  array<string, string>  $name
     * @param  array<string, string>  $description
     * @param  array<string, string>  $successMessage
     */
    public function __construct(
        public string $id,
        public string $code,
        public array $name,
        public array $description,
        public ?string $category,
        public string $status,
        public ?int $publishedNumber,
        public ?string $publishedAt,
        public bool $isPublic,
        public ?string $publicSlug,
        public ?string $publicUrl,
        public array $successMessage,
        public ?string $closesAt,
        public int $submissionsCount,
    ) {}

    /**
     * Expects `publishedVersion` and `draft` loaded, and `submissions_count` counted.
     */
    public static function fromForm(Form $form): self
    {
        /** @var array<string, string> $name */
        $name = $form->getTranslations('name');
        /** @var array<string, string> $description */
        $description = $form->getTranslations('description');
        $published = $form->publishedVersion;
        $settings = $form->public_settings ?? [];
        $count = $form->getAttribute('submissions_count');

        return new self(
            id: $form->ulid,
            code: $form->code,
            name: $name,
            description: $description,
            category: $form->category,
            status: $published instanceof FormVersion ? $form->draft !== null ? 'changes' : 'published' : ('draft'),
            publishedNumber: $published?->number,
            publishedAt: $published?->published_at?->toIso8601String(),
            isPublic: $form->is_public,
            publicSlug: $form->public_slug,
            publicUrl: $form->is_public && $form->public_slug !== null ? route('public-forms.show', $form->public_slug) : null,
            successMessage: $settings['successMessage'] ?? [],
            closesAt: $settings['closesAt'] ?? null,
            submissionsCount: is_numeric($count) ? (int) $count : 0,
        );
    }
}
