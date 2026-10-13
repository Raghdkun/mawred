<?php

declare(strict_types=1);

namespace App\Domain\People\Models;

use App\Domain\People\Enums\HistoryEvent;
use App\Support\Ids\HasPublicUlid;
use App\Support\Money\EncryptedMoneyCast;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only employment timeline (screen 8 History). Salary rows carry an encrypted amount
 * readable only with the `employee.salary` field permission.
 *
 * @property int $id
 * @property string $ulid
 * @property int $employee_id
 * @property HistoryEvent $event
 * @property CarbonImmutable $effective_on
 * @property array<string, mixed> $changes
 * @property Money|null $salary
 * @property string|null $note
 * @property int|null $recorded_by
 * @property CarbonImmutable|null $created_at
 */
#[Guarded(['id', 'ulid'])]
#[Table(name: 'employment_history')]
final class EmploymentHistoryEntry extends Model
{
    use HasPublicUlid;

    /** @var array<string, mixed> */
    protected $attributes = ['changes' => '{}', 'salary_minor' => null, 'salary_currency' => null, 'note' => null, 'recorded_by' => null];

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
        return [
            'event' => HistoryEvent::class,
            'effective_on' => 'immutable_date',
            'changes' => 'array',
            'salary' => EncryptedMoneyCast::class,
            'created_at' => 'immutable_datetime',
        ];
    }
}
