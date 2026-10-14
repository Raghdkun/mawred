<?php

declare(strict_types=1);

namespace App\Domain\Policies\Models;

use App\Domain\Audit\Concerns\Auditable;
use App\Support\Ids\HasPublicUlid;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

/**
 * Leave type catalogue (D48). Rules per type live in policy sets as `leave.type.<CODE>`.
 *
 * @property int $id
 * @property string $ulid
 * @property string $code
 * @property array<string, string>|string $name
 * @property string $unit days|hours
 * @property bool $is_paid
 * @property bool $allows_half_days
 * @property string|null $eligible_gender
 * @property bool $requires_attachment
 * @property int $sort_order
 * @property bool $is_active
 */
#[Guarded(['id', 'ulid'])]
final class LeaveType extends Model
{
    use Auditable;
    use HasPublicUlid;
    use HasTranslations;
    use SoftDeletes;

    /** @var list<string> */
    public array $translatable = ['name'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'unit' => 'days',
        'is_paid' => true,
        'allows_half_days' => true,
        'eligible_gender' => null,
        'requires_attachment' => false,
        'sort_order' => 0,
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_paid' => 'boolean', 'allows_half_days' => 'boolean', 'requires_attachment' => 'boolean', 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }
}
