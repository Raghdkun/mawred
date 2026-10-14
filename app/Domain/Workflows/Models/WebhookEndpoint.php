<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Models;

use App\Domain\Audit\Concerns\Auditable;
use App\Support\Ids\HasPublicUlid;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An outbound webhook target for the Webhook node. The signing secret is encrypted and never
 * serialised; it moves to the credential vault with the integrations work (Phase 4).
 *
 * @property int $id
 * @property string $ulid
 * @property string $name
 * @property string $url
 * @property string $secret
 * @property bool $is_active
 */
#[Guarded(['id', 'ulid'])]
#[Hidden(['secret'])]
final class WebhookEndpoint extends Model
{
    use Auditable;
    use HasPublicUlid;
    use SoftDeletes;

    /**
     * @return list<string>
     */
    protected function auditExcept(): array
    {
        return ['secret'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['secret' => 'encrypted', 'is_active' => 'boolean'];
    }
}
