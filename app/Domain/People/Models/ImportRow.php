<?php

declare(strict_types=1);

namespace App\Domain\People\Models;

use App\Domain\People\Enums\ImportRowStatus;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One spreadsheet line. `level` orders commits so a manager in the same file is created before
 * the people who report to them.
 *
 * @property int $id
 * @property int $import_id
 * @property int $row_number
 * @property list<string|null> $raw
 * @property array<string, mixed>|null $normalized
 * @property array<string, list<string>>|null $errors
 * @property ImportRowStatus $status
 * @property string|null $action create|update
 * @property int $level
 * @property int|null $employee_id
 */
#[Guarded(['id'])]
#[WithoutTimestamps]
final class ImportRow extends Model
{
    /** @var array<string, mixed> */
    protected $attributes = ['normalized' => null, 'errors' => null, 'action' => null, 'level' => 0, 'employee_id' => null];

    /**
     * @return BelongsTo<Import, $this>
     */
    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['raw' => 'array', 'normalized' => 'array', 'errors' => 'array', 'status' => ImportRowStatus::class];
    }
}
