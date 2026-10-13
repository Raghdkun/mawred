<?php

declare(strict_types=1);

namespace App\Domain\People\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Access\Support\ScopeResolver;
use App\Domain\Access\Support\SubjectBinding;
use App\Domain\Organization\Models\Branch;
use App\Domain\People\Enums\EmployeeStatus;
use App\Domain\People\Models\Employee;
use App\Domain\People\Models\EmployeeDocument;
use App\Domain\People\Notifications\DocumentExpiringNotification;
use App\Support\Time\LocalClock;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Collection;

/**
 * Daily: for each document whose expiry is within one of its type's reminder thresholds (and on
 * the day after it expires), notify the employee, their manager, and the HR users whose
 * `people.update` scope covers them. Each threshold fires once per expiry date.
 */
final readonly class SendDocumentExpiryReminders
{
    public function __construct(private LocalClock $clock, private ScopeResolver $resolver) {}

    public function handle(): int
    {
        $sent = 0;
        $horizon = CarbonImmutable::now('UTC')->addDays(366)->toDateString();
        $hr = $this->hrCandidates();

        EmployeeDocument::query()
            ->with(['type', 'employee.user', 'employee.currentManager.user'])
            ->whereNotNull('expires_on')
            ->where('expires_on', '<=', $horizon)
            ->whereHas('employee', fn (Builder $q) => $q->where('status', '!=', EmployeeStatus::Terminated->value))
            ->orderBy('id')
            ->chunkById(500, function (Collection $documents) use (&$sent, $hr): void {
                foreach ($documents as $document) {
                    $sent += $this->remind($document, $hr);
                }
            });

        return $sent;
    }

    /**
     * @param  Collection<int, User>  $hr
     */
    private function remind(EmployeeDocument $document, Collection $hr): int
    {
        $employee = $document->employee;
        $type = $document->type;

        if (! $employee instanceof Employee || $type === null || $document->expires_on === null) {
            return 0;
        }

        $timezone = Branch::withTrashed()->whereKey($employee->current_branch_id)->value('timezone');
        $today = CarbonImmutable::parse($this->clock->today(is_string($timezone) ? $timezone : 'UTC'));
        $daysLeft = (int) $today->diffInDays($document->expires_on, false);

        // Thresholds are "N days before"; -1 marks the "has expired" reminder.
        $due = array_values(array_filter([...$type->reminder_days, -1], fn (int $days): bool => $daysLeft <= $days && ! in_array($days, $document->reminders_sent, true)));

        if ($due === []) {
            return 0;
        }

        $recipients = collect([$employee->user, $employee->currentManager?->user])
            ->filter()
            ->merge($hr->filter(fn (User $user): bool => $this->resolver->allows($user, 'people.update', $employee, SubjectBinding::employee('employees.id'))))
            ->unique('id');

        foreach ($recipients as $user) {
            /** @var User $user */
            $user->notify(DocumentExpiringNotification::for($document, $daysLeft, $user->is($employee->user), $user->locale));
        }

        // Every threshold already passed is marked, so a late first run sends one reminder, not several.
        $document->forceFill(['reminders_sent' => array_values(array_unique([...$document->reminders_sent, ...$due]))])->saveQuietly();

        return $recipients->count();
    }

    /**
     * @return Collection<int, User>
     */
    private function hrCandidates(): Collection
    {
        return User::query()
            ->where('status', 'active')
            ->whereHas('roles.permissions', fn (Builder $q) => $q->where('name', 'people.update'))
            ->get();
    }
}
