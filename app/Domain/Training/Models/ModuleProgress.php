<?php

declare(strict_types=1);

namespace App\Domain\Training\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;

/**
 * A module someone finished: read, watched, downloaded, or a quiz they passed.
 *
 * @property int $id
 * @property int $enrollment_id
 * @property int $course_module_id
 * @property CarbonImmutable $completed_at
 */
#[Guarded(['id'])]
#[Table(name: 'module_progress')]
#[WithoutTimestamps]
final class ModuleProgress extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['completed_at' => 'immutable_datetime'];
    }
}
