<?php

declare(strict_types=1);

namespace App\Domain\People\Models;

use App\Domain\Audit\Concerns\Auditable;
use App\Support\Ids\HasPublicUlid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Company equipment with an employee (laptop, phone, uniform, keys…). Offboarding checks for
 * unreturned assets in a later phase.
 *
 * @property int $id
 * @property string $ulid
 * @property int $employee_id
 * @property string $name
 * @property string|null $category
 * @property string $asset_tag
 * @property string|null $serial_number
 * @property CarbonImmutable $assigned_on
 * @property CarbonImmutable|null $due_back_on
 * @property CarbonImmutable|null $returned_on
 * @property string|null $condition_notes
 */
#[Guarded(['id', 'ulid'])]
final class AssetAssignment extends Model
{
    use Auditable;
    use HasPublicUlid;

    /** @var array<string, mixed> */
    protected $attributes = ['category' => null, 'serial_number' => null, 'due_back_on' => null, 'returned_on' => null, 'condition_notes' => null];

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['assigned_on' => 'immutable_date', 'due_back_on' => 'immutable_date', 'returned_on' => 'immutable_date'];
    }
}
