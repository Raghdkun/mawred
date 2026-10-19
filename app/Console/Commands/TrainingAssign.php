<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Training\Enums\CourseStatus;
use App\Domain\Training\Jobs\ApplyCourseAudienceJob;
use App\Domain\Training\Models\Course;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('training:assign')]
#[Description('Assign mandatory courses to everyone in their audience (D100).')]
final class TrainingAssign extends Command
{
    public function handle(): int
    {
        $count = 0;

        foreach (Course::query()->where('status', CourseStatus::Published->value)->whereNotNull('audience')->get(['id']) as $course) {
            dispatch(new ApplyCourseAudienceJob($course->id));
            $count++;
        }

        $this->info("Queued {$count} mandatory course(s).");

        return self::SUCCESS;
    }
}
