<?php

declare(strict_types=1);

namespace App\Support\Actors;

use App\Domain\Access\Models\User;

/**
 * Who performs an Action (ADR-0002). Actions never read the authenticated user from the request;
 * the boundary (controller, job, webhook, signed link) builds an Actor and passes it in.
 */
final readonly class Actor
{
    private function __construct(
        public ActorType $type,
        public Channel $channel,
        public ?User $user = null,
        public ?string $reason = null,
        public ?User $impersonator = null,
    ) {}

    public static function user(User $user, Channel $channel = Channel::Web, ?User $impersonator = null): self
    {
        return new self(ActorType::User, $channel, $user, impersonator: $impersonator);
    }

    /**
     * An out-of-app decision made with a signed, single-use token bound to a user (ADR-0009).
     */
    public static function token(User $user, Channel $channel): self
    {
        return new self(ActorType::Token, $channel, $user);
    }

    public static function integration(string $integration, Channel $channel): self
    {
        return new self(ActorType::Integration, $channel, reason: $integration);
    }

    public static function system(string $reason): self
    {
        return new self(ActorType::System, Channel::System, reason: $reason);
    }

    public function isHuman(): bool
    {
        return $this->user instanceof User;
    }
}
