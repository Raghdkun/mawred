<?php

declare(strict_types=1);

namespace App\Domain\Training\Support;

use App\Domain\Training\Models\TrainingSession;
use App\Support\Crypto\RotatingCodes;

/**
 * The rotating check-in code a trainer shows at a session (D101): good for 30 seconds (and the
 * previous slot, for scanning lag). Learners scan it as a link to the check-in page.
 */
final class CheckInCodes
{
    public const int SLOT_SECONDS = 30;

    public static function code(TrainingSession $session, int $unixTime): string
    {
        return RotatingCodes::code($session->ulid, $session->checkin_secret, $unixTime, self::SLOT_SECONDS);
    }

    /** The session the code belongs to, if it's genuine and current. */
    public static function session(string $code, int $unixTime): ?TrainingSession
    {
        $ulid = RotatingCodes::verify($code, function (string $ulid): ?string {
            $session = TrainingSession::query()->where('ulid', $ulid)->first();

            return $session?->checkin_secret;
        }, $unixTime, self::SLOT_SECONDS);

        return $ulid === null ? null : TrainingSession::query()->where('ulid', $ulid)->first();
    }

    public static function url(string $code): string
    {
        return route('training.check-in', ['code' => $code]);
    }
}
