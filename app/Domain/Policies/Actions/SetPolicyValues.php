<?php

declare(strict_types=1);

namespace App\Domain\Policies\Actions;

use App\Domain\Policies\Keys\PolicyKey;
use App\Domain\Policies\Models\PolicySet;
use App\Domain\Policies\Models\PolicyValue;
use App\Domain\Policies\Support\PolicyEpoch;
use App\Domain\Policies\Support\PolicyKeys;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;

/**
 * Changes keys in a set from a date (ADR-0010): the row in force is closed the day before and a
 * new row starts, so past dates keep their values. `null` removes the key from the set from that
 * date. Back-dating needs a reason (it will trigger recalculation once ledgers exist).
 */
final readonly class SetPolicyValues
{
    public function __construct(
        private ConnectionInterface $db,
        private PolicyKeys $keys,
        private PolicyEpoch $epoch,
    ) {}

    /**
     * @param  array<string, array<string, mixed>|null>  $changes  key id => value (null = remove)
     */
    public function handle(Actor $actor, PolicySet $set, array $changes, string $effectiveFrom, ?string $reason = null): void
    {
        $today = CarbonImmutable::now('UTC')->toDateString();

        if ($effectiveFrom < $today && ($reason === null || trim($reason) === '')) {
            throw new DomainRuleViolation('policies.backdate_reason', __('Changes that start in the past need a reason.'), ['reason' => [__('Changes that start in the past need a reason.')]]);
        }

        $validated = [];
        $errors = [];

        foreach ($changes as $id => $value) {
            $key = $this->keys->find($id);

            if (! $key instanceof PolicyKey) {
                $errors["values.{$id}"] = [__('Unknown policy key.')];

                continue;
            }

            if ($value === null) {
                $validated[$id] = null;

                continue;
            }

            try {
                $validated[$id] = $key->serialize($key->validate($value));
            } catch (ValidationException $e) {
                foreach ($e->errors() as $field => $messages) {
                    $errors["values.{$id}.{$field}"] = array_values(array_filter((array) $messages, is_string(...)));
                }
            }
        }

        if ($errors !== []) {
            throw new DomainRuleViolation('policies.values_invalid', __('Some values need attention.'), $errors);
        }

        $this->db->transaction(function () use ($actor, $set, $validated, $effectiveFrom, $reason): void {
            foreach ($validated as $id => $value) {
                $rows = PolicyValue::query()->where('policy_set_id', $set->id)->where('key', $id)->lockForUpdate()->get();

                if ($rows->contains(fn (PolicyValue $row): bool => $row->effective_from->toDateString() > $effectiveFrom)) {
                    throw new DomainRuleViolation('policies.later_change_exists', __('A later change to this value is already scheduled. Edit that one instead.'), ["values.{$id}" => [__('A later change to this value is already scheduled. Edit that one instead.')]]);
                }

                $open = $rows->first(fn (PolicyValue $row): bool => $row->coversDate($effectiveFrom));

                if ($open !== null) {
                    if ($open->effective_from->toDateString() === $effectiveFrom) {
                        $open->delete();
                    } else {
                        $open->forceFill(['effective_to' => CarbonImmutable::parse($effectiveFrom)->subDay()->toDateString()])->save();
                    }
                }

                if ($value !== null) {
                    PolicyValue::query()->create([
                        'policy_set_id' => $set->id,
                        'key' => $id,
                        'value' => $value,
                        'effective_from' => $effectiveFrom,
                        'reason' => $reason,
                        'created_by' => $actor->user?->id,
                    ]);
                }
            }

            activity('policies')->causedBy($actor->user)->performedOn($set)->event('values.changed')
                ->withProperties(['from' => $effectiveFrom, 'reason' => $reason, 'values' => $validated])
                ->log('Policy values changed');
        });

        $this->epoch->bump();
    }
}
