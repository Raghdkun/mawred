<?php

declare(strict_types=1);

namespace App\Domain\Training\Listeners;

use App\Domain\People\Events\EmployeeHired;
use App\Domain\People\Events\EmployeePlacementChanged;
use App\Domain\Training\Jobs\ApplyAudiencesForEmployeeJob;

/** New joiners and people who move get the mandatory courses their new place calls for (D100). */
final readonly class AssignMandatoryCourses
{
    public function handle(EmployeeHired|EmployeePlacementChanged $event): void
    {
        dispatch(new ApplyAudiencesForEmployeeJob($event->employee->id));
    }
}
