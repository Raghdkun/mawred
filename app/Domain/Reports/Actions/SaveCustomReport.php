<?php

declare(strict_types=1);

namespace App\Domain\Reports\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Reports\Data\CustomReportInputData;
use App\Domain\Reports\Models\SavedReport;
use App\Domain\Reports\Support\DefinitionValidator;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Database\ConnectionInterface;

/**
 * Saves a custom report from the builder (D118): the definition is checked against its dataset
 * and the builder's own field permissions before it's stored.
 */
final readonly class SaveCustomReport
{
    public function __construct(private ConnectionInterface $db, private DefinitionValidator $validator) {}

    public function handle(Actor $actor, CustomReportInputData $input, ?SavedReport $report = null): SavedReport
    {
        $user = $actor->user;

        if (! $user instanceof User) {
            throw new DomainRuleViolation('reports.forbidden', __('You can’t build reports.'), status: 403);
        }

        $definition = $this->validator->validate($input->definition, $user);

        return $this->db->transaction(function () use ($user, $input, $report, $definition): SavedReport {
            $report ??= new SavedReport(['owner_user_id' => $user->id]);
            $report->fill([
                'name' => trim($input->name),
                'description' => $input->description === null || trim($input->description) === '' ? null : trim($input->description),
                'dataset' => $definition->dataset,
                'definition' => $definition->toArray(),
            ])->save();

            return $report;
        });
    }
}
