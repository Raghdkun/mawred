<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Support;

use App\Domain\Access\Models\User;
use App\Domain\Forms\Support\FieldSpec;
use App\Domain\Hiring\Models\Candidate;
use App\Domain\Hiring\Models\JobApplication;
use App\Domain\Hiring\Models\Offer;
use App\Domain\Notifications\Enums\NotificationCategory;
use App\Domain\Notifications\Support\NotificationMessage;
use App\Domain\Notifications\Support\NotificationRouter;

/** Hiring news for colleagues (the Hiring notification category): offer decisions and answers. */
final readonly class HiringNotifier
{
    public function __construct(private NotificationRouter $router) {}

    /**
     * @param  'approved'|'rejected'|'accepted'|'declined'  $what
     */
    public function offer(User $user, Offer $offer, string $what): void
    {
        /** @var JobApplication $application */
        $application = $offer->application()->with('candidate')->firstOrFail();
        $name = $application->candidate instanceof Candidate ? $application->candidate->name : '';

        $this->router->send($user, new NotificationMessage(
            event: 'hiring.offer.'.$what,
            category: NotificationCategory::Hiring,
            content: fn (string $channel, string $locale): array => [
                'subject' => FieldSpec::string(match ($what) {
                    'approved' => __('Offer approved: :name', ['name' => $name], $locale),
                    'rejected' => __('Offer not approved: :name', ['name' => $name], $locale),
                    'accepted' => __(':name accepted the offer', ['name' => $name], $locale),
                    'declined' => __(':name declined the offer', ['name' => $name], $locale),
                }),
                'body' => $offer->job_title,
            ],
            url: $application->candidate instanceof Candidate ? route('hiring.candidates.show', [$application->candidate, 'application' => $application->ulid]) : null,
            sourceKey: $offer->ulid.':'.$what,
        ));
    }
}
