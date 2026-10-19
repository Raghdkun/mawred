<?php

declare(strict_types=1);

namespace App\Domain\Training\Support;

use App\Domain\Training\Models\Certificate;
use Illuminate\Support\Str;

/**
 * Certificate serials `MW-2026-7KQ4XZ9P` (D104): the year and eight characters from an alphabet
 * without look-alikes (no 0/O or 1/I/L), unguessable enough for a public verification page.
 */
final class CertificateSerials
{
    private const string ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    public static function next(int $year): string
    {
        do {
            $code = '';

            for ($i = 0; $i < 8; $i++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }

            $serial = "MW-{$year}-{$code}";
        } while (Certificate::query()->where('serial', $serial)->exists());

        return $serial;
    }

    public static function normalise(string $serial): string
    {
        return Str::upper(trim($serial));
    }
}
