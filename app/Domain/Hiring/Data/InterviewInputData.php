<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Data;

use App\Domain\Hiring\Enums\InterviewMode;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Setting up an interview: the panel, length and place, and either a time or a booking link (D89). */
#[TypeScript]
final class InterviewInputData extends Data
{
    /**
     * @param  list<string>  $panel  user ULIDs
     */
    public function __construct(
        public string $title,
        public int $durationMinutes,
        public array $panel,
        public string $timezone,
        public string $mode = 'onsite',
        public ?string $location = null,
        public ?string $startsAt = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'durationMinutes' => ['required', 'integer', Rule::in([15, 30, 45, 60, 90, 120])],
            'panel' => ['required', 'array', 'min:1', 'max:8'],
            'panel.*' => ['string', 'size:26', 'distinct'],
            'timezone' => ['required', 'timezone:all'],
            'mode' => ['required', Rule::enum(InterviewMode::class)],
            'location' => ['nullable', 'string', 'max:200'],
            'startsAt' => ['nullable', 'date'],
        ];
    }
}
