<?php

declare(strict_types=1);

namespace App\Support\Ids;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Public identifier for business records (ADR-0003).
 *
 * The bigint `id` stays internal for joins and FKs; the `ulid` column is the route key and the
 * only identifier that is ever serialised to clients.
 *
 * @mixin Model
 */
trait HasPublicUlid
{
    public static function bootHasPublicUlid(): void
    {
        static::creating(function (Model $model): void {
            if (blank($model->getAttribute('ulid'))) {
                $model->setAttribute('ulid', Str::lower((string) Str::ulid()));
            }
        });
    }

    public function initializeHasPublicUlid(): void
    {
        $this->mergeHidden([$this->getKeyName()]);
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
