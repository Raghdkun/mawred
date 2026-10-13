<?php

declare(strict_types=1);

namespace App\Domain\People\Models;

use App\Domain\Audit\Concerns\Auditable;
use App\Domain\Audit\Contracts\MasksAuditedAttributes;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One custom field value. Plain values live in `value` (jsonb); sensitive ones only in
 * `value_encrypted`.
 *
 * @property int $id
 * @property int $employee_id
 * @property int $definition_id
 * @property mixed $value
 * @property mixed $value_encrypted
 */
#[Guarded(['id'])]
final class EmployeeCustomValue extends Model implements MasksAuditedAttributes
{
    use Auditable;

    /** @var array<string, mixed> */
    protected $attributes = ['value' => null, 'value_encrypted' => null];

    /**
     * @return BelongsTo<CustomFieldDefinition, $this>
     */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(CustomFieldDefinition::class, 'definition_id')->withTrashed();
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function resolved(): mixed
    {
        return $this->value_encrypted ?? $this->value;
    }

    public function auditMaskedAttributes(): array
    {
        return ['value_encrypted'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['value' => 'json', 'value_encrypted' => 'encrypted:json'];
    }
}
