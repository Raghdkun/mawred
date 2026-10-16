<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Hiring\Events\ApplicationMoved;
use App\Domain\Hiring\Listeners\LogCandidateReplies;
use App\Domain\Hiring\Listeners\RunStageAutomations;
use App\Domain\Hiring\Listeners\SettleOffers;
use App\Domain\Hiring\Listeners\SettleRequisitions;
use App\Domain\Hiring\Models\Offer;
use App\Domain\Hiring\Policies\HiringPolicy;
use App\Domain\Hiring\Support\OfferSummary;
use App\Domain\Hiring\Workflows\OpenApprovedRequisition;
use App\Domain\Integrations\Events\WhatsAppMessageReceived;
use App\Domain\Workflows\Engine\RecordUpdates;
use App\Domain\Workflows\Engine\SubjectSummaries;
use App\Domain\Workflows\Events\InstanceFinished;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Hiring wiring (Phase 6): the requisition approval's Update Record step, settling requisitions
 * and offers when their workflows end, offers in the approvals inbox, stage automations,
 * candidate WhatsApp replies, and record-level gates.
 */
final class HiringServiceProvider extends ServiceProvider
{
    private const array ABILITIES = [
        'viewRequisition', 'updateRequisition', 'updatePosting', 'seeApplication', 'viewApplication', 'updateApplication',
        'viewCandidate', 'updateCandidate', 'scoreInterview', 'readScorecards', 'viewOffer',
    ];

    public function register(): void
    {
        $this->app->afterResolving(RecordUpdates::class, function (RecordUpdates $updates): void {
            $updates->register($this->app->make(OpenApprovedRequisition::class));
        });

        $this->app->afterResolving(SubjectSummaries::class, function (SubjectSummaries $summaries): void {
            $summaries->register(Offer::class, OfferSummary::for(...));
        });
    }

    public function boot(): void
    {
        foreach (self::ABILITIES as $ability) {
            Gate::define("hiring.{$ability}", [HiringPolicy::class, $ability]);
        }

        Event::listen(InstanceFinished::class, SettleRequisitions::class);
        Event::listen(InstanceFinished::class, SettleOffers::class);
        Event::listen(ApplicationMoved::class, RunStageAutomations::class);
        Event::listen(WhatsAppMessageReceived::class, LogCandidateReplies::class);
    }
}
