<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Data;

use App\Domain\Hiring\Models\CandidateMessage;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A line of the communications log (D94). */
#[TypeScript]
final class CandidateMessageData extends Data
{
    public function __construct(
        public string $id,
        public string $direction,
        public string $channel,
        public string $purpose,
        public ?string $subject,
        public string $body,
        public string $status,
        public ?string $error,
        public ?string $by,
        public string $at,
    ) {}

    public static function fromMessage(CandidateMessage $message): self
    {
        return new self(
            $message->ulid,
            $message->direction->value,
            $message->channel->value,
            $message->purpose->value,
            $message->subject,
            $message->body,
            $message->status->value,
            $message->error,
            $message->sentBy?->name,
            $message->created_at->toIso8601String(),
        );
    }
}
