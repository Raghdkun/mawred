<?php

declare(strict_types=1);

namespace App\Domain\People\Support;

use App\Domain\People\Enums\CustomFieldType;
use App\Domain\People\Models\CustomFieldDefinition;
use App\Support\Phone\Phones;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Validates and normalises one custom field value by type. Returns [value, error|null].
 */
final class CustomFieldValues
{
    /**
     * @return array{0: mixed, 1: string|null}
     */
    public static function normalise(CustomFieldDefinition $definition, mixed $value, ?string $phoneRegion = null): array
    {
        if (in_array($value, [null, '', []], true)) {
            return [null, $definition->is_required ? self::message('This field is required.') : null];
        }

        return match ($definition->type) {
            CustomFieldType::Text => self::text($value, 200),
            CustomFieldType::Textarea => self::text($value, 2000),
            CustomFieldType::Number => is_numeric($value) ? [$value + 0, null] : [null, self::message('Enter a number.')],
            CustomFieldType::Date => self::date($value),
            CustomFieldType::Boolean => is_bool($value) || in_array($value, [0, 1, '0', '1', 'true', 'false'], true)
                ? [filter_var($value, FILTER_VALIDATE_BOOLEAN), null]
                : [null, self::message('Choose yes or no.')],
            CustomFieldType::Email => is_string($value) && filter_var(trim($value), FILTER_VALIDATE_EMAIL) !== false
                ? [mb_strtolower(trim($value)), null]
                : [null, self::message('Enter a valid email address.')],
            CustomFieldType::Url => is_string($value) && filter_var(trim($value), FILTER_VALIDATE_URL) !== false && preg_match('#^https?://#i', trim($value)) === 1
                ? [trim($value), null]
                : [null, self::message('Enter a valid web address.')],
            CustomFieldType::Phone => self::phone($value, $phoneRegion),
            CustomFieldType::Select => is_string($value) && in_array($value, $definition->optionValues(), true)
                ? [$value, null]
                : [null, self::message('Choose one of the options.')],
            CustomFieldType::MultiSelect => self::multi($definition, $value),
        };
    }

    /**
     * @return array{0: mixed, 1: string|null}
     */
    private static function text(mixed $value, int $max): array
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return [null, self::message('Enter text.')];
        }

        $text = trim((string) $value);

        return mb_strlen($text) > $max ? [null, self::message('Keep this under :max characters.', ['max' => $max])] : [$text, null];
    }

    /**
     * @return array{0: mixed, 1: string|null}
     */
    private static function date(mixed $value): array
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return [null, self::message('Enter a date.')];
        }

        try {
            return [CarbonImmutable::createFromFormat('!Y-m-d', $value)?->toDateString(), null];
        } catch (Throwable) {
            return [null, self::message('Enter a date.')];
        }
    }

    /**
     * @return array{0: mixed, 1: string|null}
     */
    private static function phone(mixed $value, ?string $region): array
    {
        $e164 = is_string($value) ? Phones::toE164($value, $region) : false;

        return is_string($e164) ? [$e164, null] : [null, self::message('Enter a valid phone number.')];
    }

    /**
     * @return array{0: mixed, 1: string|null}
     */
    private static function multi(CustomFieldDefinition $definition, mixed $value): array
    {
        $values = is_array($value) ? array_values(array_unique(array_filter($value, is_string(...)))) : [];

        if ($values === [] || array_diff($values, $definition->optionValues()) !== []) {
            return [null, self::message('Choose from the options.')];
        }

        return [$values, null];
    }

    /**
     * @param  array<string, int|string>  $replace
     */
    private static function message(string $key, array $replace = []): string
    {
        $message = __($key, $replace);

        return is_string($message) ? $message : $key;
    }
}
