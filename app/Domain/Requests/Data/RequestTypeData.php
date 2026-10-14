<?php

declare(strict_types=1);

namespace App\Domain\Requests\Data;

use App\Domain\Requests\Models\RequestType;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A card in the request center (handoff 10), and a row in request type settings. */
#[TypeScript]
final class RequestTypeData extends Data
{
    /**
     * @param  array<string, string>  $name
     * @param  array<string, string>  $description
     */
    public function __construct(
        public string $id,
        public string $code,
        public array $name,
        public array $description,
        public string $category,
        public ?string $icon,
        public bool $isSensitive,
        public bool $isActive,
        public bool $available,
        public int $availableInPhase,
        public ?string $formId,
        public ?string $workflowId,
        public bool $ready,
    ) {}

    /**
     * Expects `form` and `workflow` loaded.
     */
    public static function fromType(RequestType $type): self
    {
        /** @var array<string, string> $name */
        $name = $type->getTranslations('name');
        /** @var array<string, string> $description */
        $description = $type->getTranslations('description');

        return new self(
            id: $type->ulid,
            code: $type->code,
            name: $name,
            description: $description,
            category: $type->category->value,
            icon: $type->icon,
            isSensitive: $type->is_sensitive,
            isActive: $type->is_active,
            available: $type->isAvailable(),
            availableInPhase: $type->available_in_phase,
            formId: $type->form?->ulid,
            workflowId: $type->workflow?->ulid,
            ready: $type->form?->published_version_id !== null && $type->workflow?->published_version_id !== null,
        );
    }
}
