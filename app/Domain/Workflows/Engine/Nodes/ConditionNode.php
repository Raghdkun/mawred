<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Engine\Nodes;

use App\Domain\Workflows\Engine\Graph;
use App\Domain\Workflows\Engine\NodeHandler;
use App\Domain\Workflows\Engine\NodeResult;
use App\Domain\Workflows\Engine\StepContext;
use App\Support\Rules\RuleError;
use App\Support\Rules\Rules;

/** Branches on a `mawred.rule/v1` rule over the form, requester and earlier decisions. */
final class ConditionNode implements NodeHandler
{
    public function type(): string
    {
        return 'condition';
    }

    public function validate(array $config, Graph $graph, string $nodeId): array
    {
        if (! array_key_exists('rule', $config)) {
            return [__('Add the condition to check.')];
        }

        return Rules::check($config['rule'], ['field', 'requester', 'ctx', 'steps']);
    }

    public function handle(StepContext $context): NodeResult
    {
        try {
            $result = Rules::truthy(Rules::evaluate($context->config['rule'] ?? false, $context->rulesContext()));
        } catch (RuleError $e) {
            return NodeResult::failed($e->getMessage(), retryable: false);
        }

        return NodeResult::completed(['result' => $result], [$result ? 'true' : 'false']);
    }

    public function handles(array $config): array
    {
        return ['true', 'false'];
    }
}
