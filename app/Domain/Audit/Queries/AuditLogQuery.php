<?php

declare(strict_types=1);

namespace App\Domain\Audit\Queries;

use App\Domain\Access\Queries\ScopedQuery;
use App\Domain\Access\Support\SubjectBinding;
use App\Domain\Audit\Models\Activity;
use Illuminate\Database\Eloquent\Builder;

/**
 * Audit entries visible to a user (screen 43). With the Own scope a user sees what they did.
 *
 * @extends ScopedQuery<Activity>
 */
final class AuditLogQuery extends ScopedQuery
{
    protected function permission(): string
    {
        return 'audit.view';
    }

    protected function subject(): SubjectBinding
    {
        return SubjectBinding::user('causer_id');
    }

    protected function baseQuery(): Builder
    {
        return Activity::query()->latest('created_at')->latest('id');
    }
}
