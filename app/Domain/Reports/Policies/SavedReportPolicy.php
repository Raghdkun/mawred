<?php

declare(strict_types=1);

namespace App\Domain\Reports\Policies;

use App\Domain\Access\Models\User;
use App\Domain\Access\Support\ScopeResolver;
use App\Domain\Reports\Models\SavedReport;
use App\Domain\Reports\Support\SavedReportAccess;

/**
 * Saved custom reports (D118, D119): building needs `reports.create`; the owner edits, shares and
 * deletes theirs (or anyone with `reports.delete`, to tidy up); opening one needs it to be yours
 * or shared with you. What its rows show is still the viewer's own scope.
 */
final readonly class SavedReportPolicy
{
    public function __construct(private ScopeResolver $resolver) {}

    public function create(User $user): bool
    {
        return $this->resolver->can($user, 'reports.view') && $this->resolver->can($user, 'reports.create');
    }

    public function view(User $user, SavedReport $report): bool
    {
        return $this->resolver->can($user, 'reports.view') && SavedReportAccess::visibleTo($user)->whereKey($report->id)->exists();
    }

    public function update(User $user, SavedReport $report): bool
    {
        return $report->owner_user_id === $user->id && $this->create($user);
    }

    public function share(User $user, SavedReport $report): bool
    {
        return $this->update($user, $report);
    }

    public function delete(User $user, SavedReport $report): bool
    {
        return $report->owner_user_id === $user->id || $this->resolver->can($user, 'reports.delete');
    }
}
