<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Forms;

use App\Domain\Access\Models\User;
use App\Domain\Forms\Actions\DiscardFormDraft;
use App\Domain\Forms\Actions\PublishForm;
use App\Domain\Forms\Actions\SaveFormDraft;
use App\Domain\Forms\Actions\StoreFormUpload;
use App\Domain\Forms\Actions\SubmitForm;
use App\Domain\Forms\Data\FormData;
use App\Domain\Forms\Enums\SubmissionSource;
use App\Domain\Forms\Models\Form;
use App\Domain\Forms\Models\FormVersion;
use App\Domain\Forms\Support\FormContext;
use App\Domain\Forms\Support\FormSchema;
use App\Http\Controllers\Controller;
use App\Support\Actors\Actor;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Form builder (handoff 14), preview and test runs. The builder edits the draft; published
 * versions are frozen.
 */
final class FormBuilderController extends Controller
{
    public function show(Form $form, FormContext $context, #[CurrentUser] User $user): Response
    {
        Gate::authorize('forms.view');

        $form->load(['publishedVersion', 'draft'])->loadCount('submissions');
        $schema = $form->draft->schema ?? $form->publishedVersion->schema ?? FormSchema::blank();

        return Inertia::render('forms/builder', [
            'form' => FormData::fromForm($form),
            'schema' => $schema,
            'context' => $context->for($user),
            'can' => ['update' => Gate::allows('forms.update'), 'delete' => Gate::allows('forms.delete'), 'create' => Gate::allows('forms.create')],
        ]);
    }

    public function saveDraft(Request $request, Form $form, SaveFormDraft $save, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('forms.update');
        $request->validate(['schema' => ['required', 'array']]);

        /** @var array<mixed> $schema */
        $schema = $request->input('schema');
        $save->handle(Actor::user($user), $form, $schema);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Draft saved.')]);

        return back();
    }

    public function publish(Form $form, PublishForm $publish, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('forms.update');

        $version = $publish->handle(Actor::user($user), $form);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Version :number published.', ['number' => $version->number])]);

        return back();
    }

    public function discard(Form $form, DiscardFormDraft $discard): RedirectResponse
    {
        Gate::authorize('forms.update');

        $discard->handle($form);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Unpublished changes discarded.')]);

        return back();
    }

    /**
     * Fill in the form as a requester would. `version=draft` tests unpublished changes (builders only).
     */
    public function preview(Request $request, Form $form, FormContext $context, #[CurrentUser] User $user): Response
    {
        Gate::authorize('forms.view');
        $version = $this->version($request, $form);

        return Inertia::render('forms/preview', [
            'form' => FormData::fromForm($form->load(['publishedVersion', 'draft'])->loadCount('submissions')),
            'version' => ['id' => $version->ulid, 'number' => $version->number, 'status' => $version->status->value],
            'schema' => $version->schema,
            'context' => $context->for($user),
        ]);
    }

    /** A test run: validated like a real submission, recorded with source `test`. */
    public function test(Request $request, Form $form, SubmitForm $submit, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('forms.update');
        $request->validate(['values' => ['array']]);

        /** @var array<string, mixed> $values */
        $values = $request->input('values', []);
        $submit->handle(Actor::user($user), $form, $this->version($request, $form), $values, SubmissionSource::Test);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Test submission recorded.')]);

        return back();
    }

    public function upload(Request $request, Form $form, StoreFormUpload $store, #[CurrentUser] User $user): JsonResponse
    {
        Gate::authorize('forms.view');
        $request->validate(['field' => ['required', 'string', 'max:40'], 'file' => ['required', 'file', 'max:20480']]);

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $upload = $store->handle($form, $this->version($request, $form), $request->string('field')->toString(), $file, $user, null);

        return response()->json(['id' => $upload->ulid, 'name' => $upload->original_name, 'size' => $upload->size, 'mime' => $upload->mime], 201);
    }

    private function version(Request $request, Form $form): FormVersion
    {
        $wantsDraft = $request->input('version') === 'draft';

        if ($wantsDraft) {
            Gate::authorize('forms.update');
        }

        $version = $wantsDraft ? $form->draft()->first() : $form->publishedVersion()->first();

        abort_unless($version instanceof FormVersion, 404);

        return $version;
    }
}
