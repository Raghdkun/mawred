<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Support;

use App\Domain\Access\Models\User;
use App\Domain\Notifications\Enums\NotificationCategory;
use App\Domain\Notifications\Support\NotificationMessage;
use App\Domain\Notifications\Support\NotificationRouter;
use App\Domain\People\Models\Employee;

/** Roster messages (category Schedule): published rosters, changed shifts, open-shift outcomes. */
final readonly class RosterNotifier
{
    public function __construct(private NotificationRouter $router) {}

    /**
     * @param  callable(string $locale): array{subject: string, body: string}  $text
     */
    public function tell(Employee $employee, string $event, string $sourceKey, callable $text, ?string $url = null): void
    {
        $user = $employee->user_id === null ? null : User::query()->find($employee->user_id);

        if (! $user instanceof User) {
            return;
        }

        $this->router->send($user, new NotificationMessage(
            event: $event,
            category: NotificationCategory::Schedule,
            content: fn (string $channel, string $locale): array => $text($locale),
            url: $url ?? route('schedule.mine'),
            sourceKey: $sourceKey,
        ));
    }
}
