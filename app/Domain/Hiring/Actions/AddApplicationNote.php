<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Actions;

use App\Domain\Hiring\Enums\ApplicationEventKind;
use App\Domain\Hiring\Models\ApplicationEvent;
use App\Domain\Hiring\Models\JobApplication;
use App\Support\Actors\Actor;

/** A note on an application's timeline, visible to everyone who can see the application. */
final readonly class AddApplicationNote
{
    public function handle(Actor $actor, JobApplication $application, string $body): ApplicationEvent
    {
        $application->forceFill(['last_activity_at' => now()])->save();

        return ApplicationEvent::query()->create([
            'job_application_id' => $application->id,
            'kind' => ApplicationEventKind::Note,
            'actor_user_id' => $actor->user?->id,
            'body' => trim($body),
            'created_at' => now(),
        ]);
    }
}
