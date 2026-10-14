<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Domain\Forms\Actions\StoreFormUpload;
use App\Domain\Forms\Actions\SubmitForm;
use App\Domain\Forms\Enums\SubmissionSource;
use App\Domain\Forms\Models\Form;
use App\Domain\Forms\Models\FormVersion;
use App\Domain\Forms\Support\FieldSpec;
use App\Domain\Forms\Support\FormContext;
use App\Http\Controllers\Controller;
use App\Support\Actors\Actor;
use App\Support\Captcha\AltchaGuard;
use App\Support\Errors\DomainRuleViolation;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public forms (D53): no sign-in. Hardened with ALTCHA proof-of-work (D10), a honeypot field, a
 * minimum fill time and per-IP rate limits on the routes.
 */
final class PublicFormController extends Controller
{
    public const int MIN_FILL_SECONDS = 3;

    public function show(string $slug, FormContext $context): Response
    {
        [$form, $version] = $this->resolve($slug);
        $settings = $form->public_settings ?? [];

        return Inertia::render('public/form', [
            'form' => ['slug' => $slug, 'name' => $form->getTranslations('name'), 'description' => $form->getTranslations('description'), 'successMessage' => $settings['successMessage'] ?? []],
            'schema' => $version?->schema,
            'closed' => ! $version instanceof FormVersion,
            'context' => ['requester' => null, 'ctx' => $context->forEmployee(null)['ctx']],
            'startedAt' => Crypt::encryptString((string) time()),
        ]);
    }

    public function challenge(AltchaGuard $altcha): JsonResponse
    {
        return response()->json($altcha->challenge())->header('Cache-Control', 'no-store');
    }

    public function upload(Request $request, string $slug, StoreFormUpload $store): JsonResponse
    {
        [$form, $version] = $this->resolve($slug);
        abort_unless($version instanceof FormVersion, 404);
        $request->validate(['field' => ['required', 'string', 'max:40'], 'file' => ['required', 'file', 'max:20480']]);

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $upload = $store->handle($form, $version, $request->string('field')->toString(), $file, null, $this->sessionHash($request));

        return response()->json(['id' => $upload->ulid, 'name' => $upload->original_name, 'size' => $upload->size, 'mime' => $upload->mime], 201);
    }

    public function submit(Request $request, string $slug, SubmitForm $submit, AltchaGuard $altcha): RedirectResponse
    {
        [$form, $version] = $this->resolve($slug);
        abort_unless($version instanceof FormVersion, 404);
        $request->validate([
            'values' => ['array'],
            'altcha' => ['nullable', 'string', 'max:8192'],
            'startedAt' => ['required', 'string', 'max:1024'],
            'website' => ['nullable', 'string', 'max:255'],
        ]);

        // Honeypot: people never see this field; bots fill it. Pretend success.
        if ($request->filled('website')) {
            Inertia::flash('submitted', true);

            return back();
        }

        if (! $this->filledLongEnough($request->string('startedAt')->toString())) {
            throw new DomainRuleViolation('forms.too_fast', __('Please take a moment to check your answers, then send again.'));
        }

        if (! $altcha->verify($request->string('altcha')->toString())) {
            throw new DomainRuleViolation('forms.captcha', __('Please complete the verification and try again.'), ['altcha' => [__('Please complete the verification and try again.')]]);
        }

        /** @var array<string, mixed> $values */
        $values = $request->input('values', []);
        $submit->handle(Actor::system('public-form'), $form, $version, $values, SubmissionSource::Public, $this->sessionHash($request), $request->ip());

        Inertia::flash('submitted', true);

        return to_route('public-forms.show', $slug);
    }

    /**
     * @return array{0: Form, 1: FormVersion|null} the version is null when the form is closed
     */
    private function resolve(string $slug): array
    {
        $form = Form::query()->where('public_slug', $slug)->where('is_public', true)->with('publishedVersion')->first();
        abort_unless($form instanceof Form, 404);

        $closesAt = $form->public_settings['closesAt'] ?? null;
        $closed = is_string($closesAt) && $closesAt < CarbonImmutable::now(FieldSpec::string(config('app.timezone'), 'UTC'))->toDateString();

        return [$form, $closed ? null : $form->publishedVersion];
    }

    private function filledLongEnough(string $token): bool
    {
        try {
            $started = (int) Crypt::decryptString($token);
        } catch (DecryptException) {
            return false;
        }

        $elapsed = time() - $started;

        return $elapsed >= self::MIN_FILL_SECONDS && $elapsed <= 86400;
    }

    private function sessionHash(Request $request): string
    {
        return hash('sha256', $request->session()->getId());
    }
}
