<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Support;

use App\Domain\Access\Models\User;
use App\Domain\Hiring\Models\PipelineStage;

/** Names for the people on stage automation panels (D93), for the editor's chips. */
final class AutomationPeople
{
    /**
     * @param  iterable<PipelineStage>  $stages
     * @return array<string, string> user ULID => name
     */
    public static function for(iterable $stages): array
    {
        $ids = [];

        foreach ($stages as $stage) {
            foreach ($stage->automation['booking']['panel'] ?? [] as $ulid) {
                $ids[] = $ulid;
            }
        }

        if ($ids === []) {
            return [];
        }

        $names = [];

        foreach (User::query()->whereIn('ulid', array_unique($ids))->get(['ulid', 'name']) as $user) {
            $names[$user->ulid] = $user->name;
        }

        return $names;
    }
}
