<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Hiring;

use App\Domain\Access\Models\User;
use App\Domain\Hiring\Actions\SaveHiringSettings;
use App\Domain\Hiring\Actions\SavePipelineStages;
use App\Domain\Hiring\Actions\SaveScorecardCriteria;
use App\Domain\Hiring\Data\HiringSettingsData;
use App\Domain\Hiring\Data\StageData;
use App\Domain\Hiring\Models\PipelineStage;
use App\Domain\Hiring\Models\ScorecardCriterion;
use App\Domain\Hiring\Support\HiringSettings;
use App\Domain\Hiring\Support\OfferLetter;
use App\Domain\Hiring\Support\Texts;
use App\Domain\Setup\Support\Settings;
use App\Http\Controllers\Controller;
use App\Support\Actors\Actor;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Hiring settings (`hiring.configure`): the default pipeline and its automations, default
 * scorecard criteria, the careers texts, retention, the rejection message and the offer letter.
 */
final class SettingsController extends Controller
{
    public function show(HiringSettings $settings, Settings $raw, OfferLetter $letters): Response
    {
        Gate::authorize('hiring.configure');
        $pair = fn (array $text): array => ['en' => $text['en'] ?? '', 'ar' => $text['ar'] ?? ''];
        $saved = $raw->get('hiring.offer_letter');

        return Inertia::render('hiring/settings', [
            'stages' => array_values(PipelineStage::query()->whereNull('job_requisition_id')->orderBy('position')->get()->map(StageData::fromStage(...))->all()),
            'criteria' => array_values(ScorecardCriterion::query()->whereNull('job_requisition_id')->orderBy('position')->get()->map(fn (ScorecardCriterion $c): array => ['id' => $c->ulid, 'name' => Texts::pair($c, 'name')])->all()),
            'careersIntro' => $pair($settings->careersIntro()),
            'privacyNotice' => $pair($settings->privacyNotice()),
            'retentionMonths' => $settings->retentionMonths(),
            'poolRetentionMonths' => $settings->poolRetentionMonths(),
            'rejectionMessage' => $settings->rejectionMessageForEditing(),
            'offerLetter' => ['en' => $letters->template('en'), 'ar' => $letters->template('ar')],
            'offerLetterCustomised' => is_array($saved),
            'letterVariables' => OfferLetter::VARIABLES,
            'careersUrl' => route('careers.index'),
            'defaultFormId' => $settings->defaultForm()?->ulid,
        ]);
    }

    public function update(HiringSettingsData $data, SaveHiringSettings $save, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('hiring.configure');
        $save->handle(Actor::user($user), $data);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Settings saved.')]);

        return back();
    }

    public function pipeline(Request $request, SavePipelineStages $save, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('hiring.configure');
        $save->handle(Actor::user($user), null, BoardController::validatedStages($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Stages saved.')]);

        return back();
    }

    public function criteria(Request $request, SaveScorecardCriteria $save, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('hiring.configure');
        $save->handle(Actor::user($user), null, BoardController::validatedCriteria($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Criteria saved.')]);

        return back();
    }
}
