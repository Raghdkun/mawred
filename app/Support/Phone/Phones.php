<?php

declare(strict_types=1);

namespace App\Support\Phone;

use Propaganistas\LaravelPhone\PhoneNumber;
use Throwable;

/**
 * Phone numbers are stored as E.164. Local formats are read in the context of a region (the
 * branch's phone region), so "050 123 4567" in a Saudi branch becomes +966501234567.
 */
final class Phones
{
    /**
     * @return string|false|null null for empty input, false when the number is not valid
     */
    public static function toE164(?string $value, ?string $region): string|false|null
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            $number = new PhoneNumber(trim($value), $region === null ? [] : [mb_strtoupper($region)]);

            return $number->isValid() ? $number->formatE164() : false;
        } catch (Throwable) {
            return false;
        }
    }
}
