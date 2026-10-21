<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Models;

use App\Domain\Audit\Concerns\Auditable;
use App\Support\Ids\HasPublicUlid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;

/**
 * An address Mawred no longer emails (D127): it bounced hard or was marked as spam.
 *
 * @property int $id
 * @property string $ulid
 * @property string $email lowercased
 * @property string $reason hard_bounce|spam_complaint
 * @property string|null $detail
 * @property string $source
 * @property CarbonImmutable $created_at
 */
#[Guarded(['id', 'ulid'])]
final class EmailSuppression extends Model
{
    use Auditable;
    use HasPublicUlid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }
}
