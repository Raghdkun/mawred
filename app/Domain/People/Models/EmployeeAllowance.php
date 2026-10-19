<?php

declare(strict_types=1);

namespace App\Domain\People\Models;

use App\Domain\People\Enums\AllowanceType;
use App\Support\Ids\HasPublicUlid;
use App\Support\Money\EncryptedMoneyCast;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A monthly allowance (D116): housing, transport and so on, from one date until another (or
 * open-ended). Readable only with the `employee.salary` field permission; the amount is encrypted.
 *
 * @property int $id
 * @property string $ulid
 * @property int $employee_id
 * @property AllowanceType $type
 * @property string|null $label
 * @property Money|null $amount
 * @property CarbonImmutable $effective_from
 * @property CarbonImmutable|null $effective_to
 * @property string|null $note
 * @property int|null $created_by
 */
#[Guarded(['id', 'ulid'])]
final class EmployeeAllowance extends Model
{
    use HasPublicUlid;

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** In force on a day (Y-m-d). */
    public function coversDate(string $day): bool
    {
        return $this->effective_from->toDateString() <= $day && ($this->effective_to === null || $this->effective_to->toDateString() >= $day);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AllowanceType::class,
            'amount' => EncryptedMoneyCast::class,
            'effective_from' => 'immutable_date',
            'effective_to' => 'immutable_date',
        ];
    }
}
