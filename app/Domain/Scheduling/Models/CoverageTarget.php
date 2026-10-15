<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;

/**
 * How many people a branch needs on a weekday, for the roster's coverage heatmap row.
 *
 * @property int $id
 * @property int $branch_id
 * @property int $weekday ISO 1–7
 * @property int $required
 */
#[Guarded(['id'])]
final class CoverageTarget extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['weekday' => 'integer', 'required' => 'integer'];
    }
}
