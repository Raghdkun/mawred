<?php

declare(strict_types=1);

namespace App\Domain\Access\Data;

use App\Domain\Access\Models\UserSession;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class UserSessionData extends Data
{
    public function __construct(
        public string $id,
        public ?string $ip,
        public string $device,
        public string $lastSeenAt,
        public string $startedAt,
        public bool $current,
    ) {}

    public static function fromSession(UserSession $session, ?string $currentUlid): self
    {
        return new self(
            id: $session->ulid,
            ip: $session->ip,
            device: self::describe($session->user_agent),
            lastSeenAt: $session->last_seen_at->toIso8601String(),
            startedAt: $session->created_at?->toIso8601String() ?? $session->last_seen_at->toIso8601String(),
            current: $session->ulid === $currentUlid,
        );
    }

    /** A short "Browser · OS" label from the user agent. */
    private static function describe(?string $agent): string
    {
        if ($agent === null || $agent === '') {
            return 'Unknown device';
        }

        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Safari/') => 'Safari',
            default => 'Browser',
        };

        $os = match (true) {
            str_contains($agent, 'iPhone') || str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Mac OS X') => 'macOS',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Linux') => 'Linux',
            default => '',
        };

        return trim($browser.' · '.$os, ' ·');
    }
}
