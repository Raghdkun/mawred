<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Engine\Nodes;

use App\Domain\Workflows\Engine\Graph;
use App\Domain\Workflows\Engine\NodeHandler;
use App\Domain\Workflows\Engine\NodeResult;
use App\Domain\Workflows\Engine\RecordUpdate;
use App\Domain\Workflows\Engine\RecordUpdates;
use App\Domain\Workflows\Engine\StepContext;
use Throwable;

/**
 * Applies the approved change through the owning domain's Action (e.g. a profile change).
 * Actions whose module hasn't shipped yet are refused on publish (D55).
 *
 * Config: {action}
 */
final readonly class UpdateRecordNode implements NodeHandler
{
    public function __construct(private RecordUpdates $updates) {}

    public function type(): string
    {
        return 'update_record';
    }

    public function validate(array $config, Graph $graph, string $nodeId): array
    {
        $update = is_string($config['action'] ?? null) ? $this->updates->find($config['action']) : null;

        if (! $update instanceof RecordUpdate) {
            return [__('Choose what to update.')];
        }

        return $update->availableInPhase() > 0 ? [__('This update arrives in a later release.')] : [];
    }

    public function handle(StepContext $context): NodeResult
    {
        $update = is_string($context->config['action'] ?? null) ? $this->updates->find($context->config['action']) : null;

        if (! $update instanceof RecordUpdate || $update->availableInPhase() > 0) {
            return NodeResult::failed(__('Choose what to update.'), retryable: false);
        }

        if ($context->simulating) {
            return NodeResult::completed(['action' => $update->key(), 'simulated' => true]);
        }

        try {
            $update->apply($context);
        } catch (Throwable $e) {
            return NodeResult::failed($e->getMessage());
        }

        return NodeResult::completed(['action' => $update->key()]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function handles(array $config): ?array
    {
        return null;
    }
}
