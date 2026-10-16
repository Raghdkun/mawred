<?php

declare(strict_types=1);

namespace App\Domain\Calendar\Data;

use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A meeting as the scheduler sends it (handoff 27). Instants are ISO 8601 with an offset; the
 * timezone is where a repeat keeps its wall-clock time (D78). People are user ULIDs.
 */
#[TypeScript]
final class EventInputData extends Data
{
    /**
     * @param  list<string>  $required
     * @param  list<string>  $optional
     * @param  array{freq: string, interval?: int, byweekday?: list<int>, until?: string|null, count?: int|null}|null  $recurrence
     */
    public function __construct(
        public string $title,
        public string $startsAt,
        public string $endsAt,
        public string $timezone,
        public array $required = [],
        public array $optional = [],
        public ?string $description = null,
        public ?string $location = null,
        public bool $private = false,
        public ?array $recurrence = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'startsAt' => ['required', 'date'],
            'endsAt' => ['required', 'date', 'after:startsAt'],
            'timezone' => ['required', 'timezone:all'],
            'required' => ['array', 'max:100'],
            'required.*' => ['string', 'size:26', 'exists:users,ulid'],
            'optional' => ['array', 'max:100'],
            'optional.*' => ['string', 'size:26', 'exists:users,ulid'],
            'description' => ['nullable', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:200'],
            'private' => ['boolean'],
            'recurrence' => ['nullable', 'array'],
            'recurrence.freq' => ['required_with:recurrence', Rule::in(['daily', 'weekly', 'monthly'])],
            'recurrence.interval' => ['nullable', 'integer', 'between:1,12'],
            'recurrence.byweekday' => ['nullable', 'array', 'max:7'],
            'recurrence.byweekday.*' => ['integer', 'between:1,7', 'distinct'],
            'recurrence.until' => ['nullable', 'date_format:Y-m-d'],
            'recurrence.count' => ['nullable', 'integer', 'between:1,500'],
        ];
    }
}
