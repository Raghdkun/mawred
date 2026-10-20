<?php

declare(strict_types=1);

namespace App\Domain\Reports\Datasets;

use App\Domain\Reports\Enums\ColumnType;
use App\Domain\Reports\Enums\FilterOp;
use Closure;

/**
 * One field the builder offers for a dataset (D121): a label, a type, and the SQL expression
 * that reads it. Expressions are written here, never by users; values always go in as
 * bindings. Choice fields carry their options and labels; a sensitive field needs its field
 * permission to be shown, filtered or grouped on.
 */
final readonly class DatasetField
{
    /**
     * @param  array{en: string, ar: string}  $label
     * @param  Closure(string): literal-string  $sql  the expression for a locale
     * @param  list<array{value: string, label: array{en: string, ar: string}}>  $options
     */
    public function __construct(
        public string $key,
        public array $label,
        public ColumnType $type,
        private Closure $sql,
        public bool $groupable = false,
        public bool $measurable = false,
        public array $options = [],
        public ?string $sensitive = null,
    ) {}

    /**
     * @param  literal-string|Closure(string): literal-string  $sql
     */
    public static function text(string $key, string $en, string $ar, string|Closure $sql, bool $groupable = true): self
    {
        return new self($key, ['en' => $en, 'ar' => $ar], ColumnType::Text, self::closure($sql), $groupable);
    }

    /**
     * @param  literal-string  $sql
     * @param  list<array{value: string, label: array{en: string, ar: string}}>  $options
     */
    public static function choice(string $key, string $en, string $ar, string $sql, array $options): self
    {
        return new self($key, ['en' => $en, 'ar' => $ar], ColumnType::Text, self::closure($sql), true, false, $options);
    }

    /**
     * @param  literal-string  $sql
     */
    public static function date(string $key, string $en, string $ar, string $sql, ?string $sensitive = null): self
    {
        return new self($key, ['en' => $en, 'ar' => $ar], ColumnType::Date, self::closure($sql), false, false, [], $sensitive);
    }

    /**
     * @param  literal-string  $sql
     */
    public static function number(string $key, string $en, string $ar, string $sql, ColumnType $type = ColumnType::Number): self
    {
        return new self($key, ['en' => $en, 'ar' => $ar], $type, self::closure($sql), false, true);
    }

    /** @return literal-string */
    public function sql(string $locale): string
    {
        return ($this->sql)($locale);
    }

    public function labelIn(string $locale): string
    {
        return $locale === 'ar' ? $this->label['ar'] : $this->label['en'];
    }

    public function isChoice(): bool
    {
        return $this->options !== [];
    }

    /**
     * The conditions this field can be filtered with.
     *
     * @return list<FilterOp>
     */
    public function ops(): array
    {
        return match (true) {
            $this->isChoice() => [FilterOp::In],
            $this->type === ColumnType::Date => [FilterOp::Between, FilterOp::LastDays],
            $this->type === ColumnType::Text => [FilterOp::Is, FilterOp::Contains],
            default => [FilterOp::Gte, FilterOp::Lte, FilterOp::Is],
        };
    }

    /** A raw value as the screen and files show it: choices by their label, numbers as numbers. */
    public function display(mixed $value, string $locale): string|int|float|null
    {
        if ($value === null) {
            return null;
        }

        if ($this->isChoice()) {
            $raw = is_scalar($value) ? (string) $value : '';

            foreach ($this->options as $option) {
                if ($option['value'] === $raw) {
                    return $locale === 'ar' ? $option['label']['ar'] : $option['label']['en'];
                }
            }

            return is_scalar($value) ? (string) $value : null;
        }

        return match ($this->type) {
            ColumnType::Number, ColumnType::Minutes => is_numeric($value) ? (int) $value : null,
            ColumnType::Decimal, ColumnType::Percent => is_numeric($value) ? round((float) $value, 2) : null,
            ColumnType::Date => is_string($value) ? substr($value, 0, 10) : null,
            ColumnType::Text => is_scalar($value) ? (string) $value : null,
        };
    }

    /**
     * @param  literal-string|Closure(string): literal-string  $sql
     * @return Closure(string): literal-string
     */
    private static function closure(string|Closure $sql): Closure
    {
        return is_string($sql) ? fn (string $locale): string => $sql : $sql;
    }
}
