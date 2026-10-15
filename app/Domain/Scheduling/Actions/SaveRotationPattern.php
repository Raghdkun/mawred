<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Actions;

use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Support\ReferenceValues;
use App\Domain\Scheduling\Models\RotationPattern;
use App\Domain\Scheduling\Models\ShiftTemplate;

/** A rotation: a cycle of shift templates (by ULID) and days off (null). */
final readonly class SaveRotationPattern
{
    /**
     * @param  array{en: string, ar?: string|null}  $name
     * @param  list<string|null>  $days
     */
    public function handle(array $name, array $days, ?Branch $branch, ?RotationPattern $pattern = null): RotationPattern
    {
        $ids = [];

        foreach (ShiftTemplate::query()->whereIn('ulid', array_filter($days))->get(['id', 'ulid']) as $template) {
            $ids[$template->ulid] = $template->id;
        }

        $pattern ??= new RotationPattern;
        $pattern->setTranslations('name', ReferenceValues::translations($name));
        $pattern->fill([
            'branch_id' => $branch?->id,
            'days' => array_map(fn (?string $ulid): ?int => $ulid === null ? null : $ids[$ulid] ?? null, $days),
        ])->save();

        return $pattern;
    }
}
