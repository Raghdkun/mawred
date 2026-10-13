<?php

declare(strict_types=1);

namespace App\Domain\Access\Queries;

use App\Domain\Access\Models\User;
use App\Domain\Access\Support\SubjectBinding;
use Illuminate\Database\Eloquent\Builder;

/**
 * Accounts list for role assignment (screen 38). Employees-with-accounts arrive in Phase 1.
 *
 * @extends ScopedQuery<User>
 */
final class UserDirectoryQuery extends ScopedQuery
{
    private ?string $search = null;

    public function search(?string $term): self
    {
        $this->search = $term !== null && trim($term) !== '' ? trim($term) : null;

        return $this;
    }

    protected function permission(): string
    {
        return 'access.view';
    }

    protected function subject(): SubjectBinding
    {
        return SubjectBinding::user('users.id');
    }

    protected function baseQuery(): Builder
    {
        return User::query()
            ->with('roles:id,name,label')
            ->when($this->search, function (Builder $query, string $term): void {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], mb_strtolower($term)).'%';
                $query->where(fn (Builder $q) => $q->whereRaw('lower(name) like ?', [$like])->orWhereRaw('lower(email) like ?', [$like]));
            })
            ->orderBy('name')
            ->orderBy('id');
    }
}
