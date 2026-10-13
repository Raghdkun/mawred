<?php

declare(strict_types=1);

namespace App\Support\Ids;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Public identifier for business records (ADR-0003).
 *
 * The bigint `id` stays the primary key for joins and FKs; the `ulid` column is assigned on
 * insert (by Eloquent itself, not an event listener, so it also works with Event::fake), is the
 * route key, and is the only identifier ever serialised to clients. Malformed ULIDs in routes
 * resolve to 404 without touching the database.
 *
 * @mixin Model
 */
trait HasPublicUlid
{
    use HasUlids;

    public function initializeHasPublicUlid(): void
    {
        $this->mergeHidden([$this->getKeyName()]);
    }

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * @param  Builder<static>  $query
     */
    protected function scopeWhereUlid(Builder $query, string $ulid): void
    {
        $query->where($this->qualifyColumn('ulid'), Str::lower($ulid));
    }
}
