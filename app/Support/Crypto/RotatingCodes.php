<?php

declare(strict_types=1);

namespace App\Support\Crypto;

/**
 * Codes that rotate with time: an HMAC of a record and a time slot, so a code is only good
 * while it is on screen (the current or the previous slot, for scanning lag). Pure apart from
 * the time passed in. Used by attendance kiosks (D70) and training check-in (D101).
 */
final class RotatingCodes
{
    public static function code(string $ulid, string $secret, int $unixTime, int $slotSeconds): string
    {
        $slot = intdiv($unixTime, $slotSeconds);

        return $ulid.'.'.$slot.'.'.substr(hash_hmac('sha256', $ulid.'|'.$slot, $secret), 0, 24);
    }

    /**
     * The record's ULID if the code is genuine and current, else null.
     *
     * @param  callable(string): ?string  $secretFor
     */
    public static function verify(string $code, callable $secretFor, int $unixTime, int $slotSeconds): ?string
    {
        $parts = explode('.', $code);

        if (count($parts) !== 3 || ! ctype_digit($parts[1])) {
            return null;
        }

        [$ulid, $slot, $mac] = $parts;
        $current = intdiv($unixTime, $slotSeconds);

        if (! in_array((int) $slot, [$current, $current - 1], true)) {
            return null;
        }

        $secret = $secretFor($ulid);

        if (! is_string($secret)) {
            return null;
        }

        return hash_equals(substr(hash_hmac('sha256', $ulid.'|'.$slot, $secret), 0, 24), $mac) ? $ulid : null;
    }
}
