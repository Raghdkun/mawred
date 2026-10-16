<?php

declare(strict_types=1);

namespace App\Domain\Requests\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** An open approval or to-do in the inbox (handoff 13). */
#[TypeScript]
final class TaskData extends Data
{
    /**
     * @param  array<string, string>  $step
     * @param  list<string>  $decisions  what this person can decide
     * @param  list<array{label: string, value: string}>  $facts  what a non-request subject is about (an offer)
     */
    public function __construct(
        public string $id,
        public string $kind,
        public array $step,
        public string $assignedAt,
        public ?string $dueAt,
        public bool $overdue,
        public ?string $delegatedFrom,
        public array $decisions,
        public ?RequestRowData $request,
        public ?string $about = null,
        public ?string $instructions = null,
        public array $facts = [],
        public ?string $url = null,
    ) {}
}
