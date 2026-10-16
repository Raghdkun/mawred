<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Support;

/**
 * Rotating kiosk codes (D70): an HMAC of the kiosk and a 20-second time slot, so a code is only
 * good while it is on screen (the current or the previous slot, for scanning lag). Pure apart
 * from the time passed in.
 */
final class KioskCodes
{
    public const int SLOT_SECONDS = 20;

    public static function code(string $kioskUlid, string $secret, int $unixTime): string
    {
        $slot = intdiv($unixTime, self::SLOT_SECONDS);

        return $kioskUlid.'.'.$slot.'.'.substr(hash_hmac('sha256', $kioskUlid.'|'.$slot, $secret), 0, 24);
    }

    /** The kiosk ULID if the code is genuine and current, else null. */
    public static function verify(string $code, callable $secretFor, int $unixTime): ?string
    {
        $parts = explode('.', $code);

        if (count($parts) !== 3 || ! ctype_digit($parts[1])) {
            return null;
        }

        [$ulid, $slot, $mac] = $parts;
        $current = intdiv($unixTime, self::SLOT_SECONDS);

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
