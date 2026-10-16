<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Domain\Forms\Actions\StoreFormUpload;
use App\Domain\Forms\Models\Form;
use App\Domain\Forms\Models\FormVersion;
use App\Domain\Forms\Support\FieldSpec;
use App\Domain\Forms\Support\FormContext;
use App\Domain\Hiring\Actions\SubmitApplication;
use App\Domain\Hiring\Data\CareersPostingData;
use App\Domain\Hiring\Enums\ApplicationSource;
use App\Domain\Hiring\Enums\Workplace;
use App\Domain\Hiring\Models\JobPosting;
use App\Domain\Hiring\Models\JobRequisition;
use App\Domain\Hiring\Support\CareersPostings;
use App\Domain\Hiring\Support\HiringSettings;
use App\Domain\Setup\Support\Settings;
use App\Http\Controllers\Controller;
use App\Support\Actors\Actor;
use App\Support\Captcha\AltchaGuard;
use App\Support\Errors\DomainRuleViolation;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public careers site (handoff 31, D87): job cards with filters, job detail with the
 * application form, and the success state. Server-rendered for search engines, with Open Graph
 * tags, JSON-LD `JobPosting` and a sitemap. A co-brand area: the company leads, Mawred is
 * "powered by". Applying is hardened like public forms: ALTCHA, honeypot, minimum fill time.
 */
final class CareersController extends Controller
{
    public const int MIN_FILL_SECONDS = 3;

    public function __construct(private readonly Settings $settings, private readonly HiringSettings $hiring) {}

    public function index(Request $request): Response
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'workplace' => ['nullable', Rule::enum(Workplace::class)],
            'branch' => ['nullable', 'string', 'size:26'],
        ]);
        $locale = app()->getLocale();
        $postings = CareersPostings::open($this->today())->with(['requisition.branch', 'requisition.department', 'requisition.employmentType'])
            ->latest('published_at')->orderByDesc('id')->limit(500)->get();
        $branches = $postings->map(fn (JobPosting $p): array => ['id' => $p->requisition->branch->ulid ?? '', 'name' => $p->requisition?->branch?->getTranslation('name', $locale) ?? ''])
            ->unique('id')->sortBy('name')->values()->all();

        $q = mb_strtolower(trim($request->string('q')->toString()));
        $filtered = $postings
            ->when($request->filled('workplace'), fn ($c) => $c->filter(fn (JobPosting $p): bool => $p->workplace->value === $request->string('workplace')->toString()))
            ->when($request->filled('branch'), fn ($c) => $c->filter(fn (JobPosting $p): bool => $p->requisition?->branch?->ulid === $request->string('branch')->lower()->toString()))
            ->when($q !== '', fn ($c) => $c->filter(fn (JobPosting $p): bool => collect($p->getTranslations('title'))->contains(fn (string $t): bool => str_contains(mb_strtolower($t), $q))));

        $intro = FieldSpec::text($this->hiring->careersIntro(), $locale);

        return Inertia::render('public/careers', [
            'jobs' => array_values($filtered->map(CareersPostingData::fromPosting(...))->all()),
            'total' => $postings->count(),
            'intro' => $intro,
            'branches' => $branches,
            'filters' => ['q' => $request->string('q')->toString(), 'workplace' => $request->string('workplace')->toString(), 'branch' => $request->string('branch')->toString()],
        ])->withViewData(['meta' => $this->meta(
            title: __('Careers'),
            description: $intro,
            path: route('careers.index', absolute: false),
        )]);
    }

    public function show(Request $request, string $slug, FormContext $context): Response
    {
        $posting = $this->posting($slug);
        $open = $this->isOpen($posting);
        $version = $open ? $this->version($posting) : null;
        $locale = app()->getLocale();
        $data = CareersPostingData::fromPosting($posting);

        return Inertia::render('public/job', [
            'job' => $data,
            'open' => $version instanceof FormVersion,
            'schema' => $version?->schema,
            'context' => ['requester' => null, 'ctx' => $context->forEmployee(null)['ctx']],
            'privacyNotice' => FieldSpec::text($this->hiring->privacyNotice(), $locale),
            'startedAt' => Crypt::encryptString((string) time()),
        ])->withViewData(['meta' => $this->meta(
            title: $data->title,
            description: $data->summary ?? Str::limit(trim((string) preg_replace('/\s+/u', ' ', $data->description)), 155),
            path: route('careers.show', $slug, absolute: false),
            jsonLd: $this->jobPostingLd($posting, $data),
            seo: $posting->seo,
        )]);
    }

    public function challenge(AltchaGuard $altcha): JsonResponse
    {
        return response()->json($altcha->challenge())->header('Cache-Control', 'no-store');
    }

    public function upload(Request $request, string $slug, StoreFormUpload $store): JsonResponse
    {
        $posting = $this->posting($slug);
        $version = $this->isOpen($posting) ? $this->version($posting) : null;
        abort_unless($version instanceof FormVersion, 404);
        $request->validate(['field' => ['required', 'string', 'max:40'], 'file' => ['required', 'file', 'max:20480']]);

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $form = Form::withTrashed()->findOrFail($version->form_id);
        $upload = $store->handle($form, $version, $request->string('field')->toString(), $file, null, $this->sessionHash($request));

        return response()->json(['id' => $upload->ulid, 'name' => $upload->original_name, 'size' => $upload->size, 'mime' => $upload->mime], 201);
    }

    public function apply(Request $request, string $slug, SubmitApplication $submit, AltchaGuard $altcha): RedirectResponse
    {
        $posting = $this->posting($slug);
        abort_unless($this->isOpen($posting), 404);
        $request->validate([
            'values' => ['array'],
            'altcha' => ['nullable', 'string', 'max:8192'],
            'startedAt' => ['required', 'string', 'max:1024'],
            'website' => ['nullable', 'string', 'max:255'],
            'privacy' => ['boolean'],
            'talentPool' => ['boolean'],
        ]);

        // Honeypot: people never see this field; bots fill it. Pretend success.
        if ($request->filled('website')) {
            Inertia::flash('applied', true);

            return to_route('careers.show', $slug);
        }

        if (! $this->filledLongEnough($request->string('startedAt')->toString())) {
            throw new DomainRuleViolation('forms.too_fast', __('Please take a moment to check your answers, then send again.'));
        }

        if (! $altcha->verify($request->string('altcha')->toString())) {
            throw new DomainRuleViolation('forms.captcha', __('Please complete the verification and try again.'), ['altcha' => [__('Please complete the verification and try again.')]]);
        }

        /** @var JobRequisition $requisition */
        $requisition = $posting->requisition;
        /** @var array<string, mixed> $values */
        $values = $request->input('values', []);
        $submit->handle(
            Actor::system('careers'),
            $requisition,
            $posting,
            $values,
            ApplicationSource::Careers,
            ['privacy' => $request->boolean('privacy'), 'talentPool' => $request->boolean('talentPool')],
            $this->sessionHash($request),
            $request->ip(),
        );

        Inertia::flash('applied', true);

        return to_route('careers.show', $slug);
    }

    public function sitemap(): HttpResponse
    {
        $postings = CareersPostings::open($this->today())->latest('published_at')->limit(5000)->get(['slug', 'updated_at']);
        $urls = [['loc' => route('careers.index'), 'lastmod' => null]];

        foreach ($postings as $posting) {
            $urls[] = ['loc' => route('careers.show', $posting->slug), 'lastmod' => $posting->updated_at->toDateString()];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">';

        foreach ($urls as $url) {
            $xml .= '<url><loc>'.e($url['loc']).'</loc>'.($url['lastmod'] !== null ? '<lastmod>'.$url['lastmod'].'</lastmod>' : '');

            foreach (['en', 'ar'] as $lang) {
                $xml .= '<xhtml:link rel="alternate" hreflang="'.$lang.'" href="'.e($url['loc'].'?lang='.$lang).'"/>';
            }

            $xml .= '</url>';
        }

        return response($xml.'</urlset>', 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    private function posting(string $slug): JobPosting
    {
        $posting = JobPosting::query()->where('slug', $slug)
            ->whereIn('status', ['published', 'closed'])
            ->with(['requisition' => fn ($q) => $q->with(['branch', 'department', 'employmentType'])])
            ->first();
        abort_unless($posting instanceof JobPosting, 404);

        return $posting;
    }

    private function isOpen(JobPosting $posting): bool
    {
        return CareersPostings::open($this->today())->whereKey($posting->id)->exists();
    }

    private function version(JobPosting $posting): ?FormVersion
    {
        $form = $posting->form_id === null ? $this->hiring->defaultForm() : Form::query()->find($posting->form_id);

        return $form instanceof Form && $form->published_version_id !== null ? FormVersion::query()->find($form->published_version_id) : null;
    }

    /**
     * @param  array<string, mixed>|null  $jsonLd
     * @param  array{title?: array<string, string>, description?: array<string, string>}|null  $seo
     * @return array<string, mixed>
     */
    private function meta(string $title, string $description, string $path, ?array $jsonLd = null, ?array $seo = null): array
    {
        $locale = app()->getLocale();
        $company = $this->companyName($locale);
        $title = FieldSpec::text($seo['title'] ?? null, $locale, $title);
        $description = FieldSpec::text($seo['description'] ?? null, $locale, $description);
        $url = url($path);

        return [
            'title' => "{$title} · {$company}",
            'description' => Str::limit($description, 160),
            'canonical' => "{$url}?lang={$locale}",
            'alternates' => ['en' => "{$url}?lang=en", 'ar' => "{$url}?lang=ar"],
            'image' => asset("brand/og-careers-{$locale}.png"),
            'siteName' => $company,
            'locale' => $locale === 'ar' ? 'ar_SA' : 'en_US',
            'jsonLd' => $jsonLd,
        ];
    }

    /**
     * Schema.org `JobPosting` for search engines' job listings.
     *
     * @return array<string, mixed>
     */
    private function jobPostingLd(JobPosting $posting, CareersPostingData $data): array
    {
        $requisition = $posting->requisition;
        $branch = $requisition?->branch;
        $address = is_array($branch?->address) ? $branch->address : [];
        $logo = $this->settings->get('company.logo_path');

        $ld = [
            '@context' => 'https://schema.org',
            '@type' => 'JobPosting',
            'title' => $data->title,
            'description' => nl2br(e($data->description)),
            'datePosted' => $posting->published_at?->toDateString(),
            'validThrough' => $posting->closes_on?->endOfDay()->toIso8601String(),
            'directApply' => true,
            'url' => route('careers.show', $posting->slug),
            'hiringOrganization' => array_filter([
                '@type' => 'Organization',
                'name' => $this->companyName(app()->getLocale()),
                'logo' => is_string($logo) ? Storage::disk('public')->url($logo) : null,
            ]),
            'jobLocation' => ['@type' => 'Place', 'address' => array_filter([
                '@type' => 'PostalAddress',
                'addressLocality' => is_string($address['city'] ?? null) ? $address['city'] : null,
                'addressCountry' => $branch?->country_code,
            ])],
        ];

        if ($posting->workplace === Workplace::Remote) {
            $ld['jobLocationType'] = 'TELECOMMUTE';
        }

        if ($data->salary !== null) {
            $ld['baseSalary'] = ['@type' => 'MonetaryAmount', 'currency' => $data->salary['currency'], 'value' => array_filter([
                '@type' => 'QuantitativeValue',
                'minValue' => $data->salary['min'] === null ? null : (float) (new Money($data->salary['min'], $data->salary['currency']))->toDecimal(),
                'maxValue' => $data->salary['max'] === null ? null : (float) (new Money($data->salary['max'], $data->salary['currency']))->toDecimal(),
                'unitText' => 'MONTH',
            ], fn (mixed $v): bool => $v !== null)];
        }

        return array_filter($ld, fn (mixed $v): bool => $v !== null);
    }

    private function companyName(string $locale): string
    {
        return FieldSpec::text($this->settings->get('company.name'), $locale, FieldSpec::string(config('app.name'), 'Mawred'));
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now(FieldSpec::string(config('app.timezone'), 'UTC'));
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
