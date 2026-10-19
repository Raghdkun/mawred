<?php

declare(strict_types=1);

namespace App\Domain\Reports\Support;

use App\Domain\Reports\Enums\ColumnType;

/** A column of a report's table and files. */
final readonly class ReportColumn
{
    /**
     * @param  array{en: string, ar: string}  $label
     */
    public function __construct(
        public string $key,
        public array $label,
        public ColumnType $type = ColumnType::Text,
    ) {}

    public static function text(string $key, string $en, string $ar): self
    {
        return new self($key, ['en' => $en, 'ar' => $ar]);
    }

    public static function number(string $key, string $en, string $ar, ColumnType $type = ColumnType::Number): self
    {
        return new self($key, ['en' => $en, 'ar' => $ar], $type);
    }

    public function labelIn(string $locale): string
    {
        return $locale === 'ar' ? $this->label['ar'] : $this->label['en'];
    }
}
