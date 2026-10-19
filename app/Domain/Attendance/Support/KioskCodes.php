<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Support;

use App\Support\Crypto\RotatingCodes;

/**
 * Rotating kiosk codes (D70): a 20-second slot, so a code is only good while it is on screen.
 */
final class KioskCodes
{
    public const int SLOT_SECONDS = 20;

    public static function code(string $kioskUlid, string $secret, int $unixTime): string
    {
        return RotatingCodes::code($kioskUlid, $secret, $unixTime, self::SLOT_SECONDS);
    }

    /**
     * The kiosk ULID if the code is genuine and current, else null.
     *
     * @param  callable(string): ?string  $secretFor
     */
    public static function verify(string $code, callable $secretFor, int $unixTime): ?string
    {
        return RotatingCodes::verify($code, $secretFor, $unixTime, self::SLOT_SECONDS);
    }
}
