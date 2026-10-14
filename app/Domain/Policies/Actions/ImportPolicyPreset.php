<?php

declare(strict_types=1);

namespace App\Domain\Policies\Actions;

use App\Domain\Policies\Data\LeaveTypeInputData;
use App\Domain\Policies\Models\LeaveType;
use App\Domain\Policies\Models\PolicySet;
use App\Domain\Policies\Support\PolicyKeys;
use App\Domain\Policies\Support\PolicyPresets;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Database\ConnectionInterface;

/**
 * Imports a `mawred.policy-preset/v1` document as a new policy set (leave types it mentions are
 * created when missing). Every value is validated against the key registry first.
 */
final readonly class ImportPolicyPreset
{
    public function __construct(
        private ConnectionInterface $db,
        private PolicyKeys $keys,
        private SaveLeaveType $saveLeaveType,
        private SetPolicyValues $setValues,
    ) {}

    /**
     * @param  array<string, mixed>  $preset
     */
    public function handle(Actor $actor, array $preset, string $effectiveFrom): PolicySet
    {
        if (($preset['format'] ?? null) !== PolicyPresets::FORMAT || ! is_array($preset['values'] ?? null) || ! is_array($preset['name'] ?? null)) {
            throw new DomainRuleViolation('policies.preset_invalid', __('This file is not a Mawred policy preset.'), ['file' => [__('This file is not a Mawred policy preset.')]]);
        }

        return $this->db->transaction(function () use ($actor, $preset, $effectiveFrom): PolicySet {
            foreach (is_array($preset['leaveTypes'] ?? null) ? $preset['leaveTypes'] : [] as $type) {
                if (is_array($type) && is_string($type['code'] ?? null) && ! LeaveType::withTrashed()->where('code', mb_strtoupper($type['code']))->exists()) {
                    $this->saveLeaveType->handle(LeaveTypeInputData::validateAndCreate($type));
                }
            }

            $this->keys->forget();

            /** @var array<string, string> $name */
            $name = $preset['name'];
            /** @var array<string, string> $description */
            $description = is_array($preset['description'] ?? null) ? $preset['description'] : [];

            $set = PolicySet::query()->create([
                'name' => $name,
                'description' => $description === [] ? null : $description,
                'preset_key' => is_string($preset['key'] ?? null) ? $preset['key'] : null,
                'preset_version' => is_string($preset['version'] ?? null) ? $preset['version'] : null,
                'created_by' => $actor->user?->id,
            ]);

            /** @var array<string, array<string, mixed>|null> $values */
            $values = $preset['values'];
            $this->setValues->handle($actor, $set, $values, $effectiveFrom, __('Imported preset'));

            return $set;
        });
    }
}
