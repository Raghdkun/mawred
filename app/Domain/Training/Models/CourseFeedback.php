<?php

declare(strict_types=1);

namespace App\Domain\Training\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * A learner's rating (1–5) and comment on a course they completed (D109).
 *
 * @property int $id
 * @property int $enrollment_id
 * @property int $course_id
 * @property int $rating
 * @property string|null $comment
 * @property CarbonImmutable|null $created_at
 */
#[Guarded(['id'])]
#[Table(name: 'course_feedback')]
final class CourseFeedback extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }
}
