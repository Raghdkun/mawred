<?php

declare(strict_types=1);

namespace App\Domain\Policies\Actions;

use App\Domain\Policies\Enums\PolicyLevel;
use App\Domain\Policies\Models\LeaveType;
use App\Domain\Policies\Models\PolicyAssignment;
use App\Domain\Policies\Models\PolicySet;
use App\Domain\Policies\Support\PolicyKeys;
use App\Domain\Policies\Support\PolicyPresets;
use App\Support\Actors\Actor;

/**
 * Production defaults (brief §21, D48/D49): the leave type catalogue, the Neutral default set and
 * its global assignment. Idempotent: never touches anything HR already changed.
 */
final readonly class SeedPolicyDefaults
{
    public const string SINCE = '2000-01-01';

    public function __construct(
        private PolicyKeys $keys,
        private ImportPolicyPreset $import,
        private AssignPolicySet $assign,
    ) {}

    public function handle(): void
    {
        foreach (PolicyPresets::LEAVE_TYPES as $index => $type) {
            if (! LeaveType::withTrashed()->where('code', $type['code'])->exists()) {
                LeaveType::query()->create([...array_intersect_key($type, ['code' => 1, 'name' => 1, 'unit' => 1]),
                    'is_paid' => $type['isPaid'],
                    'allows_half_days' => $type['allowsHalfDays'],
                    'eligible_gender' => $type['eligibleGender'],
                    'requires_attachment' => $type['requiresAttachment'],
                    'sort_order' => ($index + 1) * 10,
                ]);
            }
        }

        $this->keys->forget();

        if (PolicySet::withTrashed()->where('preset_key', PolicyPresets::NEUTRAL_KEY)->exists()) {
            return;
        }

        $actor = Actor::system('policy-defaults');
        $set = $this->import->handle($actor, [
            'format' => PolicyPresets::FORMAT,
            'key' => PolicyPresets::NEUTRAL_KEY,
            'version' => PolicyPresets::NEUTRAL_VERSION,
            'name' => ['en' => 'Neutral default', 'ar' => 'الإعداد الافتراضي المحايد'],
            'description' => [
                'en' => 'Generic starting values, not tied to any country\'s law. Review before go-live.',
                'ar' => 'قيم بداية عامة غير مرتبطة بقانون أي دولة. راجعها قبل التشغيل.',
            ],
            'values' => PolicyPresets::neutralValues($this->keys),
        ], self::SINCE);

        if (! PolicyAssignment::query()->where('level', PolicyLevel::Global->value)->exists()) {
            $this->assign->handle($actor, $set, PolicyLevel::Global, null, self::SINCE, __('Default policies'));
        }
    }
}
