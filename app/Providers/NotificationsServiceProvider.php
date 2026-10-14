<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Notifications\Listeners\DeliverWorkflowMessage;
use App\Domain\Notifications\Listeners\SendRequestOutcome;
use App\Domain\Notifications\Listeners\SendTaskNotifications;
use App\Domain\Notifications\Support\NotificationPreferences;
use App\Domain\Workflows\Events\InstanceFinished;
use App\Domain\Workflows\Events\TaskAssigned;
use App\Domain\Workflows\Events\WorkflowNotificationRequested;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Notifications wiring (ADR-0016): workflow events become in-app and email messages.
 */
final class NotificationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(NotificationPreferences::class);
    }

    public function boot(): void
    {
        Event::listen(TaskAssigned::class, SendTaskNotifications::class);
        Event::listen(InstanceFinished::class, SendRequestOutcome::class);
        Event::listen(WorkflowNotificationRequested::class, DeliverWorkflowMessage::class);
    }
}
