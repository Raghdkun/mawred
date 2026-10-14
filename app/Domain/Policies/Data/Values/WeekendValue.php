<?php

declare(strict_types=1);

namespace App\Domain\Policies\Data\Values;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Weekend days as ISO weekdays (1 = Monday). Falls back to the branch (D46). */
#[TypeScript]
final class WeekendValue extends Data
{
    /**
     * @param  list<int>  $days
     */
    public function __construct(public array $days) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return ['days' => ['present', 'array', 'max:6'], 'days.*' => ['integer', 'between:1,7', 'distinct']];
    }
}
