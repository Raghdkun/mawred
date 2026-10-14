<?php

declare(strict_types=1);

use App\Domain\Forms\Actions\ArchiveForm;
use App\Domain\Forms\Actions\DiscardFormDraft;
use App\Domain\Forms\Actions\DuplicateForm;
use App\Domain\Forms\Actions\PublishForm;
use App\Domain\Forms\Actions\SaveFormDraft;
use App\Domain\Forms\Actions\SaveFormSettings;
use App\Domain\Forms\Actions\StoreFormUpload;
use App\Domain\Forms\Actions\SubmitForm;
use App\Domain\Forms\Data\FormSettingsData;
use App\Domain\Forms\Enums\SubmissionSource;
use App\Domain\Forms\Enums\VersionStatus;
use App\Domain\Forms\Events\FormSubmitted;
use App\Domain\Forms\Models\FormUpload;
use App\Domain\Forms\Models\FormVersion;
use App\Domain\Organization\Models\Branch;
use App\Domain\People\Models\Employee;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use App\Support\Files\FileScanner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    seedAccess();
    Storage::fake('local');
    $this->user = userWithRoles('employee');
    $this->actor = Actor::user($this->user);
    $this->employee = Employee::factory()->placedIn(Branch::factory()->riyadh()->create())->create(['user_id' => $this->user->id]);
    $this->form = resolve(SaveFormSettings::class)->handle($this->actor, FormSettingsData::from(['code' => 'trip', 'name' => ['en' => 'Business trip']]));
    $this->schema = mfs([
        field('destination', 'text', ['required' => true]),
        field('nights', 'number', ['validation' => ['min' => 1, 'integer' => true]]),
        field('ticket', 'file', ['validation' => ['maxFiles' => 1, 'accept' => ['pdf']]]),
    ]);
});

function publish(object $test): FormVersion
{
    resolve(SaveFormDraft::class)->handle($test->actor, $test->form, $test->schema);

    return resolve(PublishForm::class)->handle($test->actor, $test->form);
}

it('versions a form: drafts are edited, published versions frozen', function (): void {
    expect($this->form->code)->toBe('TRIP')->and($this->form->versions()->sole()->status)->toBe(VersionStatus::Draft);

    $v1 = publish($this);
    expect($v1->number)->toBe(1)->and($this->form->fresh()?->published_version_id)->toBe($v1->id);

    $draft = resolve(SaveFormDraft::class)->handle($this->actor, $this->form, mfs([field('destination', 'text')]));
    expect($draft->number)->toBe(2)->and($v1->fresh()?->schema)->toEqual($this->schema);

    $v2 = resolve(PublishForm::class)->handle($this->actor, $this->form);
    expect($v1->fresh()?->status)->toBe(VersionStatus::Archived)->and($v2->status)->toBe(VersionStatus::Published);

    expect(fn () => resolve(PublishForm::class)->handle($this->actor, $this->form))->toThrow(DomainRuleViolation::class);
});

it('refuses an invalid draft and discards unpublished changes', function (): void {
    expect(fn () => resolve(SaveFormDraft::class)->handle($this->actor, $this->form, mfs([field('BAD', 'text')])))
        ->toThrow(fn (DomainRuleViolation $e) => expect($e->details)->toHaveKey('schema.pages.0.sections.0.fields.0.key'));

    publish($this);
    resolve(SaveFormDraft::class)->handle($this->actor, $this->form, mfs([field('other', 'text')]));
    resolve(DiscardFormDraft::class)->handle($this->form);

    expect($this->form->versions()->count())->toBe(1);
});

it('duplicates into a new unpublished form and archives', function (): void {
    publish($this);
    $copy = resolve(DuplicateForm::class)->handle($this->actor, $this->form, 'trip_copy', ['en' => 'Trip (copy)']);

    expect($copy->published_version_id)->toBeNull()
        ->and($copy->draft()->sole()->schema)->toEqual($this->schema);

    resolve(ArchiveForm::class)->handle($this->form);
    expect($this->form->fresh()?->trashed())->toBeTrue();
});

it('accepts uploads that fit the field, scanned, and attaches them on submit', function (): void {
    Event::fake([FormSubmitted::class]);
    $version = publish($this);
    $upload = resolve(StoreFormUpload::class)->handle($this->form, $version, 'ticket', UploadedFile::fake()->create('ticket.pdf', 100, 'application/pdf'), $this->user, null);

    $submission = resolve(SubmitForm::class)->handle($this->actor, $this->form, $version, ['destination' => 'Jeddah', 'nights' => '3', 'ticket' => [$upload->ulid]], SubmissionSource::Internal);

    expect($submission->data)->toMatchArray(['destination' => 'Jeddah', 'nights' => 3])
        ->and($submission->data['ticket'][0]['name'])->toBe('ticket.pdf')
        ->and($submission->employee_id)->toBe($this->employee->id)
        ->and($upload->fresh()?->form_submission_id)->toBe($submission->id)
        ->and(DB::table('form_submissions')->value('data'))->not->toContain('Jeddah');

    Event::assertDispatched(FormSubmitted::class);
});

it('refuses the wrong file type, oversize files and files flagged by the scanner', function (): void {
    $version = publish($this);
    $store = fn (UploadedFile $file) => resolve(StoreFormUpload::class)->handle($this->form, $version, 'ticket', $file, $this->user, null);

    expect(fn () => $store(UploadedFile::fake()->image('photo.png')))->toThrow(DomainRuleViolation::class, 'not accepted')
        ->and(fn () => $store(UploadedFile::fake()->create('big.pdf', 20000, 'application/pdf')))->toThrow(DomainRuleViolation::class, 'larger')
        ->and(fn () => resolve(StoreFormUpload::class)->handle($this->form, $version, 'destination', UploadedFile::fake()->create('a.pdf', 1, 'application/pdf'), $this->user, null))->toThrow(DomainRuleViolation::class);

    $this->app->instance(FileScanner::class, new class implements FileScanner
    {
        public function isClean(string $path): bool
        {
            return false;
        }
    });

    expect(fn () => resolve(StoreFormUpload::class)->handle($this->form, $version, 'ticket', UploadedFile::fake()->create('t.pdf', 1, 'application/pdf'), $this->user, null))
        ->toThrow(DomainRuleViolation::class, 'virus');
});

it('never lets one person claim another person’s upload', function (): void {
    $version = publish($this);
    $theirs = resolve(StoreFormUpload::class)->handle($this->form, $version, 'ticket', UploadedFile::fake()->create('t.pdf', 1, 'application/pdf'), userWithRoles('employee'), null);

    expect(fn () => resolve(SubmitForm::class)->handle($this->actor, $this->form, $version, ['destination' => 'X', 'ticket' => [$theirs->ulid]], SubmissionSource::Internal))
        ->toThrow(ValidationException::class);

    $this->travel(25)->hours();
    $this->artisan('model:prune', ['--model' => [FormUpload::class]]);
    expect(FormUpload::query()->count())->toBe(0);
});
