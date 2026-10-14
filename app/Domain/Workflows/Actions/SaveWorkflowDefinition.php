<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Actions;

use App\Domain\Organization\Support\ReferenceValues;
use App\Domain\Workflows\Engine\Graph;
use App\Domain\Workflows\Enums\VersionStatus;
use App\Domain\Workflows\Models\WorkflowDefinition;
use App\Support\Actors\Actor;
use Illuminate\Database\ConnectionInterface;

/**
 * Creates a workflow (with a minimal Start → End draft) or renames it. The code is fixed once created.
 */
final readonly class SaveWorkflowDefinition
{
    public function __construct(private ConnectionInterface $db) {}

    /**
     * @param  array<string, string|null>  $name
     * @param  array<string, string|null>  $description
     */
    public function handle(Actor $actor, string $code, array $name, array $description = [], ?string $category = null, ?WorkflowDefinition $definition = null): WorkflowDefinition
    {
        $definition ??= new WorkflowDefinition(['created_by' => $actor->user?->id]);
        $code = $definition->exists ? $definition->code : ReferenceValues::code($code);

        if (! $definition->exists) {
            ReferenceValues::assertCodeAvailable(WorkflowDefinition::class, $code);
        }

        return $this->db->transaction(function () use ($definition, $code, $name, $description, $category): WorkflowDefinition {
            $definition->setTranslations('name', ReferenceValues::translations($name));
            $definition->setTranslations('description', ReferenceValues::translations($description));
            $definition->fill(['code' => $code, 'category' => $category])->save();

            if (! $definition->versions()->exists()) {
                $definition->versions()->create(['number' => 1, 'status' => VersionStatus::Draft, 'graph' => self::blank()]);
            }

            return $definition;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public static function blank(): array
    {
        return [
            'format' => Graph::FORMAT,
            'nodes' => [['id' => 'start', 'type' => 'start', 'config' => (object) []], ['id' => 'end', 'type' => 'end', 'config' => ['outcome' => 'completed']]],
            'edges' => [['id' => 'e_start_end', 'source' => 'start', 'target' => 'end']],
        ];
    }
}
