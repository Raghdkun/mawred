<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Models;

use App\Domain\Audit\Concerns\Auditable;
use App\Support\Ids\HasPublicUlid;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 * A repeating cycle of shift templates and days off (e.g. 4 on, 3 off), applied to people from a
 * start date.
 *
 * @property int $id
 * @property string $ulid
 * @property int|null $branch_id
 * @property array<string, string>|string $name
 * @property list<int|null> $days shift template id per cycle day; null = day off
 */
#[Guarded(['id', 'ulid'])]
final class RotationPattern extends Model
{
    use Auditable;
    use HasPublicUlid;
    use HasTranslations;

    /** @var list<string> */
    public array $translatable = ['name'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['days' => 'array'];
    }
}
