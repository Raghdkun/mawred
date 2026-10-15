<?php

declare(strict_types=1);

use App\Domain\Integrations\Actions\SaveCognitoSource;
use App\Domain\Integrations\Actions\SaveWhatsAppSettings;
use App\Domain\Integrations\Data\CognitoSourceData;
use App\Domain\Integrations\Enums\IntegrationType;
use App\Domain\Integrations\Models\IntegrationConnection;
use App\Domain\Integrations\Models\WhatsAppTemplate;
use App\Domain\Organization\Models\Branch;
use App\Domain\People\Models\Employee;
use App\Domain\Requests\Actions\SeedRequestTypes;
use App\Domain\Requests\Models\RequestType;
use App\Support\Actors\Actor;
use Illuminate\Support\Facades\Http;

/*
| Phase 4 screens (EN + AR, axe): integrations hub, email, Cognito, WhatsApp setup, and the
| WhatsApp opt-in on notification settings.
*/
beforeEach(function (): void {
    Http::fake(['*' => Http::response(['status' => 'SCAN_QR_CODE'])]);
    seedAccess();
    resolve(SeedRequestTypes::class)->handle();
    $this->admin = userWithRoles('hr_admin');
    $this->admin->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();
    Employee::factory()->placedIn(Branch::factory()->riyadh()->create())->create(['user_id' => $this->admin->id]);

    IntegrationConnection::query()->create(['type' => 'email', 'name' => 'default', 'status' => 'healthy', 'config' => ['host' => 'smtp.example.com', 'port' => 587, 'from_address' => 'hr@example.com', 'from_name' => 'HR'], 'credentials' => ['password' => 'secret-pass']]);
    resolve(SaveWhatsAppSettings::class)->handle(Actor::system('test'), IntegrationType::WhatsAppOfficial, ['phone_number_id' => '1065403522', 'waba_id' => '1022901293'], ['access_token' => 'token', 'app_secret' => 'secret']);
    IntegrationConnection::query()->where('type', 'whatsapp_official')->update(['status' => 'healthy']);
    WhatsAppTemplate::query()->create(['name' => 'approval_request', 'language' => 'en', 'status' => 'APPROVED', 'category' => 'UTILITY', 'components' => [['type' => 'BODY', 'text' => 'Approval needed: {{1}}']], 'synced_at' => now()]);
    [$this->source] = resolve(SaveCognitoSource::class)->handle(Actor::system('test'), CognitoSourceData::from([
        'name' => 'Expenses (Cognito)', 'requestTypeId' => RequestType::query()->where('code', 'EXPENSE')->value('ulid'), 'formUrl' => 'https://www.cognitoforms.com/Najd/Expenses',
        'fields' => [['from' => 'Description', 'to' => 'description']], 'requesterBy' => 'email', 'requesterPath' => 'Email', 'entryPath' => null, 'versionPath' => null, 'prefill' => ['email' => 'Email'], 'isActive' => true,
    ]));
});

it('renders the integration screens accessibly in both languages', function (string $locale): void {
    $this->admin->forceFill(['locale' => $locale])->save();
    $this->actingAs($this->admin);

    foreach (['/integrations', '/integrations/email', '/integrations/cognito', "/integrations/cognito/sources/{$this->source->ulid}", '/integrations/whatsapp', '/notifications/preferences'] as $path) {
        visit($path)->wait(1)->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    }
})->with(['en', 'ar']);

it('walks through the WhatsApp setup steps', function (): void {
    $this->actingAs($this->admin);

    visit('/integrations/whatsapp')
        ->wait(1)
        ->click('Next')
        ->assertSee('Phone number ID')
        ->assertSee('Verify token')
        ->click('Next')
        ->assertSee('Routing')
        ->click('Next')
        ->assertSee('Invite people')
        ->assertNoJavaScriptErrors();
});

/*
 * Visual check: 390 / 834 / 1440 × LTR / RTL; dark for the hub. Run with SCREENSHOTS=1.
 */
it('captures the integration screens for review', function (string $locale): void {
    $this->admin->forceFill(['locale' => $locale])->save();
    $this->actingAs($this->admin);
    $pages = ['hub' => '/integrations', 'cognito' => '/integrations/cognito', 'source' => "/integrations/cognito/sources/{$this->source->ulid}", 'whatsapp' => '/integrations/whatsapp', 'preferences' => '/notifications/preferences'];

    foreach ($pages as $name => $path) {
        visit($path)->on()->mobile()->wait(0.8)->screenshot(true, "integrations-{$name}-{$locale}-390");
        visit($path)->on()->iPadPro()->wait(0.8)->screenshot(true, "integrations-{$name}-{$locale}-834");
        visit($path)->on()->macbook14()->wait(0.8)->screenshot(true, "integrations-{$name}-{$locale}-1440");
    }

    visit('/integrations')->on()->macbook14()->inDarkMode()->wait(0.8)->screenshot(true, "integrations-hub-{$locale}-dark");
})->with(['en', 'ar'])->skip(fn (): bool => ! env('SCREENSHOTS'), 'Set SCREENSHOTS=1 to capture.');
