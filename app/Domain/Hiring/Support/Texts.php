<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Typed reads of translatable attributes (spatie/laravel-translatable returns mixed): one
 * language with English as the fallback, or both languages for editors.
 */
final class Texts
{
    public static function one(?Model $model, string $attribute, ?string $locale = null): ?string
    {
        if (! $model instanceof Model || ! method_exists($model, 'getTranslation')) {
            return null;
        }

        $text = $model->getTranslation($attribute, $locale ?? app()->getLocale());

        return is_string($text) && $text !== '' ? $text : null;
    }

    /**
     * @return array{en: string, ar: string}
     */
    public static function pair(Model $model, string $attribute): array
    {
        $values = method_exists($model, 'getTranslations') ? $model->getTranslations($attribute) : [];
        $values = is_array($values) ? $values : [];
        $en = is_string($values['en'] ?? null) ? $values['en'] : '';
        $ar = is_string($values['ar'] ?? null) ? $values['ar'] : '';

        return ['en' => $en, 'ar' => $ar];
    }

    /** A `withCount` result (0 when it was not loaded). */
    public static function count(Model $model, string $attribute): int
    {
        // Strict models throw on attributes that were not selected, so look before reading.
        $value = $model->getAttributes()[$attribute] ?? null;

        return is_numeric($value) ? (int) $value : 0;
    }
}
