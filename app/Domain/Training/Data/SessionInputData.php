<?php

declare(strict_types=1);

namespace App\Domain\Training\Data;

use App\Domain\Training\Enums\SessionMode;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A session as a trainer plans it: when (ISO instants), where, seats and the trainer. */
#[TypeScript]
final class SessionInputData extends Data
{
    public function __construct(
        public string $startsAt,
        public string $endsAt,
        public string $timezone,
        public string $mode = 'onsite',
        public ?string $location = null,
        public ?string $meetingUrl = null,
        public ?int $capacity = null,
        public ?string $trainerId = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'startsAt' => ['required', 'date'],
            'endsAt' => ['required', 'date', 'after:startsAt'],
            'timezone' => ['required', 'timezone:all'],
            'mode' => ['required', Rule::enum(SessionMode::class)],
            'location' => ['nullable', 'string', 'max:200'],
            'meetingUrl' => ['nullable', 'url:https', 'max:500'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'trainerId' => ['nullable', 'string', 'size:26', 'exists:users,ulid'],
        ];
    }
}
