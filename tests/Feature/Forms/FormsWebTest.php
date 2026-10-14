<?php

declare(strict_types=1);

use AltchaOrg\Altcha\Algorithm\Pbkdf2;
use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\Challenge;
use AltchaOrg\Altcha\Payload;
use AltchaOrg\Altcha\SolveChallengeOptions;
use App\Domain\Access\Models\User;
use App\Domain\Forms\Enums\SubmissionSource;
use App\Domain\Forms\Models\Form;
use App\Domain\Forms\Models\FormSubmission;
use App\Domain\Forms\Models\FormUpload;
use App\Domain\Organization\Models\Branch;
use App\Domain\People\Models\Employee;
use App\Domain\Policies\Actions\SeedPolicyDefaults;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    seedAccess();
    Storage::fake('local');
    // A tiny work factor so the test can solve challenges quickly in PHP.
    config(['services.altcha' => ['cost' => 1, 'counter_min' => 1, 'counter_max' => 20]]);
});

function formsAdmin(): User
{
    $user = userWithRoles('hr_admin');
    $user->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();
    Employee::factory()->placedIn(Branch::factory()->riyadh()->create())->create(['user_id' => $user->id]);

    return $user;
}

/** Creates and publishes a form through the HTTP endpoints. */
function publishedForm(object $test, array $fields, array $settings = []): Form
{
    $test->post(route('forms.store'), ['code' => 'feedback', 'name' => ['en' => 'Office feedback']])->assertRedirect();
    $form = Form::query()->latest('id')->firstOrFail();

    $test->put(route('forms.draft.save', $form), ['schema' => mfs($fields)])->assertSessionHasNoErrors();
    $test->post(route('forms.publish', $form))->assertSessionHasNoErrors();

    if ($settings !== []) {
        $test->put(route('forms.update', $form), ['code' => 'feedback', 'name' => ['en' => 'Office feedback'], ...$settings])->assertSessionHasNoErrors();
    }

    return $form->fresh() ?? $form;
}

/** Solves an ALTCHA challenge the way the browser widget does. */
function solvedAltcha(object $test, string $slug): string
{
    $challenge = Challenge::fromArray($test->getJson(route('public-forms.challenge', $slug))->assertOk()->json());
    $solution = (new Altcha(hmacSignatureSecret: 'unused'))->solveChallenge(new SolveChallengeOptions(challenge: $challenge, algorithm: new Pbkdf2));

    return (new Payload($challenge, $solution))->toBase64();
}

it('gates forms to people with forms permissions', function (): void {
    $this->actingAs(userWithRoles('employee'))->get(route('forms.index'))->assertForbidden();
    $this->actingAs(formsAdmin())->get(route('forms.index'))->assertOk()->assertInertia(fn (Assert $page): Assert => $page->component('forms/index')->where('can.create', true));
});

it('builds, publishes, previews and test-submits a form', function (): void {
    $this->actingAs(formsAdmin());
    $form = publishedForm($this, [field('rating', 'radio', ['required' => true, 'options' => [['value' => 'good', 'label' => ['en' => 'Good']], ['value' => 'bad', 'label' => ['en' => 'Bad']]]])]);

    $this->get(route('forms.builder', $form))->assertInertia(fn (Assert $page): Assert => $page
        ->component('forms/builder')
        ->where('form.status', 'published')
        ->where('form.publishedNumber', 1)
        ->where('context.requester.currency', 'SAR'));

    $this->put(route('forms.draft.save', $form), ['schema' => mfs([field('Bad Key', 'text')])])
        ->assertSessionHasErrors(['schema.pages.0.sections.0.fields.0.key', 'error']);

    $this->get(route('forms.preview', $form))->assertInertia(fn (Assert $page): Assert => $page->component('forms/preview')->where('version.number', 1));
    $this->post(route('forms.test', $form), ['values' => ['rating' => 'meh']])->assertSessionHasErrors('rating');
    $this->post(route('forms.test', $form), ['values' => ['rating' => 'good']])->assertSessionHasNoErrors();

    expect(FormSubmission::query()->sole())->source->toBe(SubmissionSource::Test)->data->toBe(['rating' => 'good']);
});

it('lists submissions and audits opening one and its files', function (): void {
    $admin = formsAdmin();
    $this->actingAs($admin);
    $form = publishedForm($this, [field('where', 'branch'), field('proof', 'file', ['validation' => ['accept' => ['pdf']]])]);
    $branch = Branch::query()->firstOrFail();

    $upload = $this->post(route('forms.uploads', $form), ['field' => 'proof', 'file' => UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf')])
        ->assertCreated()->json('id');
    $this->post(route('forms.test', $form), ['values' => ['where' => $branch->ulid, 'proof' => [$upload]]])->assertSessionHasNoErrors();
    $submission = FormSubmission::query()->sole();

    $this->get(route('forms.submissions.index', $form))->assertInertia(fn (Assert $page): Assert => $page->component('forms/submissions')->has('submissions.data', 1));
    $this->getJson(route('forms.submissions.show', [$form, $submission]))
        ->assertOk()
        ->assertJsonPath('submission.answers.where', $branch->ulid)
        ->assertJsonPath('display.where', $branch->getTranslation('name', 'en'));
    $this->get(route('forms.submissions.files', [$form, $submission, $upload]))->assertOk()->assertDownload('proof.pdf');

    expect(Activity::query()->where('log_name', 'forms')->whereIn('event', ['submission.viewed', 'file.downloaded'])->count())->toBe(2);
});

it('serves public forms behind ALTCHA, a honeypot and a minimum fill time', function (): void {
    $this->actingAs(formsAdmin());
    $form = publishedForm($this, [field('comment', 'textarea', ['required' => true])], ['isPublic' => true, 'publicSlug' => 'office-feedback']);
    auth()->logout();

    $started = Crypt::encryptString((string) (time() - 10));
    $this->get('/f/office-feedback')->assertOk()->assertInertia(fn (Assert $page): Assert => $page->component('public/form')->where('closed', false)->where('context.requester', null));

    $this->post('/f/office-feedback', ['values' => ['comment' => 'Hi'], 'startedAt' => $started])->assertSessionHasErrors('altcha');
    $this->post('/f/office-feedback', ['values' => ['comment' => 'Hi'], 'startedAt' => Crypt::encryptString((string) time()), 'altcha' => solvedAltcha($this, 'office-feedback')])
        ->assertSessionHasErrors('error');

    $payload = solvedAltcha($this, 'office-feedback');
    $this->post('/f/office-feedback', ['values' => ['comment' => 'Great coffee'], 'startedAt' => $started, 'altcha' => $payload])->assertRedirect('/f/office-feedback');
    $this->post('/f/office-feedback', ['values' => ['comment' => 'Replay'], 'startedAt' => $started, 'altcha' => $payload])->assertSessionHasErrors('altcha');
    $this->post('/f/office-feedback', ['values' => ['comment' => 'Bot'], 'startedAt' => $started, 'website' => 'http://spam.example'])->assertRedirect();

    expect(FormSubmission::query()->where('form_id', $form->id)->get())->toHaveCount(1)
        ->and(FormSubmission::query()->sole())->source->toBe(SubmissionSource::Public)->submitted_by->toBeNull()->ip_hash->not->toBeNull();
});

it('accepts public uploads only for the visitor session that made them', function (): void {
    $this->actingAs(formsAdmin());
    publishedForm($this, [field('cv', 'file', ['required' => true, 'validation' => ['accept' => ['pdf']]])], ['isPublic' => true, 'publicSlug' => 'jobs']);
    auth()->logout();

    $this->post('/f/jobs/uploads', ['field' => 'cv', 'file' => UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf')])->assertCreated();

    expect(FormUpload::query()->sole())->user_id->toBeNull()->session_hash->not->toBeNull();
});

it('hides closed, private and archived forms from the public', function (): void {
    $this->actingAs(formsAdmin());
    $form = publishedForm($this, [field('a', 'text')], ['isPublic' => true, 'publicSlug' => 'closing', 'closesAt' => '2020-01-01']);
    auth()->logout();

    $this->get('/f/closing')->assertInertia(fn (Assert $page): Assert => $page->where('closed', true)->where('schema', null));
    $this->get('/f/nope')->assertNotFound();

    $form->forceFill(['is_public' => false])->save();
    $this->get('/f/closing')->assertNotFound();
});

it('refuses directory pickers on public forms and archives forms', function (): void {
    $this->actingAs(formsAdmin());
    $form = publishedForm($this, [field('who', 'employee')]);

    $this->put(route('forms.update', $form), ['code' => 'feedback', 'name' => ['en' => 'X'], 'isPublic' => true, 'publicSlug' => 'who'])->assertSessionHasErrors('isPublic');
    $this->delete(route('forms.destroy', $form))->assertRedirect(route('forms.index'));
    expect($form->fresh()?->trashed())->toBeTrue();
});

it('lists active leave types for pickers', function (): void {
    resolve(SeedPolicyDefaults::class)->handle();
    Sanctum::actingAs(userWithRoles('employee'));

    $this->getJson('/api/v1/leave-types?search=annual')->assertOk()->assertJsonPath('data.0.code', 'ANNUAL');
});
