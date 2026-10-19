<?php

declare(strict_types=1);

namespace App\Domain\Reports\Support;

use App\Domain\Access\Models\User;
use App\Domain\Reports\Models\ReportRun;
use Illuminate\Database\Eloquent\Collection;

/** A person's own exports: deliberately not scoped, since nobody sees anyone else's (D114). */
final class MyExports
{
    public const int LIMIT = 50;

    /**
     * @return Collection<int, ReportRun>
     */
    public static function for(User $user): Collection
    {
        return ReportRun::query()->where('user_id', $user->id)->latest()->orderByDesc('id')->limit(self::LIMIT)->get();
    }

    public static function pending(User $user): bool
    {
        return ReportRun::query()->where('user_id', $user->id)->whereIn('status', ['queued', 'running'])->exists();
    }
}
