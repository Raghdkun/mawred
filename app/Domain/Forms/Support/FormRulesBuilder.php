<?php

declare(strict_types=1);

namespace App\Domain\Forms\Support;

use App\Domain\Forms\Enums\FieldType;
use App\Support\Rules\RuleError;
use App\Support\Rules\Rules;
use Closure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Server-side, authoritative validation of a submission against its form version (ADR-0008).
 * Hidden fields are dropped, calculated fields recomputed (client values for them are ignored),
 * conditional `required` evaluated, and values normalised for storage.
 */
final class FormRulesBuilder
{
    public const string PHONE_PATTERN = '/^\+[1-9]\d{6,14}$/';

    /**
     * @param  array<mixed>  $schema
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $context  requester + ctx (FormContext)
     * @param  (Closure(string, list<string>, array<string, mixed>): (list<array<string, mixed>>|string))|null  $files
     *                                                                                                                  resolves upload tokens for a field to stored file details, or returns an error message
     * @return array<string, mixed> clean values, by field key
     *
     * @throws ValidationException keyed by field key
     */
    public function validate(array $schema, array $input, array $context, ?Closure $files = null): array
    {
        $fields = FormSchema::fieldsByKey($schema);
        $resolved = FormResolution::resolve($fields, $input, $context);
        $values = $resolved['values'];
        $hidden = $resolved['hidden'];
        $scope = [...$context, 'field' => $values];

        $rules = [];
        $attributes = [];
        $locale = app()->getLocale();

        foreach ($fields as $key => $field) {
            $type = FieldSpec::type($field);

            if ($type?->acceptsInput() !== true || in_array($key, $hidden, true)) {
                continue;
            }

            $label = FieldSpec::text($field['label'] ?? null, $locale, $key);
            $attributes[$key] = $label;
            $required = FormResolution::isRequired($field, $scope);
            $validation = FieldSpec::validation($field);

            foreach ($this->rulesFor($type, $key, $field, $validation, $required, $scope, $files) as $path => $pathRules) {
                $rules[$path] = $pathRules;
                $attributes[$path] ??= $label;
            }
        }

        $data = array_filter($values, fn (string $key): bool => ! in_array($key, $hidden, true), ARRAY_FILTER_USE_KEY);
        $validator = Validator::make($data, $rules, [], $attributes);
        $validator->validate();

        $clean = [];

        foreach ($fields as $key => $field) {
            $type = FieldSpec::type($field);

            if ($type?->hasValue() !== true || in_array($key, $hidden, true)) {
                continue;
            }

            $clean[$key] = $type === FieldType::File && $files instanceof Closure && is_array($data[$key] ?? null) && $data[$key] !== []
                ? $files($key, array_values(array_filter($data[$key], is_string(...))), $field)
                : $this->normalize($type, $data[$key] ?? null);
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<mixed>  $v  validation options
     * @param  array<string, mixed>  $scope
     * @return array<string, list<mixed>>
     */
    private function rulesFor(FieldType $type, string $key, array $field, array $v, bool $required, array $scope, ?Closure $files): array
    {
        $base = $required ? ['required'] : ['nullable'];
        $options = array_values(array_filter(array_map(fn (mixed $o): mixed => is_array($o) ? ($o['value'] ?? null) : null, is_array($field['options'] ?? null) ? $field['options'] : []), is_string(...)));
        $min = $this->bound($v['min'] ?? null, $scope);
        $max = $this->bound($v['max'] ?? null, $scope);

        return match ($type) {
            FieldType::Text, FieldType::Textarea => [$key => [...$base, 'string', 'min:'.FieldSpec::int($v, 'minLength', 0), 'max:'.FieldSpec::int($v, 'maxLength', $type === FieldType::Text ? 255 : 5000)]],
            FieldType::Number => [$key => [...$base, 'numeric', ...(($v['integer'] ?? false) === true ? ['integer'] : []), ...$this->numberBounds($v)]],
            FieldType::Money => [
                $key => [...$base, 'array:amount_minor,currency'],
                "{$key}.amount_minor" => [$required ? 'required' : 'nullable', 'integer', ...$this->numberBounds($v)],
                "{$key}.currency" => [$required ? 'required' : 'nullable', Rule::in([$this->currency($field, $scope)])],
            ],
            FieldType::Email => [$key => [...$base, 'string', 'email:rfc', 'max:255']],
            FieldType::Phone => [$key => [...$base, 'string', 'regex:'.self::PHONE_PATTERN]],
            FieldType::Date => [$key => [...$base, 'date_format:Y-m-d', ...$this->dateBounds($min, $max)]],
            FieldType::DateRange => [
                $key => [...$base, 'array:from,to', $this->maxDays($v['maxDays'] ?? null)],
                "{$key}.from" => [$required ? 'required' : 'nullable', 'date_format:Y-m-d', ...$this->dateBounds($min, $max)],
                "{$key}.to" => [$required ? 'required' : 'nullable', 'date_format:Y-m-d', "after_or_equal:{$key}.from", ...$this->dateBounds($min, $max)],
            ],
            FieldType::Time => [$key => [...$base, 'date_format:H:i', ...(is_string($min) ? ["after_or_equal:{$min}"] : []), ...(is_string($max) ? ["before_or_equal:{$max}"] : [])]],
            FieldType::Select, FieldType::Radio => [$key => [...$base, 'string', Rule::in($options)]],
            FieldType::Multiselect, FieldType::Checkboxes => [
                $key => [...$base, 'array', 'min:'.FieldSpec::int($v, 'minItems', $required ? 1 : 0), ...(is_int($v['maxItems'] ?? null) ? ['max:'.$v['maxItems']] : [])],
                "{$key}.*" => ['string', 'distinct', Rule::in($options)],
            ],
            FieldType::Boolean => [$key => $required ? ['accepted'] : ['nullable', 'boolean']],
            FieldType::File => [
                $key => [...$base, 'array', 'max:'.FieldSpec::int($v, 'maxFiles', 1), ...($files instanceof Closure ? [$this->uploads($key, $field, $files)] : [])],
                "{$key}.*" => ['string', 'size:26'],
            ],
            FieldType::Employee => [$key => [...$base, 'string', Rule::exists('employees', 'ulid')->whereNull('deleted_at')->whereNot('status', 'terminated')]],
            FieldType::Department => [$key => [...$base, 'string', Rule::exists('departments', 'ulid')->whereNull('deleted_at')]],
            FieldType::Branch => [$key => [...$base, 'string', Rule::exists('branches', 'ulid')->whereNull('deleted_at')]],
            FieldType::LeaveType => [$key => [...$base, 'string', Rule::exists('leave_types', 'code')->whereNull('deleted_at')->where('is_active', true)]],
            FieldType::Calculated, FieldType::Note => [],
        };
    }

    /**
     * @param  array<mixed>  $v
     * @return list<string>
     */
    private function numberBounds(array $v): array
    {
        return [
            ...(is_int($v['min'] ?? null) || is_float($v['min'] ?? null) ? ['min:'.$v['min']] : []),
            ...(is_int($v['max'] ?? null) || is_float($v['max'] ?? null) ? ['max:'.$v['max']] : []),
        ];
    }

    /**
     * A date bound: a literal `Y-m-d` or a rule over `ctx` (e.g. today).
     *
     * @param  array<string, mixed>  $scope
     */
    private function bound(mixed $bound, array $scope): ?string
    {
        if ($bound === null) {
            return null;
        }

        try {
            $value = is_string($bound) ? $bound : Rules::evaluate($bound, $scope);
        } catch (RuleError) {
            return null;
        }

        return is_string($value) ? $value : null;
    }

    /**
     * @return list<string>
     */
    private function dateBounds(?string $min, ?string $max): array
    {
        return [
            ...($min !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $min) === 1 ? ["after_or_equal:{$min}"] : []),
            ...($max !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $max) === 1 ? ["before_or_equal:{$max}"] : []),
        ];
    }

    private function maxDays(mixed $maxDays): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($maxDays): void {
            if (! is_int($maxDays) || ! is_array($value)) {
                return;
            }

            $days = Rules::evaluate(['op' => 'days_between', 'args' => [$value['from'] ?? null, $value['to'] ?? null]], []);

            if (is_int($days) && $days + 1 > $maxDays) {
                $fail(__('Choose at most :days days.', ['days' => $maxDays]));
            }
        };
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $scope
     */
    private function currency(array $field, array $scope): string
    {
        $currency = $field['currency'] ?? Rules::evaluate(['ref' => 'requester.currency'], $scope);

        return is_string($currency) ? $currency : 'USD';
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  Closure(string, list<string>, array<string, mixed>): (list<array<string, mixed>>|string)  $files
     */
    private function uploads(string $key, array $field, Closure $files): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($key, $field, $files): void {
            if (! is_array($value) || $value === []) {
                return;
            }

            $result = $files($key, array_values(array_filter($value, is_string(...))), $field);

            if (is_string($result)) {
                $fail($result);
            }
        };
    }

    private function normalize(FieldType $type, mixed $value): mixed
    {
        if (in_array($value, [null, '', []], true)) {
            return $type === FieldType::Boolean ? false : null;
        }

        return match ($type) {
            FieldType::Number => is_numeric($value) ? $value + 0 : null,
            FieldType::Money => is_array($value) && is_numeric($value['amount_minor'] ?? null) ? ['amount_minor' => (int) $value['amount_minor'], 'currency' => FieldSpec::string($value['currency'] ?? null)] : null,
            FieldType::Boolean => filter_var($value, FILTER_VALIDATE_BOOL),
            FieldType::DateRange => is_array($value) ? ['from' => $value['from'] ?? null, 'to' => $value['to'] ?? null] : null,
            FieldType::Multiselect, FieldType::Checkboxes => is_array($value) ? array_values($value) : [],
            FieldType::Text, FieldType::Textarea => is_string($value) ? trim($value) : $value,
            default => $value,
        };
    }
}
