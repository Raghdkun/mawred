<?php

declare(strict_types=1);

namespace App\Domain\People\Data;

use App\Domain\People\Enums\AllowanceType;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** An allowance as HR records it (D116): type, monthly amount and currency, and from when. */
#[TypeScript]
final class AllowanceInputData extends Data
{
    public function __construct(
        public string $type,
        public string $amount,
        public string $currency,
        public string $effectiveFrom,
        public ?string $effectiveTo = null,
        public ?string $label = null,
        public ?string $note = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(AllowanceType::class)],
            'amount' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'effectiveFrom' => ['required', 'date_format:Y-m-d'],
            'effectiveTo' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effectiveFrom'],
            'label' => ['nullable', 'required_if:type,other', 'string', 'max:80'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
