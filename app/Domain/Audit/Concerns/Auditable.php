<?php

declare(strict_types=1);

namespace App\Domain\Audit\Concerns;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Every write to a business model is audited with old/new values (ADR-0006). Secrets and noise
 * are never logged; models holding sensitive values also implement MasksAuditedAttributes.
 *
 * Override `auditExcept()` to exclude additional attributes entirely.
 *
 * @mixin Model
 */
trait Auditable
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('model')
            ->logAll()
            ->logExcept([
                'id', 'created_at', 'updated_at', 'password', 'remember_token',
                'two_factor_secret', 'two_factor_recovery_codes',
                ...$this->auditExcept(),
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return list<string>
     */
    protected function auditExcept(): array
    {
        return [];
    }
}
