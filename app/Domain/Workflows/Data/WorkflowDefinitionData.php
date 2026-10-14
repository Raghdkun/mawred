<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Data;

use App\Domain\Workflows\Models\WorkflowDefinition;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A workflow in the list and the builder header (handoff 15). */
#[TypeScript]
final class WorkflowDefinitionData extends Data
{
    /**
     * @param  array<string, string>  $name
     * @param  array<string, string>  $description
     * @param  list<array<string, string>>  $requestTypes
     */
    public function __construct(
        public string $id,
        public string $code,
        public array $name,
        public array $description,
        public ?string $category,
        public ?int $publishedVersion,
        public bool $hasDraft,
        public int $openInstances,
        public array $requestTypes,
    ) {}

    /**
     * @param  list<array<string, string>>  $requestTypes
     */
    public static function fromDefinition(WorkflowDefinition $definition, ?int $publishedVersion, bool $hasDraft, int $openInstances, array $requestTypes): self
    {
        /** @var array<string, string> $name */
        $name = $definition->getTranslations('name');
        /** @var array<string, string> $description */
        $description = $definition->getTranslations('description');

        return new self(
            id: $definition->ulid,
            code: $definition->code,
            name: $name,
            description: $description,
            category: $definition->category,
            publishedVersion: $publishedVersion,
            hasDraft: $hasDraft,
            openInstances: $openInstances,
            requestTypes: $requestTypes,
        );
    }
}
