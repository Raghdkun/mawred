<?php

declare(strict_types=1);

namespace App\Domain\Reports\Support;

use App\Domain\Reports\Enums\FilterType;

/** A filter a report asks for: its key, type, whether it's required, and for choices the options. */
final readonly class ReportFilter
{
    /**
     * @param  array{en: string, ar: string}|null  $label  for choices; the others have standard labels
     * @param  list<array{value: string, label: array{en: string, ar: string}}>  $options
     */
    public function __construct(
        public string $key,
        public FilterType $type,
        public bool $required = false,
        public ?array $label = null,
        public array $options = [],
        public int|string|null $default = null,
    ) {}
}
