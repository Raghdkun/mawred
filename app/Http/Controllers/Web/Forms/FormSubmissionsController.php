<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Forms;

use App\Domain\Access\Models\User;
use App\Domain\Forms\Data\FormData;
use App\Domain\Forms\Data\SubmissionData;
use App\Domain\Forms\Enums\SubmissionSource;
use App\Domain\Forms\Models\Form;
use App\Domain\Forms\Models\FormSubmission;
use App\Domain\Forms\Models\FormUpload;
use App\Domain\Forms\Support\AnswerLabels;
use App\Http\Controllers\Controller;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Submissions of a form. Answers can hold personal data: opening one and downloading its files
 * are audited.
 */
final class FormSubmissionsController extends Controller
{
    public function index(Request $request, Form $form): Response
    {
        Gate::authorize('forms.view');
        $request->validate(['source' => ['nullable', Rule::enum(SubmissionSource::class)], 'open' => ['nullable', 'string', 'size:26']]);

        $submissions = $form->submissions()
            ->with(['version:id,number', 'employee', 'submitter:id,name'])
            ->when($request->filled('source'), fn ($q) => $q->where('source', $request->string('source')->toString()))
            ->latest('created_at')->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (FormSubmission $submission): SubmissionData => SubmissionData::fromSubmission($submission));

        return Inertia::render('forms/submissions', [
            'form' => FormData::fromForm($form->load(['publishedVersion', 'draft'])->loadCount('submissions')),
            'submissions' => $submissions,
            'filters' => ['source' => $request->input('source')],
        ]);
    }

    /**
     * One submission's answers with the schema of the version it was made against.
     */
    public function show(Form $form, FormSubmission $submission, #[CurrentUser] User $viewer): JsonResponse
    {
        Gate::authorize('forms.view');
        abort_unless($submission->form_id === $form->id, 404);

        $submission->load(['version', 'employee', 'submitter:id,name']);
        activity('forms')->causedBy($viewer)->performedOn($form)->event('submission.viewed')
            ->withProperties(['submission' => $submission->ulid])->log('Form submission viewed');

        return response()->json([
            'submission' => SubmissionData::fromSubmission($submission, withAnswers: true),
            'schema' => $submission->pinnedVersion()->schema,
            'display' => AnswerLabels::for($submission->pinnedVersion()->schema, $submission->data, app()->getLocale()),
        ]);
    }

    public function download(Form $form, FormSubmission $submission, FormUpload $upload, #[CurrentUser] User $viewer): StreamedResponse
    {
        Gate::authorize('forms.view');
        abort_unless($submission->form_id === $form->id && $upload->form_submission_id === $submission->id, 404);

        activity('forms')->causedBy($viewer)->performedOn($form)->event('file.downloaded')
            ->withProperties(['submission' => $submission->ulid, 'file' => $upload->ulid])->log('Form file downloaded');

        return Storage::disk(FormUpload::DISK)->download($upload->path, $upload->original_name, ['Content-Type' => $upload->mime]);
    }
}
