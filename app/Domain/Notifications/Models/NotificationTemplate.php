<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Models;

use App\Domain\Audit\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;

/**
 * An admin's override of a notification template (ADR-0016). Rendered only by SafeTemplate.
 *
 * @property int $id
 * @property string $event
 * @property string $channel
 * @property string $locale
 * @property string|null $subject
 * @property string $body
 * @property int|null $updated_by
 */
#[Guarded(['id'])]
final class NotificationTemplate extends Model
{
    use Auditable;
}
