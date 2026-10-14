<?php

declare(strict_types=1);

namespace App\Domain\Policies\Actions;

use App\Domain\Organization\Support\ReferenceValues;
use App\Domain\Policies\Models\PolicySet;
use App\Support\Actors\Actor;

/**
 * Creates a set or renames one. Values are changed through SetPolicyValues.
 */
final readonly class SavePolicySet
{
    /**
     * @param  array<string, string|null>  $name
     * @param  array<string, string|null>  $description
     */
    public function handle(Actor $actor, array $name, array $description = [], ?PolicySet $set = null): PolicySet
    {
        $set ??= new PolicySet(['created_by' => $actor->user?->id]);
        $set->setTranslations('name', ReferenceValues::translations($name));
        $set->setTranslations('description', ReferenceValues::translations($description));
        $set->save();

        return $set;
    }
}
