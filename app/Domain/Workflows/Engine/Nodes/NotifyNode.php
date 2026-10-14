<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Engine\Nodes;

use App\Domain\Workflows\Engine\AssigneeResolver;
use App\Domain\Workflows\Engine\Graph;
use App\Domain\Workflows\Engine\NodeHandler;
use App\Domain\Workflows\Engine\NodeResult;
use App\Domain\Workflows\Engine\StepContext;
use App\Domain\Workflows\Events\WorkflowNotificationRequested;

/**
 * Sends a message (in-app, email, or WhatsApp once Phase 4 ships) to people resolved like
 * assignees. Text uses SafeTemplate variables such as `{{ request.number }}`.
 *
 * Config: {channel, to: spec, subject: {en, ar}, body: {en, ar}}
 */
final readonly class NotifyNode implements NodeHandler
{
    public const array CHANNELS = ['in_app', 'email', 'whatsapp'];

    public function __construct(private AssigneeResolver $resolver) {}

    public function type(): string
    {
        return 'notify';
    }

    public function validate(array $config, Graph $graph, string $nodeId): array
    {
        $problems = AssigneeSpec::problems($config['to'] ?? null);

        if (! in_array($config['channel'] ?? null, self::CHANNELS, true)) {
            $problems[] = __('Choose a channel.');
        }

        if (! is_array($config['body'] ?? null) || ! is_string($config['body']['en'] ?? null) || $config['body']['en'] === '') {
            $problems[] = __('Write the message.');
        }

        return $problems;
    }

    public function handle(StepContext $context): NodeResult
    {
        $spec = is_array($context->config['to'] ?? null) ? $context->config['to'] : [];
        $users = $this->resolver->resolve($spec, $context->instance);
        $channel = is_string($context->config['channel'] ?? null) ? $context->config['channel'] : 'in_app';

        if (! $context->simulating && $users !== []) {
            event(new WorkflowNotificationRequested($context->step, $channel, $users, $this->text($context->config['subject'] ?? null), $this->text($context->config['body'] ?? null)));
        }

        return NodeResult::completed(['channel' => $channel, 'recipients' => $users]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function handles(array $config): ?array
    {
        return null;
    }

    /**
     * @return array<string, string>
     */
    private function text(mixed $value): array
    {
        $text = [];

        foreach (is_array($value) ? $value : [] as $locale => $string) {
            if (is_string($locale) && is_string($string)) {
                $text[$locale] = $string;
            }
        }

        return $text;
    }
}
