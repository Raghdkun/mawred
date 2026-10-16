<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Hiring;

use App\Domain\Access\Models\User;
use App\Domain\Forms\Models\Form;
use App\Domain\Forms\Models\FormVersion;
use App\Domain\Hiring\Actions\ChangePostingStatus;
use App\Domain\Hiring\Actions\SaveJobPosting;
use App\Domain\Hiring\Data\JobPostingInputData;
use App\Domain\Hiring\Data\PostingData;
use App\Domain\Hiring\Data\RequisitionData;
use App\Domain\Hiring\Enums\PostingStatus;
use App\Domain\Hiring\Models\JobPosting;
use App\Domain\Hiring\Models\JobRequisition;
use App\Domain\Hiring\Support\ApplicantFields;
use App\Domain\Hiring\Support\HiringSettings;
use App\Domain\Integrations\Models\CognitoSource;
use App\Http\Controllers\Controller;
use App\Support\Actors\Actor;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Posting editor (D81, D87): bilingual advert, application form, screening knock-outs, search
 * and share texts, publish and close.
 */
final class PostingsController extends Controller
{
    public function create(JobRequisition $requisition, HiringSettings $settings): Response
    {
        $this->authorizeEditing($requisition);

        return $this->editor($requisition, null, $settings);
    }

    public function store(JobRequisition $requisition, JobPostingInputData $input, SaveJobPosting $save, #[CurrentUser] User $user): RedirectResponse
    {
        $this->authorizeEditing($requisition);
        $posting = $save->handle(Actor::user($user), $requisition, $input);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Posting saved.')]);

        return to_route('hiring.postings.edit', $posting);
    }

    public function edit(JobPosting $posting, HiringSettings $settings): Response
    {
        $requisition = $posting->requisition()->firstOrFail();
        $this->authorizeEditing($requisition);

        return $this->editor($requisition, $posting, $settings);
    }

    public function update(JobPosting $posting, JobPostingInputData $input, SaveJobPosting $save, #[CurrentUser] User $user): RedirectResponse
    {
        $requisition = $posting->requisition()->firstOrFail();
        $this->authorizeEditing($requisition);
        $save->handle(Actor::user($user), $requisition, $input, $posting);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Posting saved.')]);

        return back();
    }

    public function status(Request $request, JobPosting $posting, ChangePostingStatus $change, #[CurrentUser] User $user): RedirectResponse
    {
        $this->authorizeEditing($posting->requisition()->firstOrFail());
        $request->validate(['status' => ['required', Rule::enum(PostingStatus::class)]]);
        $to = PostingStatus::from($request->string('status')->toString());
        $change->handle(Actor::user($user), $posting, $to);
        Inertia::flash('toast', ['type' => 'success', 'message' => $to === PostingStatus::Published ? __('The posting is live on the careers site.') : __('Posting updated.')]);

        return back();
    }

    private function editor(JobRequisition $requisition, ?JobPosting $posting, HiringSettings $settings): Response
    {
        $default = $settings->defaultForm();
        $forms = Form::query()->whereNotNull('published_version_id')->where(fn (Builder $q) => $q->where('category', 'hiring')->orWhere('id', $posting?->form_id))->orderBy('id')->get()
            ->map(function (Form $form): ?array {
                $version = FormVersion::query()->find($form->published_version_id);

                return $version instanceof FormVersion && ApplicantFields::of($version->schema)->usable()
                    ? ['id' => $form->ulid, 'name' => $form->getTranslation('name', app()->getLocale()), 'schema' => $version->schema]
                    : null;
            })
            ->filter()
            ->values()
            ->all();

        $posting?->loadCount('applications');
        $taken = JobPosting::query()->whereNotNull('cognito_source_id')->when($posting instanceof JobPosting, fn ($q) => $q->whereKeyNot($posting?->id))->pluck('cognito_source_id');
        $cognitoSources = CognitoSource::query()->whereNull('request_type_id')->whereNotIn('id', $taken)->orderBy('name')->get()
            ->map(fn (CognitoSource $source): array => ['id' => $source->ulid, 'name' => $source->name, 'formUrl' => $source->form_url, 'active' => $source->is_active])
            ->values()
            ->all();

        return Inertia::render('hiring/posting', [
            'requisition' => RequisitionData::fromRequisition($requisition),
            'posting' => $posting instanceof JobPosting ? PostingData::fromPosting($posting, $posting->form?->ulid) : null,
            'forms' => $forms,
            'cognitoSources' => $cognitoSources,
            'defaultFormId' => $default?->ulid,
            'suggestedTitle' => $requisition->getTranslations('title'),
            'requisitionOpen' => $requisition->status->isActive(),
        ]);
    }

    private function authorizeEditing(JobRequisition $requisition): void
    {
        abort_unless(Gate::allows('hiring.viewRequisition', $requisition), 404);
        abort_unless(Gate::allows('hiring.updateRequisition', $requisition), 403);
    }
}
