<?php

declare(strict_types=1);

namespace App\Domain\Policies\Actions;

use App\Domain\Policies\Keys\LeaveRuleKey;
use App\Domain\Policies\Models\LeaveType;
use App\Domain\Policies\Models\PolicySet;
use App\Domain\Policies\Models\PolicyValue;
use App\Domain\Policies\Support\PolicyPresets;
use Illuminate\Contracts\Database\Query\Builder;

/**
 * Exports a set's values in force on a date as `mawred.policy-preset/v1`, with the leave types
 * its rules mention, so it can be imported into another Mawred.
 */
final readonly class ExportPolicyPreset
{
    /**
     * @return array<string, mixed>
     */
    public function handle(PolicySet $set, string $date): array
    {
        $values = PolicyValue::query()
            ->where('policy_set_id', $set->id)
            ->where('effective_from', '<=', $date)
            ->where(fn (Builder $q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date))
            ->orderBy('key')
            ->get()
            ->mapWithKeys(fn (PolicyValue $value): array => [$value->key => $value->value])
            ->all();

        $codes = [];
        foreach (array_keys($values) as $key) {
            if (str_starts_with($key, LeaveRuleKey::PREFIX)) {
                $codes[] = substr($key, strlen(LeaveRuleKey::PREFIX));
            }
        }

        return [
            'format' => PolicyPresets::FORMAT,
            'version' => $set->preset_version ?? '1.0.0',
            'key' => $set->preset_key,
            'name' => $set->getTranslations('name'),
            'description' => $set->getTranslations('description'),
            'exportedAt' => now()->toIso8601String(),
            'leaveTypes' => LeaveType::query()->whereIn('code', $codes)->orderBy('sort_order')->get()->map(fn (LeaveType $type): array => [
                'code' => $type->code,
                'name' => $type->getTranslations('name'),
                'unit' => $type->unit,
                'isPaid' => $type->is_paid,
                'allowsHalfDays' => $type->allows_half_days,
                'eligibleGender' => $type->eligible_gender,
                'requiresAttachment' => $type->requires_attachment,
            ])->values()->all(),
            'values' => $values,
        ];
    }
}
