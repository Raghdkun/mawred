<?php

declare(strict_types=1);

use App\Domain\Forms\Actions\PublishForm;
use App\Domain\Forms\Actions\SaveFormDraft;
use App\Domain\Forms\Actions\SaveFormSettings;
use App\Domain\Forms\Actions\SubmitForm;
use App\Domain\Forms\Data\FormSettingsData;
use App\Domain\Forms\Enums\SubmissionSource;
use App\Domain\Forms\Models\Form;
use App\Domain\Forms\Models\FormSubmission;
use App\Domain\Organization\Models\Branch;
use App\Domain\People\Models\Employee;
use App\Support\Actors\Actor;

beforeEach(function (): void {
    seedAccess();
    config(['services.altcha' => ['cost' => 1, 'counter_min' => 1, 'counter_max' => 20]]);
    $this->root = userWithRoles('super_admin');
    $this->root->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();
    Employee::factory()->placedIn(Branch::factory()->riyadh()->create(['name' => ['en' => 'Riyadh HQ', 'ar' => 'المقر الرئيسي']]))->create(['user_id' => $this->root->id]);

    $actor = Actor::user($this->root);
    $this->form = resolve(SaveFormSettings::class)->handle($actor, FormSettingsData::from([
        'code' => 'trip', 'name' => ['en' => 'Business trip', 'ar' => 'رحلة عمل'], 'isPublic' => true, 'publicSlug' => 'trip',
    ]));
    resolve(SaveFormDraft::class)->handle($actor, $this->form, mfs([
        field('destination', 'text', ['label' => ['en' => 'Destination', 'ar' => 'الوجهة'], 'required' => true]),
        field('kind', 'radio', ['label' => ['en' => 'Kind', 'ar' => 'النوع'], 'options' => [['value' => 'client', 'label' => ['en' => 'Client visit', 'ar' => 'زيارة عميل']], ['value' => 'training', 'label' => ['en' => 'Training', 'ar' => 'تدريب']]]]),
        field('client', 'text', ['label' => ['en' => 'Client name', 'ar' => 'اسم العميل'], 'visible' => ['op' => 'eq', 'args' => [['ref' => 'field.kind'], 'client']]]),
        field('dates', 'date_range', ['label' => ['en' => 'Dates', 'ar' => 'التواريخ']]),
        field('nights', 'calculated', ['label' => ['en' => 'Nights', 'ar' => 'الليالي'], 'calc' => ['op' => 'days_between', 'args' => [['ref' => 'field.dates.from'], ['ref' => 'field.dates.to']]]]),
    ]));
    resolve(PublishForm::class)->handle($actor, $this->form);
    resolve(SubmitForm::class)->handle($actor, $this->form, $this->form->publishedVersion()->firstOrFail(), ['destination' => 'Jeddah', 'kind' => 'training'], SubmissionSource::Internal);
});

it('renders the forms screens accessibly in both languages', function (string $locale, string $path): void {
    $this->root->forceFill(['locale' => $locale])->save();
    $this->actingAs($this->root);

    visit(str_replace('{form}', $this->form->ulid, $path))
        ->wait(0.8)
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
})->with(['en', 'ar'])->with([
    'list' => '/forms',
    'builder' => '/forms/{form}/builder',
    'preview' => '/forms/{form}/preview',
    'submissions' => '/forms/{form}/submissions',
]);

it('renders the public form accessibly in both languages', function (string $locale): void {
    $page = visit('/f/trip')->wait(0.6);

    if ($locale === 'ar') {
        $page->click('العربية')->wait(0.8);
    }

    $page
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
})->with(['en', 'ar']);

it('adds a field in the builder, saves and publishes a new version', function (): void {
    $this->actingAs($this->root);

    visit("/forms/{$this->form->ulid}/builder")
        ->wait(0.8)
        ->click('[aria-label="Add Email"]')
        ->fill('[aria-label="Label (EN)"]', 'Work email')
        ->click('Save draft')
        ->wait(1)
        ->assertSee('Unpublished changes')
        ->click('Publish')
        ->wait(1)
        ->assertSee('Version 2 published.')
        ->assertNoJavaScriptErrors();

    expect(Form::query()->sole()->publishedVersion?->number)->toBe(2);
});

it('shows conditional fields, calculates and records a test submission', function (): void {
    $this->actingAs($this->root);

    visit("/forms/{$this->form->ulid}/preview")
        ->wait(0.8)
        ->assertDontSee('Client name')
        ->click('Client visit')
        ->assertSee('Client name')
        ->fill('#field-destination', 'Riyadh')
        ->fill('#field-client', 'Acme')
        ->fill('#field-dates-from', '2026-11-01')
        ->fill('#field-dates-to', '2026-11-04')
        ->assertSeeIn('#field-nights', '3')
        ->click('Send test submission')
        ->wait(1)
        ->assertSee('Test submission recorded.')
        ->assertNoJavaScriptErrors();

    expect(FormSubmission::query()->where('source', 'test')->sole()->data)->toMatchArray(['destination' => 'Riyadh', 'client' => 'Acme', 'nights' => 3]);
});

it('takes a public submission once the proof-of-work is solved', function (): void {
    visit('/f/trip')
        ->wait(0.6)
        ->fill('#field-destination', 'Dammam')
        ->click('Training')
        ->wait(3)
        ->click('Send')
        ->wait(1.2)
        ->assertSee('Thank you')
        ->assertNoJavaScriptErrors();

    expect(FormSubmission::query()->where('source', 'public')->sole()->data)->toMatchArray(['destination' => 'Dammam', 'kind' => 'training']);
});

/*
 * Visual check: 390 / 834 / 1440 × LTR / RTL; dark for the builder (★). Run with SCREENSHOTS=1.
 */
it('captures the forms screens for review', function (string $locale): void {
    $this->root->forceFill(['locale' => $locale])->save();
    $this->actingAs($this->root);
    $pages = ['list' => '/forms', 'builder' => "/forms/{$this->form->ulid}/builder", 'preview' => "/forms/{$this->form->ulid}/preview", 'submissions' => "/forms/{$this->form->ulid}/submissions"];

    foreach ($pages as $name => $path) {
        visit($path)->on()->mobile()->wait(0.8)->screenshot(true, "forms-{$name}-{$locale}-390");
        visit($path)->on()->iPadPro()->wait(0.8)->screenshot(true, "forms-{$name}-{$locale}-834");
        visit($path)->on()->macbook14()->wait(0.8)->screenshot(true, "forms-{$name}-{$locale}-1440");
    }

    visit($pages['builder'])->on()->macbook14()->inDarkMode()->wait(0.8)->screenshot(true, "forms-builder-{$locale}-dark");
})->with(['en', 'ar'])->skip(fn (): bool => ! env('SCREENSHOTS'), 'Set SCREENSHOTS=1 to capture.');
