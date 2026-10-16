<?php

declare(strict_types=1);

namespace App\Domain\Calendar\Support;

use App\Domain\Calendar\Enums\Response;

/**
 * One-tap meeting replies (D75) from email and WhatsApp: the participant, the reply and an HMAC
 * keyed by the app key. Stateless; replying again just changes the answer.
 */
final class ReplyTokens
{
    private const array CODES = ['a' => Response::Accepted, 't' => Response::Tentative, 'd' => Response::Declined];

    public static function make(string $participantUlid, Response $response, string $key): string
    {
        $code = (string) array_search($response, self::CODES, true);

        return $participantUlid.'.'.$code.'.'.self::mac($participantUlid, $code, $key);
    }

    /**
     * @return array{0: string, 1: Response}|null participant ULID and reply
     */
    public static function verify(string $token, string $key): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3 || ! isset(self::CODES[$parts[1]]) || strlen($parts[0]) !== 26) {
            return null;
        }

        return hash_equals(self::mac($parts[0], $parts[1], $key), $parts[2]) ? [$parts[0], self::CODES[$parts[1]]] : null;
    }

    public static function looksLikeOne(string $token): bool
    {
        return preg_match('/^[0-9A-Za-z]{26}\.[atd]\.[0-9a-f]{24}$/', $token) === 1;
    }

    private static function mac(string $ulid, string $code, string $key): string
    {
        return substr(hash_hmac('sha256', 'rsvp|'.$ulid.'|'.$code, $key), 0, 24);
    }
}
