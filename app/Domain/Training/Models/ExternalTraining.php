<?php

declare(strict_types=1);

namespace App\Domain\Training\Models;

use App\Domain\Audit\Concerns\Auditable;
use App\Domain\People\Models\Employee;
use App\Support\Ids\HasPublicUlid;
use App\Support\Money\Money;
use App\Support\Money\MoneyCast;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Training taken outside the catalog (D105), usually from an approved training request: the
 * course, provider, dates and cost, and the certificate once uploaded. HR can enrol the person
 * in a catalog course instead, which links the enrolment here.
 *
 * @property int $id
 * @property string $ulid
 * @property int $employee_id
 * @property int|null $request_id
 * @property string $title
 * @property string|null $provider
 * @property CarbonImmutable|null $starts_on
 * @property CarbonImmutable|null $ends_on
 * @property Money|null $cost
 * @property string|null $certificate_path
 * @property string|null $certificate_name
 * @property int|null $enrollment_id
 * @property int|null $created_by
 */
#[Guarded(['id', 'ulid'])]
final class ExternalTraining extends Model
{
    use Auditable;
    use HasPublicUlid;

    public const string DISK = 'local';

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
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'cost' => MoneyCast::class,
        ];
    }
}
