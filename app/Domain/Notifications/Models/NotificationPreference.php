<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Model;

/**
 * One switched channel for one category. Written with upsert (composite key), never `save()`.
 *
 * @property int $user_id
 * @property string $category
 * @property string $channel
 * @property bool $enabled
 */
#[Guarded([])]
#[WithoutIncrementing]
final class NotificationPreference extends Model
{
    protected $primaryKey = 'user_id';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }
}
