<?php

declare(strict_types=1);

namespace App\Support\Templates;

use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Arr;
use IntlDateFormatter;
use InvalidArgumentException;

/**
 * Logic-less renderer for admin-editable templates (ADR-0016).
 *
 * Supports `{{ variable }}`, dotted paths `{{ request.reference }}` and a small set of filters
 * `{{ starts_on | date }}`. There are no loops, conditionals or code execution, and Blade is
 * never compiled from database content.
 */
final class SafeTemplate
{
    private const string PATTERN = '/\{\{\s*([a-zA-Z_][a-zA-Z0-9_.]*)\s*(?:\|\s*([a-z_]+)\s*)?\}\}/';

    /**
     * @var array<string, Closure(mixed, string): string>
     */
    private array $filters;

    public function __construct()
    {
        $this->filters = [
            'upper' => fn (mixed $value): string => mb_strtoupper($this->stringify($value)),
            'lower' => fn (mixed $value): string => mb_strtolower($this->stringify($value)),
            'date' => fn (mixed $value, string $locale): string => $this->formatDate($value, $locale, IntlDateFormatter::NONE),
            'datetime' => fn (mixed $value, string $locale): string => $this->formatDate($value, $locale, IntlDateFormatter::SHORT),
            'number' => fn (mixed $value, string $locale): string => is_numeric($value)
                ? (string) (new \NumberFormatter($locale, \NumberFormatter::DECIMAL))->format((float) $value)
                : $this->stringify($value),
        ];
    }

    /**
     * @param  array<string, mixed>  $variables
     */
    public function render(string $template, array $variables, string $locale = 'en', bool $escapeHtml = true): string
    {
        return (string) preg_replace_callback(self::PATTERN, function (array $match) use ($variables, $locale, $escapeHtml): string {
            $value = Arr::get($variables, $match[1]);
            $filter = $match[2] ?? '';

            $rendered = $filter !== '' && isset($this->filters[$filter])
                ? ($this->filters[$filter])($value, $locale)
                : $this->stringify($value);

            return $escapeHtml ? e($rendered) : $rendered;
        }, $template);
    }

    /**
     * Variable paths referenced by a template, for validating against a category's allow-list.
     *
     * @return list<string>
     */
    public function variables(string $template): array
    {
        preg_match_all(self::PATTERN, $template, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * @param  list<string>  $allowed
     *
     * @throws InvalidArgumentException when the template uses unknown variables or filters
     */
    public function assertValid(string $template, array $allowed): void
    {
        $unknown = array_values(array_diff($this->variables($template), $allowed));

        preg_match_all(self::PATTERN, $template, $matches);
        $badFilters = array_values(array_diff(array_filter($matches[2]), array_keys($this->filters)));

        if ($unknown !== [] || $badFilters !== []) {
            throw new InvalidArgumentException(sprintf(
                'Unknown template variables [%s] or filters [%s].',
                implode(', ', $unknown),
                implode(', ', $badFilters),
            ));
        }
    }

    private function stringify(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? '1' : '0',
            is_scalar($value) => (string) $value,
            $value instanceof \Stringable => (string) $value,
            $value instanceof \BackedEnum => (string) $value->value,
            default => '',
        };
    }

    private function formatDate(mixed $value, string $locale, int $timeStyle): string
    {
        if ($value instanceof DateTimeInterface) {
            $date = $value;
        } elseif (is_string($value) && strtotime($value) !== false) {
            $date = new DateTimeImmutable($value);
        } else {
            return $this->stringify($value);
        }

        $formatter = new IntlDateFormatter($locale, IntlDateFormatter::LONG, $timeStyle, $date->getTimezone());

        return (string) $formatter->format($date);
    }
}
