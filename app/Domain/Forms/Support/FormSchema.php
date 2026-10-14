<?php

declare(strict_types=1);

namespace App\Domain\Forms\Support;

use App\Domain\Forms\Enums\FieldType;
use App\Support\Rules\Rules;

/**
 * Mawred Form Schema v1 (`mawred.form/v1`, ADR-0008): pages → sections → fields, as data. The
 * contract is documented in `resources/schemas/form.v1.json`; this class enforces it on save and
 * returns every problem keyed by its path (`pages.0.sections.1.fields.2.label.en`).
 */
final class FormSchema
{
    public const string FORMAT = 'mawred.form/v1';

    public const string KEY_PATTERN = '/^[a-z][a-z0-9_]{0,39}$/';

    public const array FILE_KINDS = [
        'pdf' => ['application/pdf'],
        'image' => ['image/jpeg', 'image/png', 'image/webp', 'image/heic'],
        'word' => ['application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'excel' => ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/csv'],
    ];

    public const int MAX_FIELDS = 150;

    /**
     * An empty form: one page, one section, no fields.
     *
     * @return array<string, mixed>
     */
    public static function blank(): array
    {
        return ['format' => self::FORMAT, 'pages' => [['key' => 'page_1', 'title' => ['en' => ''], 'sections' => [['key' => 'section_1', 'title' => null, 'fields' => []]]]]];
    }

    /**
     * @param  array<mixed>  $schema
     * @return array<string, list<string>> path => problems; empty when valid
     */
    public static function problems(array $schema, bool $public = false): array
    {
        $errors = [];
        $add = function (string $path, string $message) use (&$errors): void {
            $errors[$path][] = $message;
        };

        if (($schema['format'] ?? null) !== self::FORMAT) {
            $add('format', __('This is not a Mawred form.'));
        }

        $pages = $schema['pages'] ?? null;

        if (! is_array($pages) || ! array_is_list($pages) || $pages === [] || count($pages) > 20) {
            $add('pages', __('A form needs between 1 and 20 pages.'));

            return $errors;
        }

        $fieldKeys = [];
        $sectionKeys = [];
        $pageKeys = [];
        $fieldCount = 0;

        foreach ($pages as $p => $page) {
            $at = "pages.{$p}";

            if (! is_array($page)) {
                $add($at, __('Invalid page.'));

                continue;
            }

            self::checkKey($page['key'] ?? null, $pageKeys, "{$at}.key", $add);
            self::checkText($page['title'] ?? null, "{$at}.title", $add, required: count($pages) > 1, max: 120);

            $sections = $page['sections'] ?? null;

            if (! is_array($sections) || ! array_is_list($sections) || $sections === []) {
                $add("{$at}.sections", __('A page needs at least one section.'));

                continue;
            }

            foreach ($sections as $s => $section) {
                $sat = "{$at}.sections.{$s}";

                if (! is_array($section)) {
                    $add($sat, __('Invalid section.'));

                    continue;
                }

                self::checkKey($section['key'] ?? null, $sectionKeys, "{$sat}.key", $add);
                self::checkText($section['title'] ?? null, "{$sat}.title", $add, max: 120);
                self::checkText($section['description'] ?? null, "{$sat}.description", $add, max: 500);

                $fields = $section['fields'] ?? null;

                if (! is_array($fields) || ! array_is_list($fields)) {
                    $add("{$sat}.fields", __('Invalid list of fields.'));

                    continue;
                }

                foreach ($fields as $f => $field) {
                    $fieldCount++;
                    self::checkField($field, "{$sat}.fields.{$f}", $fieldKeys, $add, $public);
                }
            }
        }

        if ($fieldCount > self::MAX_FIELDS) {
            $add('pages', __('A form can have at most :count fields.', ['count' => self::MAX_FIELDS]));
        }

        // Rules may only read fields that exist in this form.
        foreach (self::fields($schema) as $path => $field) {
            foreach (['required', 'visible', 'calc', 'default'] as $part) {
                foreach (Rules::refs($field[$part] ?? null) as $ref) {
                    if (str_starts_with($ref, 'field.') && ! in_array(explode('.', $ref)[1] ?? '', $fieldKeys, true)) {
                        $add("{$path}.{$part}", __('This rule uses a field that does not exist: :field.', ['field' => substr($ref, 6)]));
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Every field with its path, in order.
     *
     * @param  array<mixed>  $schema
     * @return array<string, array<string, mixed>>
     */
    public static function fields(array $schema): array
    {
        $fields = [];

        foreach (is_array($schema['pages'] ?? null) ? $schema['pages'] : [] as $p => $page) {
            foreach (is_array($page) && is_array($page['sections'] ?? null) ? $page['sections'] : [] as $s => $section) {
                foreach (is_array($section) && is_array($section['fields'] ?? null) ? $section['fields'] : [] as $f => $field) {
                    if (is_array($field)) {
                        /** @var array<string, mixed> $field */
                        $fields["pages.{$p}.sections.{$s}.fields.{$f}"] = $field;
                    }
                }
            }
        }

        return $fields;
    }

    /**
     * Field definitions keyed by field key.
     *
     * @param  array<mixed>  $schema
     * @return array<string, array<string, mixed>>
     */
    public static function fieldsByKey(array $schema): array
    {
        $byKey = [];

        foreach (self::fields($schema) as $field) {
            if (is_string($field['key'] ?? null)) {
                $byKey[$field['key']] = $field;
            }
        }

        return $byKey;
    }

    /**
     * @param  list<string>  $seen
     * @param  callable(string, string): void  $add
     */
    private static function checkKey(mixed $key, array &$seen, string $path, callable $add): void
    {
        if (! is_string($key) || preg_match(self::KEY_PATTERN, $key) !== 1) {
            $add($path, __('Use lowercase letters, digits and underscores, starting with a letter.'));

            return;
        }

        if (in_array($key, $seen, true)) {
            $add($path, __('This key is already used in the form.'));
        }

        $seen[] = $key;
    }

    /**
     * @param  callable(string, string): void  $add
     */
    private static function checkText(mixed $text, string $path, callable $add, bool $required = false, int $max = 255): void
    {
        if ($text === null && ! $required) {
            return;
        }

        if (! is_array($text) || array_diff(array_keys($text), ['en', 'ar']) !== []) {
            $add($path, __('Expected English and Arabic text.'));

            return;
        }

        foreach ($text as $locale => $value) {
            if ($value !== null && (! is_string($value) || mb_strlen($value) > $max)) {
                $add("{$path}.{$locale}", __('Keep this under :max characters.', ['max' => $max]));
            }
        }

        if ($required && trim(FieldSpec::string($text['en'] ?? null)) === '') {
            $add("{$path}.en", __('Enter the English text.'));
        }
    }

    /**
     * @param  list<string>  $keys
     * @param  callable(string, string): void  $add
     */
    private static function checkField(mixed $field, string $at, array &$keys, callable $add, bool $public): void
    {
        if (! is_array($field)) {
            $add($at, __('Invalid field.'));

            return;
        }

        $type = is_string($field['type'] ?? null) ? FieldType::tryFrom($field['type']) : null;

        if (! $type instanceof FieldType) {
            $add("{$at}.type", __('Unknown field type.'));

            return;
        }

        self::checkKey($field['key'] ?? null, $keys, "{$at}.key", $add);
        self::checkText($field['label'] ?? null, "{$at}.label", $add, required: true, max: 200);
        self::checkText($field['help'] ?? null, "{$at}.help", $add, max: 1000);
        self::checkText($field['placeholder'] ?? null, "{$at}.placeholder", $add, max: 120);

        if ($public && $type->isPicker()) {
            $add("{$at}.type", __('Public forms cannot use directory pickers.'));
        }

        foreach (['required', 'visible', 'calc'] as $part) {
            if (array_key_exists($part, $field) && ! is_bool($field[$part]) && $field[$part] !== null) {
                foreach (Rules::check($field[$part], $public ? ['field', 'ctx'] : ['field', 'requester', 'ctx']) as $problem) {
                    $add("{$at}.{$part}", $problem);
                }
            }
        }

        if (array_key_exists('default', $field) && is_array($field['default']) && ! array_is_list($field['default'])) {
            foreach (Rules::check($field['default'], $public ? ['ctx'] : ['requester', 'ctx']) as $problem) {
                $add("{$at}.default", $problem);
            }
        }

        if ($type === FieldType::Calculated && ! array_key_exists('calc', $field)) {
            $add("{$at}.calc", __('A calculated field needs a calculation.'));
        }

        $validation = is_array($field['validation'] ?? null) ? $field['validation'] : [];
        $int = function (string $name, int $min, int $max) use ($validation, $at, $add): void {
            $value = $validation[$name] ?? null;

            if ($value !== null && (! is_int($value) || $value < $min || $value > $max)) {
                $add("{$at}.validation.{$name}", __('Enter a whole number from :min to :max.', ['min' => $min, 'max' => $max]));
            }
        };

        $limits = match ($type) {
            FieldType::Text => ['minLength' => 255, 'maxLength' => 255],
            FieldType::Textarea => ['minLength' => 5000, 'maxLength' => 5000],
            FieldType::Multiselect, FieldType::Checkboxes => ['minItems' => 200, 'maxItems' => 200],
            FieldType::DateRange => ['maxDays' => 3660],
            FieldType::File => ['maxFiles' => 10, 'maxSizeKb' => 20480],
            default => [],
        };

        foreach ($limits as $name => $max) {
            $int($name, str_starts_with($name, 'min') ? 0 : 1, $max);
        }

        if ($type === FieldType::Number || $type === FieldType::Money) {
            self::checkNumbers($validation, $at, $add);
        }

        if ($type === FieldType::File) {
            self::checkAccept($validation['accept'] ?? null, "{$at}.validation.accept", $add);
        }

        if (in_array($type, [FieldType::Date, FieldType::DateRange], true)) {
            foreach (['min', 'max'] as $bound) {
                if (isset($validation[$bound]) && ! is_string($validation[$bound])) {
                    foreach (Rules::check($validation[$bound], ['ctx']) as $problem) {
                        $add("{$at}.validation.{$bound}", $problem);
                    }
                }
            }
        }

        if ($type === FieldType::Money && isset($field['currency']) && (! is_string($field['currency']) || preg_match('/^[A-Z]{3}$/', $field['currency']) !== 1)) {
            $add("{$at}.currency", __('Use a three-letter currency code.'));
        }

        if ($type === FieldType::Money && $public && ! isset($field['currency'])) {
            $add("{$at}.currency", __('Public forms need a fixed currency.'));
        }

        if ($type->hasOptions()) {
            self::checkOptions($field['options'] ?? null, "{$at}.options", $add);
        }
    }

    /**
     * @param  array<mixed>  $validation
     * @param  callable(string, string): void  $add
     */
    private static function checkNumbers(array $validation, string $at, callable $add): void
    {
        foreach (['min', 'max'] as $bound) {
            if (isset($validation[$bound]) && ! is_int($validation[$bound]) && ! is_float($validation[$bound])) {
                $add("{$at}.validation.{$bound}", __('Enter a number.'));
            }
        }

        if (isset($validation['min'], $validation['max']) && is_numeric($validation['min']) && is_numeric($validation['max']) && $validation['min'] > $validation['max']) {
            $add("{$at}.validation.max", __('The maximum must be at least the minimum.'));
        }
    }

    /**
     * @param  callable(string, string): void  $add
     */
    private static function checkAccept(mixed $accept, string $path, callable $add): void
    {
        if ($accept !== null && (! is_array($accept) || $accept === [] || array_any($accept, fn (mixed $kind): bool => ! is_string($kind) || ! isset(self::FILE_KINDS[$kind])))) {
            $add($path, __('Choose from PDF, images, Word and Excel files.'));
        }
    }

    /**
     * @param  callable(string, string): void  $add
     */
    private static function checkOptions(mixed $options, string $path, callable $add): void
    {
        if (! is_array($options) || ! array_is_list($options) || $options === [] || count($options) > 200) {
            $add($path, __('Add between 1 and 200 options.'));

            return;
        }

        $values = [];

        foreach ($options as $i => $option) {
            $value = is_array($option) ? ($option['value'] ?? null) : null;

            if (! is_string($value) || $value === '' || mb_strlen($value) > 80) {
                $add("{$path}.{$i}.value", __('Each option needs a value.'));
            } elseif (in_array($value, $values, true)) {
                $add("{$path}.{$i}.value", __('Option values must be unique.'));
            } else {
                $values[] = $value;
            }

            self::checkText(is_array($option) ? ($option['label'] ?? null) : null, "{$path}.{$i}.label", $add, required: true, max: 200);
        }
    }
}
