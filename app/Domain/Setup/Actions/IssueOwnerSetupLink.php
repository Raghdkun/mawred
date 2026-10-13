<?php

declare(strict_types=1);

namespace App\Domain\Setup\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Setup\Support\Settings;
use Illuminate\Support\Str;

/**
 * One-time, 24-hour link that lets the first Super Admin set a password (D4). Only the token's
 * hash is stored; issuing a new link invalidates the previous one.
 */
final readonly class IssueOwnerSetupLink
{
    public const int TTL_HOURS = 24;

    public function __construct(private Settings $settings) {}

    public function handle(User $owner): string
    {
        $token = Str::random(48);

        $this->settings->set('setup.claim', [
            'user_id' => $owner->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addHours(self::TTL_HOURS)->toIso8601String(),
        ]);

        return route('setup.claim.show', ['token' => $token]);
    }

    public function resolve(string $token): ?User
    {
        $claim = $this->settings->get('setup.claim');

        if (! is_array($claim)
            || ! is_string($claim['token_hash'] ?? null)
            || ! is_string($claim['expires_at'] ?? null)
            || ! hash_equals($claim['token_hash'], hash('sha256', $token))
            || now()->greaterThan($claim['expires_at'])) {
            return null;
        }

        $userId = $claim['user_id'] ?? null;

        return is_int($userId) ? User::query()->find($userId) : null;
    }

    public function consume(): void
    {
        $this->settings->forget('setup.claim');
    }
}
