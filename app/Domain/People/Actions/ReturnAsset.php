<?php

declare(strict_types=1);

namespace App\Domain\People\Actions;

use App\Domain\People\Models\AssetAssignment;
use App\Support\Errors\DomainRuleViolation;

final readonly class ReturnAsset
{
    public function handle(AssetAssignment $assignment, string $returnedOn, ?string $notes = null): AssetAssignment
    {
        if ($returnedOn < $assignment->assigned_on->toDateString()) {
            throw new DomainRuleViolation('people.dates_reversed', __('The return date cannot be before it was handed over.'), ['returnedOn' => [__('The return date cannot be before it was handed over.')]]);
        }

        $assignment->fill(['returned_on' => $returnedOn, 'condition_notes' => $notes ?? $assignment->condition_notes])->save();

        return $assignment;
    }
}
