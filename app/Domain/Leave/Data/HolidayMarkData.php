<?php

declare(strict_types=1);

namespace App\Domain\Leave\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A public holiday cell on the leave calendar. */
#[TypeScript]
final class HolidayMarkData extends Data
{
    /**
     * @param  array<string, string>  $name
     */
    public function __construct(public string $date, public array $name) {}
}
