<?php

declare(strict_types=1);

namespace App\Domain\Requests\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One line of a request's live timeline (handoff 12, DSData workflow timeline). */
#[TypeScript]
final class TimelineEntryData extends Data
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public string $event,
        public string $at,
        public ?string $actor,
        public ?string $subject,
        public ?string $comment,
        public ?string $via,
        public array $data,
    ) {}
}
