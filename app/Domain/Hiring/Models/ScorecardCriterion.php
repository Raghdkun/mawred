<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Models;

use App\Support\Ids\HasPublicUlid;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 * What interviewers rate a candidate on (D90). Criteria without a requisition are the defaults
 * each new requisition copies.
 *
 * @property int $id
 * @property string $ulid
 * @property int|null $job_requisition_id
 * @property array<string, string>|string $name
 * @property int $position
 */
#[Guarded(['id', 'ulid'])]
final class ScorecardCriterion extends Model
{
    use HasPublicUlid;
    use HasTranslations;

    protected $table = 'scorecard_criteria';

    /** @var list<string> */
    public array $translatable = ['name'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['position' => 'integer'];
    }
}
