<?php

declare(strict_types=1);

namespace App\Domain\Audit\Support;

use App\Support\Actors\Actor;
use App\Support\Actors\ActorType;
use App\Support\Actors\Channel;
use Illuminate\Support\Facades\Context;

/**
 * Request/job-scoped audit context (channel, actor type, impersonator, client) stored in Laravel
 * Context so it flows into queued jobs and every activity row.
 */
final class AuditContext
{
    public static function forRequest(Channel $channel, ?string $ip, ?string $userAgent, ?int $impersonatorId): void
    {
        Context::addHidden('audit', [
            'channel' => $channel->value,
            'actor_type' => ActorType::User->value,
            'ip' => $ip,
            'user_agent_hash' => $userAgent !== null && $userAgent !== '' ? hash('sha256', $userAgent) : null,
            'impersonator_id' => $impersonatorId,
        ]);
    }

    public static function forActor(Actor $actor): void
    {
        Context::addHidden('audit', [
            ...self::current(),
            'channel' => $actor->channel->value,
            'actor_type' => $actor->type->value,
            'impersonator_id' => $actor->impersonator?->id,
        ]);
    }

    /**
     * @return array{channel?: string, actor_type?: string, ip?: string|null, user_agent_hash?: string|null, impersonator_id?: int|null}
     */
    public static function current(): array
    {
        $audit = Context::getHidden('audit');

        if (! is_array($audit)) {
            return [];
        }

        $context = [];

        foreach (['channel', 'actor_type'] as $key) {
            if (isset($audit[$key]) && is_string($audit[$key])) {
                $context[$key] = $audit[$key];
            }
        }

        foreach (['ip', 'user_agent_hash'] as $key) {
            if (array_key_exists($key, $audit)) {
                $context[$key] = is_string($audit[$key]) ? $audit[$key] : null;
            }
        }

        if (array_key_exists('impersonator_id', $audit)) {
            $context['impersonator_id'] = is_int($audit['impersonator_id']) ? $audit['impersonator_id'] : null;
        }

        return $context;
    }
}
