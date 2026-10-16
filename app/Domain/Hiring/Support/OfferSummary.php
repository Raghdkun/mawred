<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Support;

use App\Domain\Hiring\Models\Offer;
use App\Support\Money\Money;

/** What an approver sees about an offer in the approvals inbox (D91). */
final class OfferSummary
{
    /**
     * @return array{about: string, facts: list<array{label: string, value: string}>, url: string|null}|null
     */
    public static function for(int $offerId, string $locale): ?array
    {
        $offer = Offer::query()->with(['application.candidate', 'application.requisition', 'branch', 'department', 'manager'])->find($offerId);

        if (! $offer instanceof Offer) {
            return null;
        }

        $application = $offer->application;
        $facts = [
            ['label' => __('Candidate', [], $locale), 'value' => $application->candidate->name ?? '—'],
            ['label' => __('Requisition', [], $locale), 'value' => $application->requisition->number ?? '—'],
            ['label' => __('Start date', [], $locale), 'value' => $offer->start_on->toDateString()],
            ['label' => __('Monthly salary', [], $locale), 'value' => $offer->salary instanceof Money ? $offer->salary->format($locale) : '—'],
            ['label' => __('Branch', [], $locale), 'value' => Texts::one($offer->branch, 'name', $locale) ?? '—'],
        ];

        if ($offer->department !== null) {
            $facts[] = ['label' => __('Department', [], $locale), 'value' => Texts::one($offer->department, 'name', $locale) ?? '—'];
        }

        if ($offer->expires_on !== null) {
            $facts[] = ['label' => __('Offer valid until', [], $locale), 'value' => $offer->expires_on->toDateString()];
        }

        return [
            'about' => __('Offer: :job', ['job' => $offer->job_title], $locale),
            'facts' => $facts,
            'url' => route('hiring.offers.pdf', $offer),
        ];
    }
}
