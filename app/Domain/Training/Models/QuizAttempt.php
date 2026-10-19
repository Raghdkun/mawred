<?php

declare(strict_types=1);

namespace App\Domain\Training\Models;

use App\Support\Ids\HasPublicUlid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;

/**
 * One go at a quiz (D102): the answers given, the score in percent and whether it passed.
 *
 * @property int $id
 * @property string $ulid
 * @property int $enrollment_id
 * @property int $course_module_id
 * @property array<string, list<string>> $answers
 * @property int $score
 * @property bool $passed
 * @property CarbonImmutable $submitted_at
 */
#[Guarded(['id', 'ulid'])]
#[WithoutTimestamps]
final class QuizAttempt extends Model
{
    use HasPublicUlid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'passed' => 'boolean',
            'submitted_at' => 'immutable_datetime',
        ];
    }
}
