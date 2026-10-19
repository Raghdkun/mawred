<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\People\Events\EmployeeHired;
use App\Domain\People\Events\EmployeePlacementChanged;
use App\Domain\Training\Events\EnrollmentCompleted;
use App\Domain\Training\Listeners\AssignMandatoryCourses;
use App\Domain\Training\Listeners\IssueCertificates;
use App\Domain\Training\Listeners\SettleEnrollments;
use App\Domain\Training\Models\Enrollment;
use App\Domain\Training\Policies\TrainingPolicy;
use App\Domain\Training\Support\EnrollmentSummary;
use App\Domain\Training\Workflows\ApplyTrainingRequest;
use App\Domain\Workflows\Engine\RecordUpdates;
use App\Domain\Workflows\Engine\SubjectSummaries;
use App\Domain\Workflows\Events\InstanceFinished;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Training wiring (Phase 7): settling enrolment requests when their workflow ends, enrolment
 * requests in the approvals inbox, mandatory courses for joiners and movers, certificates on
 * completion, approved training requests onto the training record (D105), and the gates.
 */
final class TrainingServiceProvider extends ServiceProvider
{
    private const array ABILITIES = ['browse', 'enrol', 'manage', 'assign', 'viewRecord', 'viewEnrollment', 'manageEnrollment', 'viewCertificate', 'revokeCertificate', 'updateExternal', 'viewExternal', 'linkExternal'];

    public function register(): void
    {
        $this->app->afterResolving(RecordUpdates::class, function (RecordUpdates $updates): void {
            $updates->register($this->app->make(ApplyTrainingRequest::class));
        });

        $this->app->afterResolving(SubjectSummaries::class, function (SubjectSummaries $summaries): void {
            $summaries->register(Enrollment::class, EnrollmentSummary::for(...));
        });
    }

    public function boot(): void
    {
        foreach (self::ABILITIES as $ability) {
            Gate::define("training.{$ability}", [TrainingPolicy::class, $ability]);
        }

        Event::listen(InstanceFinished::class, SettleEnrollments::class);
        Event::listen(EnrollmentCompleted::class, IssueCertificates::class);
        Event::listen(EmployeeHired::class, AssignMandatoryCourses::class);
        Event::listen(EmployeePlacementChanged::class, AssignMandatoryCourses::class);
    }
}
