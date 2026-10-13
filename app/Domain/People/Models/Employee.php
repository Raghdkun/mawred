<?php

declare(strict_types=1);

namespace App\Domain\People\Models;

use App\Domain\Access\Models\User;
use App\Domain\Audit\Concerns\Auditable;
use App\Domain\Audit\Contracts\MasksAuditedAttributes;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\EmploymentType;
use App\Domain\Organization\Models\Grade;
use App\Domain\Organization\Models\Position;
use App\Domain\People\Enums\EmployeeStatus;
use App\Support\Crypto\BlindIndex;
use App\Support\Ids\HasPublicUlid;
use App\Support\Search\HasSearchText;
use Carbon\CarbonImmutable;
use Database\Factories\People\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One row per person employed (architecture "People"). The `current_*` columns mirror the
 * primary assignment in force today and are written only by SyncEmployeePlacement.
 *
 * @property int $id
 * @property string $ulid
 * @property string $employee_number
 * @property int|null $user_id
 * @property string $name_en
 * @property string|null $name_ar
 * @property string|null $preferred_name
 * @property string|null $work_email
 * @property string|null $work_phone_e164
 * @property string|null $gender
 * @property string|null $nationality
 * @property CarbonImmutable|null $date_of_birth
 * @property string|null $marital_status
 * @property string|null $national_id
 * @property string|null $passport_number
 * @property string|null $passport_country
 * @property CarbonImmutable|null $passport_expires_on
 * @property string|null $residency_number
 * @property CarbonImmutable|null $residency_expires_on
 * @property array<string, string|null>|null $bank_details
 * @property array<string, string|null>|null $personal_contact
 * @property EmployeeStatus $status
 * @property CarbonImmutable $hire_date
 * @property CarbonImmutable|null $probation_ends_on
 * @property CarbonImmutable|null $termination_date
 * @property string|null $termination_reason
 * @property int|null $current_branch_id
 * @property int|null $current_department_id
 * @property int|null $current_position_id
 * @property int|null $current_manager_id
 * @property int|null $current_employment_type_id
 * @property int|null $current_grade_id
 */
#[UseFactory(EmployeeFactory::class)]
#[Guarded(['id', 'ulid', 'search_text', 'national_id_bidx', 'passport_bidx', 'residency_bidx'])]
final class Employee extends Model implements MasksAuditedAttributes
{
    use Auditable;

    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

    use HasPublicUlid;
    use HasSearchText;
    use SoftDeletes;

    /** Encrypted attribute => blind index column (ADR-0007). */
    public const array BLIND_INDEXES = [
        'national_id' => 'national_id_bidx',
        'passport_number' => 'passport_bidx',
        'residency_number' => 'residency_bidx',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'user_id' => null,
        'name_ar' => null,
        'preferred_name' => null,
        'work_email' => null,
        'work_phone_e164' => null,
        'gender' => null,
        'nationality' => null,
        'date_of_birth' => null,
        'marital_status' => null,
        'national_id' => null,
        'passport_number' => null,
        'passport_country' => null,
        'passport_expires_on' => null,
        'residency_number' => null,
        'residency_expires_on' => null,
        'bank_details' => null,
        'personal_contact' => null,
        'status' => 'active',
        'probation_ends_on' => null,
        'termination_date' => null,
        'termination_reason' => null,
        'current_branch_id' => null,
        'current_department_id' => null,
        'current_position_id' => null,
        'current_manager_id' => null,
        'current_employment_type_id' => null,
        'current_grade_id' => null,
    ];

    public static function lookupHash(string $attribute, ?string $value): ?string
    {
        return BlindIndex::of($value, "employees.{$attribute}");
    }

    public function displayName(?string $locale = null): string
    {
        if ($this->preferred_name !== null && $this->preferred_name !== '') {
            return $this->preferred_name;
        }

        return ($locale ?? app()->getLocale()) === 'ar' && $this->name_ar !== null && $this->name_ar !== ''
            ? $this->name_ar
            : $this->name_en;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<EmployeeAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(EmployeeAssignment::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function currentBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'current_branch_id');
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function currentDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'current_department_id');
    }

    /**
     * @return BelongsTo<Position, $this>
     */
    public function currentPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'current_position_id');
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function currentManager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'current_manager_id');
    }

    /**
     * @return BelongsTo<EmploymentType, $this>
     */
    public function currentEmploymentType(): BelongsTo
    {
        return $this->belongsTo(EmploymentType::class, 'current_employment_type_id');
    }

    /**
     * @return BelongsTo<Grade, $this>
     */
    public function currentGrade(): BelongsTo
    {
        return $this->belongsTo(Grade::class, 'current_grade_id');
    }

    /**
     * @return HasMany<EmployeeCustomValue, $this>
     */
    public function customValues(): HasMany
    {
        return $this->hasMany(EmployeeCustomValue::class);
    }

    /**
     * @return HasMany<EmployeeDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    /**
     * @return HasMany<EmploymentHistoryEntry, $this>
     */
    public function history(): HasMany
    {
        return $this->hasMany(EmploymentHistoryEntry::class);
    }

    /**
     * @return HasMany<AssetAssignment, $this>
     */
    public function assets(): HasMany
    {
        return $this->hasMany(AssetAssignment::class);
    }

    /**
     * @return HasMany<self, $this>
     */
    public function directReports(): HasMany
    {
        return $this->hasMany(self::class, 'current_manager_id');
    }

    public function searchableValues(): array
    {
        return [$this->employee_number, $this->name_en, $this->name_ar, $this->preferred_name, $this->work_email, $this->work_phone_e164];
    }

    public function auditMaskedAttributes(): array
    {
        return ['national_id', 'passport_number', 'residency_number', 'bank_details', 'personal_contact', 'date_of_birth'];
    }

    protected static function booted(): void
    {
        self::saving(function (self $employee): void {
            foreach (self::BLIND_INDEXES as $attribute => $column) {
                if ($employee->isDirty($attribute)) {
                    $value = $employee->getAttribute($attribute);
                    $employee->setAttribute($column, self::lookupHash($attribute, is_string($value) ? $value : null));
                }
            }
        });
    }

    /**
     * @return list<string>
     */
    protected function auditExcept(): array
    {
        return ['search_text', 'national_id_bidx', 'passport_bidx', 'residency_bidx'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EmployeeStatus::class,
            'date_of_birth' => 'immutable_date',
            'hire_date' => 'immutable_date',
            'probation_ends_on' => 'immutable_date',
            'termination_date' => 'immutable_date',
            'passport_expires_on' => 'immutable_date',
            'residency_expires_on' => 'immutable_date',
            'national_id' => 'encrypted',
            'passport_number' => 'encrypted',
            'residency_number' => 'encrypted',
            'bank_details' => 'encrypted:array',
            'personal_contact' => 'encrypted:array',
        ];
    }
}
