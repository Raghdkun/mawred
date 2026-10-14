<?php

declare(strict_types=1);

namespace App\Domain\Forms\Support;

use App\Domain\Forms\Enums\FieldType;
use App\Support\Rules\RuleError;
use App\Support\Rules\Rules;

/**
 * Which fields are visible and what calculated fields come to, for one set of answers. The
 * TypeScript twin is `resolveForm` in `features/forms/resolve.ts`; both:
 * 1. start from the submitted input values,
 * 2. per pass: blank hidden inputs, compute calculated fields in order, re-evaluate visibility,
 * 3. stop when visibility is stable (at most five passes; chains longer than that are refused
 *    by nothing, they simply settle on the fifth pass).
 */
final class FormResolution
{
    private const int PASSES = 5;

    /**
     * @param  array<string, array<string, mixed>>  $fields  by key
     * @param  array<string, mixed>  $input  submitted values for input fields
     * @param  array<string, mixed>  $context  requester + ctx
     * @return array{values: array<string, mixed>, hidden: list<string>}
     */
    public static function resolve(array $fields, array $input, array $context): array
    {
        $hidden = [];
        $values = [];

        for ($pass = 0; $pass < self::PASSES; $pass++) {
            $values = [];

            foreach ($fields as $key => $field) {
                $type = FieldSpec::type($field);

                if ($type?->acceptsInput() === true) {
                    $values[$key] = in_array($key, $hidden, true) ? null : ($input[$key] ?? null);
                }
            }

            foreach ($fields as $key => $field) {
                if (($field['type'] ?? null) === FieldType::Calculated->value) {
                    $values[$key] = in_array($key, $hidden, true) ? null : self::safely($field['calc'] ?? null, [...$context, 'field' => $values]);
                }
            }

            $next = [];

            foreach ($fields as $key => $field) {
                $rule = $field['visible'] ?? null;

                if ($rule !== null && $rule !== true && ! Rules::truthy(self::safely($rule, [...$context, 'field' => $values]))) {
                    $next[] = $key;
                }
            }

            if ($next === $hidden) {
                break;
            }

            $hidden = $next;
        }

        return ['values' => $values, 'hidden' => $hidden];
    }

    /**
     * Whether a field is required with these values.
     *
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $context
     */
    public static function isRequired(array $field, array $context): bool
    {
        $rule = $field['required'] ?? false;

        return is_bool($rule) ? $rule : Rules::truthy(self::safely($rule, $context));
    }

    /**
     * A broken rule counts as "no value" rather than failing the submission; rules are checked on save.
     *
     * @param  array<string, mixed>  $context
     */
    private static function safely(mixed $rule, array $context): mixed
    {
        try {
            return Rules::evaluate($rule, $context);
        } catch (RuleError) {
            return null;
        }
    }
}
