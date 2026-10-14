<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Engine\Nodes;

use App\Domain\Workflows\Engine\Graph;
use App\Domain\Workflows\Engine\NodeHandler;
use App\Domain\Workflows\Engine\NodeResult;
use App\Domain\Workflows\Engine\StepContext;
use App\Domain\Workflows\Models\WebhookEndpoint;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Posts the request to an external system (ADR-0009): HMAC-SHA256 signature with a timestamp,
 * the step ULID as `Idempotency-Key`, 10 s timeout, retried with backoff on failure.
 *
 * Config: {endpoint: <ulid>}
 */
final class WebhookNode implements NodeHandler
{
    public function type(): string
    {
        return 'webhook';
    }

    public function validate(array $config, Graph $graph, string $nodeId): array
    {
        $exists = is_string($config['endpoint'] ?? null) && WebhookEndpoint::query()->where('ulid', $config['endpoint'])->where('is_active', true)->exists();

        return $exists ? [] : [__('Choose a webhook endpoint.')];
    }

    public function handle(StepContext $context): NodeResult
    {
        $endpoint = is_string($context->config['endpoint'] ?? null) ? WebhookEndpoint::query()->where('ulid', $context->config['endpoint'])->first() : null;

        if (! $endpoint instanceof WebhookEndpoint || ! $endpoint->is_active) {
            return NodeResult::failed(__('Choose a webhook endpoint.'), retryable: false);
        }

        if ($context->simulating) {
            return NodeResult::completed(['endpoint' => $endpoint->ulid, 'simulated' => true]);
        }

        $body = (string) json_encode([
            'event' => 'workflow.step',
            'instance' => $context->instance->ulid,
            'step' => $context->step->ulid,
            'field' => $context->instance->context['field'] ?? [],
            'request' => $context->instance->context['request'] ?? null,
        ], JSON_UNESCAPED_UNICODE);
        $timestamp = (string) $context->now->getTimestamp();

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Idempotency-Key' => $context->step->ulid,
                    'X-Mawred-Signature' => 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$body, $endpoint->secret),
                ])
                ->withBody($body, 'application/json')
                ->post($endpoint->url);
        } catch (ConnectionException $e) {
            return NodeResult::failed($e->getMessage());
        }

        return $response->successful()
            ? NodeResult::completed(['endpoint' => $endpoint->ulid, 'status' => $response->status()])
            : NodeResult::failed(__('The webhook answered :status.', ['status' => $response->status()]));
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function handles(array $config): ?array
    {
        return null;
    }
}
