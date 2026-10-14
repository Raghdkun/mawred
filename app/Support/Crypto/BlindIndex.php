<?php

declare(strict_types=1);

namespace App\Support\Crypto;

use RuntimeException;

/**
 * Deterministic HMAC of a normalised value so encrypted columns can still be matched exactly
 * (e.g. "import by national ID"), without storing anything reversible (ADR-0007).
 */
final class BlindIndex
{
    public static function of(?string $value, string $purpose): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalised = mb_strtoupper((string) preg_replace('/[\s\-_.\/]+/u', '', $value));

        if ($normalised === '') {
            return null;
        }

        return hash_hmac('sha256', "{$purpose}|{$normalised}", self::key());
    }

    private static function key(): string
    {
        $key = config('app.blind_index_key');

        if (! is_string($key) || strlen($key) < 32) {
            throw new RuntimeException('BLIND_INDEX_KEY must be set to at least 32 characters.');
        }

        return $key;
    }
}
