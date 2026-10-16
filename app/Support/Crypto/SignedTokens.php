<?php

declare(strict_types=1);

namespace App\Support\Crypto;

/**
 * Stateless links for people without an account (a candidate's booking, offer or privacy page):
 * the record's ULID and an HMAC of it for one purpose, keyed by the app key. A link for one
 * purpose never works for another.
 */
final class SignedTokens
{
    public static function make(string $purpose, string $ulid): string
    {
        return $ulid.'.'.self::mac($purpose, $ulid);
    }

    /** The ULID the token was made for, or null when it is not genuine. */
    public static function verify(string $purpose, string $token): ?string
    {
        $parts = explode('.', $token);

        if (count($parts) !== 2 || preg_match('/^[0-9a-z]{26}$/', $parts[0]) !== 1) {
            return null;
        }

        return hash_equals(self::mac($purpose, $parts[0]), $parts[1]) ? $parts[0] : null;
    }

    private static function mac(string $purpose, string $ulid): string
    {
        $key = config('app.key');

        return substr(hash_hmac('sha256', "signed|{$purpose}|{$ulid}", is_string($key) ? $key : ''), 0, 32);
    }
}
