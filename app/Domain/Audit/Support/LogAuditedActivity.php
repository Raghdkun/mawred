<?php

declare(strict_types=1);

namespace App\Domain\Audit\Support;

use App\Domain\Audit\Contracts\MasksAuditedAttributes;
use App\Support\Actors\ActorType;
use App\Support\Actors\Channel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Context;
use Spatie\Activitylog\Actions\LogActivityAction;

/**
 * Replaces the package's logging action to (1) mask sensitive attributes before they are stored
 * and (2) stamp every row with channel, actor type, impersonator, client and request id.
 */
final class LogAuditedActivity extends LogActivityAction
{
    public const string MASK = '••••';

    protected function transformChanges(Model $activity): void
    {
        $subject = $activity->getAttribute('subject');
        $masked = $subject instanceof MasksAuditedAttributes ? $subject->auditMaskedAttributes() : [];

        if ($masked === []) {
            return;
        }

        $changes = $activity->getAttribute('attribute_changes');

        if (! $changes instanceof Collection) {
            return;
        }

        $activity->setAttribute('attribute_changes', $changes->map(
            fn (mixed $values): mixed => is_array($values) ? $this->mask($values, $masked) : $values,
        ));
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @param  list<string>  $masked
     * @return array<array-key, mixed>
     */
    private function mask(array $values, array $masked): array
    {
        foreach ($masked as $attribute) {
            if (array_key_exists($attribute, $values)) {
                $values[$attribute] = self::MASK;
            }
        }

        return $values;
    }

    protected function beforeActivityLogged(Model $activity): void
    {
        parent::beforeActivityLogged($activity);

        $context = AuditContext::current();

        $activity->setAttribute('channel', $context['channel'] ?? (app()->runningInConsole() ? Channel::System->value : Channel::Web->value));
        $activity->setAttribute('actor_type', $context['actor_type'] ?? ($activity->getAttribute('causer_id') !== null ? ActorType::User->value : ActorType::System->value));
        $activity->setAttribute('impersonator_id', $context['impersonator_id'] ?? null);
        $activity->setAttribute('ip', $context['ip'] ?? null);
        $activity->setAttribute('user_agent_hash', $context['user_agent_hash'] ?? null);
        $activity->setAttribute('request_id', Context::get('request_id'));
    }
}
