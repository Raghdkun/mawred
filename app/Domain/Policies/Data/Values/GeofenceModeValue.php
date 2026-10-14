<?php

declare(strict_types=1);

namespace App\Domain\Policies\Data\Values;

use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Check-ins outside the branch geofence: block, flag for review (D26 default) or ignore. */
#[TypeScript]
final class GeofenceModeValue extends Data
{
    public function __construct(public string $mode = 'flag') {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return ['mode' => ['required', Rule::in(['block', 'flag', 'off'])]];
    }
}
