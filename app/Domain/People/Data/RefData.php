<?php

declare(strict_types=1);

namespace App\Domain\People\Data;

use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\EmploymentType;
use App\Domain\Organization\Models\Grade;
use App\Domain\Organization\Models\Position;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A reference to an organization record as shown next to a person (name in every locale).
 */
#[TypeScript]
final class RefData extends Data
{
    /**
     * @param  array<string, string>  $name
     */
    public function __construct(
        public string $id,
        public string $code,
        public array $name,
    ) {}

    public static function of(Branch|Department|Position|EmploymentType|Grade|null $record): ?self
    {
        if ($record === null) {
            return null;
        }

        /** @var array<string, string> $name */
        $name = $record->getTranslations($record instanceof Position ? 'title' : 'name');

        return new self($record->ulid, $record->code, $name);
    }
}
