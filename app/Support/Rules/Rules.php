<?php

declare(strict_types=1);

namespace App\Support\Rules;

use DateTimeImmutable;
use DateTimeZone;

/**
 * The shared rules language `mawred.rule/v1` (ADR-0008): a JSON expression tree used for form
 * visibility, required-ness, calculated values and workflow conditions. There is no free-text
 * formula anywhere. The TypeScript twin is `resources/js/lib/rules`; both must produce the same
 * result for every fixture in `tests/fixtures/rules`.
 *
 * Nodes:
 * - a JSON scalar (string, number, bool, null) is a literal; `{"value": …}` is a literal of any shape
 * - `{"ref": "field.amount"}` reads the context by dotted path (`field.*`, `requester.*`, `ctx.*`)
 * - `{"op": "<name>", "args": [ … ]}` applies an operator
 *
 * Null propagates: arithmetic with null gives null, ordering comparisons with null are false.
 */
final class Rules
{
    public const string FORMAT = 'mawred.rule/v1';

    public const int MAX_DEPTH = 32;

    /** @var array<string, array{0: int, 1: int|null}> operator => [min args, max args] */
    public const array OPERATORS = [
        'all' => [1, null], 'any' => [1, null], 'not' => [1, 1],
        'eq' => [2, 2], 'neq' => [2, 2], 'gt' => [2, 2], 'gte' => [2, 2], 'lt' => [2, 2], 'lte' => [2, 2],
        'in' => [2, 2], 'contains' => [2, 2], 'empty' => [1, 1], 'between' => [3, 3],
        'add' => [2, null], 'sub' => [2, 2], 'mul' => [2, null], 'div' => [2, 2], 'round' => [1, 2],
        'sum' => [1, 1], 'count' => [1, 1], 'if' => [3, 3],
        'days_between' => [2, 2], 'working_days_between' => [2, 2], 'balance' => [1, 1],
    ];

    /**
     * Evaluates a node against a context: `['field' => …, 'requester' => …, 'ctx' => …]`.
     * `ctx.calendar` = `['weekend' => [5, 6], 'holidays' => ['2026-03-20', …]]` feeds
     * `working_days_between`; `ctx.balances` = `['ANNUAL' => 12.5]` feeds `balance`.
     *
     * @param  array<string, mixed>  $context
     */
    public static function evaluate(mixed $node, array $context, int $depth = 0): mixed
    {
        if ($depth > self::MAX_DEPTH) {
            throw new RuleError('Rule is nested too deeply.');
        }

        if ($node === null || is_scalar($node)) {
            return $node;
        }

        if (! is_array($node)) {
            throw new RuleError('Unsupported rule node.');
        }

        if (array_key_exists('value', $node) && count($node) === 1) {
            return $node['value'];
        }

        if (isset($node['ref']) && count($node) === 1) {
            return is_string($node['ref']) ? self::resolve($node['ref'], $context) : throw new RuleError('A ref must be a string.');
        }

        $op = $node['op'] ?? null;
        $args = $node['args'] ?? null;

        if (! is_string($op) || ! isset(self::OPERATORS[$op]) || ! is_array($args) || ! array_is_list($args)) {
            throw new RuleError('Unknown rule node.');
        }

        [$min, $max] = self::OPERATORS[$op];

        if (count($args) < $min || ($max !== null && count($args) > $max)) {
            throw new RuleError("Wrong number of arguments for {$op}.");
        }

        $next = $depth + 1;
        $eval = fn (mixed $arg): mixed => self::evaluate($arg, $context, $next);

        // Logic short-circuits, so an unused branch is never evaluated.
        switch ($op) {
            case 'all':
                return array_all($args, fn ($arg): bool => self::truthy($eval($arg)));
            case 'any':
                return array_any($args, fn ($arg): bool => self::truthy($eval($arg)));
            case 'not':
                return ! self::truthy($eval($args[0]));
            case 'if':
                return self::truthy($eval($args[0])) ? $eval($args[1]) : $eval($args[2]);
        }

        $values = array_map($eval, $args);

        return match ($op) {
            'eq' => self::equals($values[0], $values[1]),
            'neq' => ! self::equals($values[0], $values[1]),
            'gt' => self::compare($values[0], $values[1]) === 1,
            'gte' => in_array(self::compare($values[0], $values[1]), [0, 1], true),
            'lt' => self::compare($values[0], $values[1]) === -1,
            'lte' => in_array(self::compare($values[0], $values[1]), [-1, 0], true),
            'in' => self::isList($values[1]) && array_any($values[1], fn (mixed $item): bool => self::equals($values[0], $item)),
            'contains' => self::contains($values[0], $values[1]),
            'empty' => self::isEmpty($values[0]),
            'between' => in_array(self::compare($values[0], $values[1]), [0, 1], true) && in_array(self::compare($values[0], $values[2]), [-1, 0], true),
            'add' => self::arithmetic($values, fn (float $a, float $b): float => $a + $b),
            'sub' => self::arithmetic($values, fn (float $a, float $b): float => $a - $b),
            'mul' => self::arithmetic($values, fn (float $a, float $b): float => $a * $b),
            'div' => self::number($values[1]) === 0.0 ? null : self::arithmetic($values, fn (float $a, float $b): float => $a / $b),
            'round' => self::round($values[0], $values[1] ?? 0),
            'sum' => self::isList($values[0]) ? self::normalize(array_sum(array_map(fn (mixed $v): float => self::number($v) ?? 0.0, $values[0]))) : null,
            'count' => self::isList($values[0]) ? count($values[0]) : ($values[0] === null ? 0 : 1),
            'days_between' => self::daysBetween($values[0], $values[1]),
            'working_days_between' => self::workingDaysBetween($values[0], $values[1], $context),
            'balance' => self::balance($values[0], $context),
        };
    }

    /**
     * Structural check before a rule is saved: every operator known, arity right, depth bounded,
     * refs rooted in an allowed namespace.
     *
     * @param  list<string>  $roots  allowed ref roots
     * @return list<string> problems, empty when valid
     */
    public static function check(mixed $node, array $roots = ['field', 'requester', 'ctx'], int $depth = 0): array
    {
        if ($depth > self::MAX_DEPTH) {
            return ['Rule is nested too deeply.'];
        }

        if ($node === null || is_scalar($node)) {
            return [];
        }

        if (! is_array($node)) {
            return ['Unsupported rule node.'];
        }

        if (array_key_exists('value', $node) && count($node) === 1) {
            return [];
        }

        if (array_key_exists('ref', $node) && count($node) === 1) {
            $root = is_string($node['ref']) ? explode('.', $node['ref'])[0] : null;

            return in_array($root, $roots, true) ? [] : ['Unknown reference '.json_encode($node['ref']).'.'];
        }

        $op = $node['op'] ?? null;
        $args = $node['args'] ?? null;

        if (! is_string($op) || ! isset(self::OPERATORS[$op])) {
            return ['Unknown operator '.json_encode($op).'.'];
        }

        if (! is_array($args) || ! array_is_list($args)) {
            return ["{$op} needs a list of arguments."];
        }

        [$min, $max] = self::OPERATORS[$op];

        if (count($args) < $min || ($max !== null && count($args) > $max)) {
            return ["Wrong number of arguments for {$op}."];
        }

        $problems = [];

        foreach ($args as $arg) {
            $problems = [...$problems, ...self::check($arg, $roots, $depth + 1)];
        }

        return $problems;
    }

    /**
     * Refs a rule reads, e.g. `field.amount`, so the builder can warn about deleted fields.
     *
     * @return list<string>
     */
    public static function refs(mixed $node): array
    {
        if (! is_array($node)) {
            return [];
        }

        if (isset($node['ref']) && is_string($node['ref'])) {
            return [$node['ref']];
        }

        $refs = [];

        foreach (is_array($node['args'] ?? null) ? $node['args'] : [] as $arg) {
            $refs = [...$refs, ...self::refs($arg)];
        }

        return array_values(array_unique($refs));
    }

    public static function truthy(mixed $value): bool
    {
        return ! (in_array($value, [null, false, 0, 0.0, '', []], true));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function resolve(string $path, array $context): mixed
    {
        $value = $context;

        foreach (explode('.', $path) as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } elseif (is_array($value) && ctype_digit($segment) && array_key_exists((int) $segment, $value)) {
                $value = $value[(int) $segment];
            } else {
                return null;
            }
        }

        return $value;
    }

    /**
     * @phpstan-assert-if-true list<mixed> $value
     */
    private static function isList(mixed $value): bool
    {
        return is_array($value) && array_is_list($value);
    }

    /** Numbers and numeric strings (`"12"`, `"-1.5"`) as a float; anything else is null. */
    private static function number(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return is_finite((float) $value) ? (float) $value : null;
        }

        return is_string($value) && preg_match('/^-?\d+(\.\d+)?$/', $value) === 1 ? (float) $value : null;
    }

    /** Whole floats become ints, so 2.0 and 2 serialise the same way in both runtimes. */
    private static function normalize(float $value): int|float
    {
        return floor($value) === $value && abs($value) < 1e15 ? (int) $value : $value;
    }

    private static function equals(mixed $a, mixed $b): bool
    {
        $x = self::number($a);
        $y = self::number($b);

        if ($x !== null && $y !== null) {
            return $x === $y;
        }

        return $a === $b;
    }

    /** -1, 0 or 1; null when the two can't be ordered (null, mixed types, arrays). */
    private static function compare(mixed $a, mixed $b): ?int
    {
        $x = self::number($a);
        $y = self::number($b);

        if ($x !== null && $y !== null) {
            return $x <=> $y;
        }

        if (is_string($a) && is_string($b)) {
            return strcmp($a, $b) <=> 0;
        }

        return null;
    }

    private static function contains(mixed $haystack, mixed $needle): bool
    {
        if (self::isList($haystack)) {
            return array_any($haystack, fn (mixed $item): bool => self::equals($item, $needle));
        }

        return is_string($haystack) && is_string($needle) && str_contains(mb_strtolower($haystack), mb_strtolower($needle));
    }

    private static function isEmpty(mixed $value): bool
    {
        return in_array($value, [null, '', []], true) || (is_string($value) && trim($value) === '');
    }

    /**
     * @param  list<mixed>  $values
     * @param  callable(float, float): float  $apply
     */
    private static function arithmetic(array $values, callable $apply): int|float|null
    {
        $result = null;

        foreach ($values as $value) {
            $number = self::number($value);

            if ($number === null) {
                return null;
            }

            $result = $result === null ? $number : $apply($result, $number);
        }

        if ($result === null) {
            return null;
        }

        return is_finite($result) ? self::normalize($result) : null;
    }

    /** Half away from zero, the same as the TypeScript twin. */
    private static function round(mixed $value, mixed $digits): int|float|null
    {
        $number = self::number($value);
        $places = self::number($digits);

        if ($number === null || $places === null) {
            return null;
        }

        return self::normalize(round($number, max(0, min(6, (int) $places))));
    }

    private static function date(mixed $value): ?DateTimeImmutable
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));

        return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
    }

    /** Calendar days from a to b (negative when b is earlier). */
    private static function daysBetween(mixed $a, mixed $b): ?int
    {
        $from = self::date($a);
        $to = self::date($b);

        if (! $from instanceof DateTimeImmutable || ! $to instanceof DateTimeImmutable) {
            return null;
        }

        return intdiv($to->getTimestamp() - $from->getTimestamp(), 86400);
    }

    /**
     * Working days in [a, b], inclusive, on the requester's calendar.
     *
     * @param  array<string, mixed>  $context
     */
    private static function workingDaysBetween(mixed $a, mixed $b, array $context): ?int
    {
        $from = self::date($a);
        $to = self::date($b);

        if (! $from instanceof DateTimeImmutable || ! $to instanceof DateTimeImmutable || $to < $from) {
            return $from instanceof DateTimeImmutable && $to instanceof DateTimeImmutable ? 0 : null;
        }

        $weekend = self::resolve('ctx.calendar.weekend', $context);
        $holidays = self::resolve('ctx.calendar.holidays', $context);
        $weekend = is_array($weekend) ? array_map(self::number(...), $weekend) : [];
        $off = is_array($holidays) ? array_fill_keys(array_filter($holidays, is_string(...)), true) : [];
        $days = 0;

        // Bounded: date ranges in forms are at most a few years.
        for ($day = $from, $guard = 0; $day <= $to && $guard < 3660; $day = $day->modify('+1 day'), $guard++) {
            if (! in_array((float) $day->format('N'), $weekend, true) && ! isset($off[$day->format('Y-m-d')])) {
                $days++;
            }
        }

        return $days;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function balance(mixed $code, array $context): int|float|null
    {
        if (! is_string($code)) {
            return null;
        }

        $value = self::number(self::resolve('ctx.balances.'.$code, $context));

        return $value === null ? null : self::normalize($value);
    }
}
