<?php

declare(strict_types=1);

namespace App\Domain\Forms\Support;

use App\Domain\Forms\Enums\FieldType;

/**
 * Typed reads from a schema field, which is untrusted JSON until FormSchema has checked it.
 */
final class FieldSpec
{
    /**
     * @param  array<mixed>  $field
     */
    public static function type(array $field): ?FieldType
    {
        return is_string($field['type'] ?? null) ? FieldType::tryFrom($field['type']) : null;
    }

    /**
     * @param  array<mixed>  $field
     * @return array<mixed>
     */
    public static function validation(array $field): array
    {
        return is_array($field['validation'] ?? null) ? $field['validation'] : [];
    }

    /**
     * @param  array<mixed>  $values
     */
    public static function int(array $values, string $key, int $default): int
    {
        return is_int($values[$key] ?? null) ? $values[$key] : $default;
    }

    /**
     * A bilingual text in one locale, falling back to English, then to the given fallback.
     */
    public static function text(mixed $text, string $locale, string $fallback = ''): string
    {
        if (! is_array($text)) {
            return $fallback;
        }

        foreach ([$locale, 'en'] as $candidate) {
            if (is_string($text[$candidate] ?? null) && $text[$candidate] !== '') {
                return $text[$candidate];
            }
        }

        return $fallback;
    }

    public static function string(mixed $value, string $default = ''): string
    {
        return is_string($value) ? $value : $default;
    }
}
