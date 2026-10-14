<?php

declare(strict_types=1);

namespace App\Support\Search;

/**
 * Normalises text for search (ADR-0013): case-folds Latin, strips Arabic diacritics (tashkeel)
 * and tatweel, and unifies letter variants (أإآ→ا, ى→ي, ة→ه) plus Arabic-Indic digits, so
 * "أحمد" finds "احمد" and "٠١٢" finds "012".
 */
final class SearchText
{
    /**
     * @param  iterable<mixed>  $parts
     */
    public static function from(iterable $parts): string
    {
        $values = [];

        foreach ($parts as $part) {
            if (is_array($part)) {
                $values[] = self::from($part);
            } elseif (is_scalar($part) && (string) $part !== '') {
                $values[] = self::normalize((string) $part);
            }
        }

        return trim(implode(' ', array_unique(array_filter($values))));
    }

    public static function normalize(string $value): string
    {
        $value = mb_strtolower($value);
        $value = (string) preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $value);
        $value = strtr($value, [
            'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
            'ى' => 'ي', 'ة' => 'ه', 'ؤ' => 'و', 'ئ' => 'ي',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    /** An ILIKE pattern for a user's search term, with wildcards escaped. */
    public static function likePattern(string $term): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], self::normalize($term)).'%';
    }
}
