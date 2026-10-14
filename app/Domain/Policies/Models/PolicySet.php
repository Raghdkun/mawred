<?php

declare(strict_types=1);

namespace App\Domain\Policies\Models;

use App\Domain\Audit\Concerns\Auditable;
use App\Support\Ids\HasPublicUlid;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

/**
 * A named, partial set of policy values (ADR-0010). It only defines the keys it needs; anything
 * else comes from less specific levels.
 *
 * @property int $id
 * @property string $ulid
 * @property array<string, string>|string $name
 * @property array<string, string>|string|null $description
 * @property string|null $preset_key
 * @property string|null $preset_version
 * @property int|null $created_by
 */
#[Guarded(['id', 'ulid'])]
final class PolicySet extends Model
{
    use Auditable;
    use HasPublicUlid;
    use HasTranslations;
    use SoftDeletes;

    /** @var list<string> */
    public array $translatable = ['name', 'description'];

    /** @var array<string, mixed> */
    protected $attributes = ['description' => null, 'preset_key' => null, 'preset_version' => null, 'created_by' => null];

    /**
     * @return HasMany<PolicyValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(PolicyValue::class);
    }

    /**
     * @return HasMany<PolicyAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(PolicyAssignment::class);
    }
}
