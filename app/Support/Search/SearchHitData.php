<?php

declare(strict_types=1);

namespace App\Support\Search;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One global-search result (⌘K): already localised for the viewer.
 */
#[TypeScript]
final class SearchHitData extends Data
{
    public function __construct(
        public string $type,
        public string $id,
        public string $title,
        public ?string $subtitle,
        public string $url,
        public ?string $status = null,
    ) {}
}
