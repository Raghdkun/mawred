<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Engine;

use App\Domain\Workflows\Engine\Nodes\ApprovalNode;
use App\Domain\Workflows\Engine\Nodes\ConditionNode;
use App\Domain\Workflows\Engine\Nodes\DelayNode;
use App\Domain\Workflows\Engine\Nodes\EndNode;
use App\Domain\Workflows\Engine\Nodes\JoinNode;
use App\Domain\Workflows\Engine\Nodes\NotifyNode;
use App\Domain\Workflows\Engine\Nodes\SplitNode;
use App\Domain\Workflows\Engine\Nodes\StartNode;
use App\Domain\Workflows\Engine\Nodes\SubWorkflowNode;
use App\Domain\Workflows\Engine\Nodes\TaskNode;
use App\Domain\Workflows\Engine\Nodes\UpdateRecordNode;
use App\Domain\Workflows\Engine\Nodes\WebhookNode;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/** Node types by id. The builder's node palette mirrors these ids. */
final class NodeRegistry
{
    public const array TYPES = [
        'start' => StartNode::class,
        'end' => EndNode::class,
        'approval' => ApprovalNode::class,
        'task' => TaskNode::class,
        'condition' => ConditionNode::class,
        'split' => SplitNode::class,
        'join' => JoinNode::class,
        'notify' => NotifyNode::class,
        'delay' => DelayNode::class,
        'update_record' => UpdateRecordNode::class,
        'webhook' => WebhookNode::class,
        'subworkflow' => SubWorkflowNode::class,
    ];

    /** @var array<string, NodeHandler> */
    private array $handlers = [];

    public function __construct(private readonly Container $container) {}

    public function has(string $type): bool
    {
        return isset(self::TYPES[$type]);
    }

    public function get(string $type): NodeHandler
    {
        if (! isset(self::TYPES[$type])) {
            throw new InvalidArgumentException("Unknown workflow node type [{$type}].");
        }

        /** @var NodeHandler $handler */
        $handler = $this->handlers[$type] ??= $this->container->make(self::TYPES[$type]);

        return $handler;
    }
}
