<?php

declare(strict_types=1);

namespace App\Support\Captcha;

use AltchaOrg\Altcha\Algorithm\Pbkdf2;
use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\CreateChallengeOptions;
use AltchaOrg\Altcha\Payload;
use AltchaOrg\Altcha\VerifySolutionOptions;
use Illuminate\Contracts\Cache\Repository as Cache;
use InvalidArgumentException;

/**
 * ALTCHA proof-of-work for public forms (D10, D53). The HMAC key is derived from APP_KEY until
 * the credential vault ships (Phase 4). Each solved challenge is accepted once.
 */
final readonly class AltchaGuard
{
    public const int TTL_SECONDS = 600;

    public function __construct(private Cache $cache) {}

    /**
     * @return array<string, mixed>
     */
    public function challenge(): array
    {
        return $this->altcha()->createChallenge(new CreateChallengeOptions(
            algorithm: new Pbkdf2,
            cost: $this->setting('cost', 5000),
            counter: random_int($this->setting('counter_min', 5000), $this->setting('counter_max', 10000)),
            expiresAt: time() + self::TTL_SECONDS,
        ))->toArray();
    }

    /** Verifies the widget's base64 payload; false when invalid, expired or already used. */
    public function verify(?string $payload): bool
    {
        if ($payload === null || $payload === '' || strlen($payload) > 8192) {
            return false;
        }

        $decoded = json_decode((string) base64_decode($payload, true), true);

        if (! is_array($decoded)) {
            return false;
        }

        $fields = [];

        foreach ($decoded as $name => $value) {
            if (is_string($name)) {
                $fields[$name] = $value;
            }
        }

        try {
            $parsed = Payload::fromArray($fields);
            $result = $this->altcha()->verifySolution(new VerifySolutionOptions(payload: $parsed, algorithm: new Pbkdf2));
        } catch (InvalidArgumentException) {
            return false;
        }

        if (! $result->verified) {
            return false;
        }

        // One use per challenge: replaying a solved payload is refused.
        return $this->cache->add('altcha:used:'.hash('sha256', (string) $parsed->challenge->signature), true, self::TTL_SECONDS);
    }

    /** Work factor (services.altcha): the library's recommended values unless configured. */
    private function setting(string $name, int $default): int
    {
        $value = config("services.altcha.{$name}");

        return is_numeric($value) ? max(1, (int) $value) : $default;
    }

    private function altcha(): Altcha
    {
        $key = config('app.key');
        $secret = hash_hmac('sha256', 'mawred.altcha.v1', is_string($key) ? $key : '');

        return new Altcha(hmacSignatureSecret: $secret, hmacKeySignatureSecret: hash('sha256', $secret.'.keys'));
    }
}
