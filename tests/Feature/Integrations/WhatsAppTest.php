<?php

declare(strict_types=1);

use App\Domain\Integrations\Actions\ConfirmWhatsAppNumber;
use App\Domain\Integrations\Actions\SaveWhatsAppSettings;
use App\Domain\Integrations\Actions\StartWhatsAppVerification;
use App\Domain\Integrations\Enums\IntegrationType;
use App\Domain\Integrations\Models\IntegrationConnection;
use App\Domain\Integrations\Models\WhatsAppContact;
use App\Domain\Integrations\Models\WhatsAppMessage;
use App\Domain\Integrations\Models\WhatsAppTemplate;
use App\Domain\Notifications\Mail\NotificationMail;
use App\Domain\Notifications\Models\NotificationTemplate;
use App\Domain\Organization\Models\Branch;
use App\Domain\People\Models\Employee;
use App\Domain\Requests\Actions\SeedRequestTypes;
use App\Domain\Requests\Actions\SubmitRequest;
use App\Domain\Requests\Models\EmployeeRequest;
use App\Domain\Requests\Models\RequestType;
use App\Domain\Workflows\Enums\TaskStatus;
use App\Domain\Workflows\Models\WorkflowTask;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    Mail::fake();
    seedAccess();
    resolve(SeedRequestTypes::class)->handle();
    Http::fake([
        'graph.facebook.com/*/messages' => Http::response(['messages' => [['id' => 'wamid.TEST1']]]),
        'graph.facebook.com/*/message_templates*' => Http::response(['data' => [
            ['name' => 'approval_request', 'language' => 'en', 'status' => 'APPROVED', 'category' => 'UTILITY', 'components' => [
                ['type' => 'BODY', 'text' => 'Approval needed: {{1}} from {{2}}'],
                ['type' => 'BUTTONS', 'buttons' => [['type' => 'QUICK_REPLY', 'text' => 'Approve'], ['type' => 'QUICK_REPLY', 'text' => 'Reject']]],
            ]],
        ]]),
        'graph.facebook.com/*' => Http::response(['display_phone_number' => '+966 50 000 0000', 'quality_rating' => 'GREEN']),
        'waha.test/api/sendText' => Http::response(['id' => ['_serialized' => 'true_9665@c.us_ABC']]),
        'waha.test/api/sessions/default' => Http::response(['name' => 'default', 'status' => 'SCAN_QR_CODE']),
        'waha.test/api/default/auth/qr*' => Http::response(['mimetype' => 'image/png', 'data' => 'iVBORw0KGgo=']),
        'waha.test/*' => Http::response([]),
    ]);

    $this->admin = userWithRoles('hr_admin');
    $this->admin->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();
    $branch = Branch::factory()->riyadh()->create();
    $this->managerUser = userWithRoles('employee');
    $manager = Employee::factory()->placedIn($branch)->create(['user_id' => $this->managerUser->id]);
    $this->requesterUser = userWithRoles('employee');
    Employee::factory()->placedIn($branch, null, $manager)->create(['user_id' => $this->requesterUser->id]);

    $this->official = resolve(SaveWhatsAppSettings::class)->handle(Actor::system('test'), IntegrationType::WhatsAppOfficial,
        ['phone_number_id' => '1065403522', 'waba_id' => '1022901293'], ['access_token' => 'EAAG-token', 'app_secret' => 'app-secret-1']);
    $this->official->forceFill(['status' => 'healthy'])->save();
    WhatsAppContact::query()->create(['user_id' => $this->managerUser->id, 'phone_e164' => '+966501112233', 'verified_at' => now()]);
});

function signedMeta(object $test, array $payload): TestResponse
{
    $body = (string) json_encode($payload);

    return $test->call('POST', '/hooks/whatsapp/official', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'app-secret-1')], $body);
}

function submitAsset(object $test, string $code = 'ASSET_REQUEST', array $values = ['item' => 'laptop', 'details' => 'New']): EmployeeRequest
{
    return resolve(SubmitRequest::class)->handle(Actor::user($test->requesterUser), RequestType::query()->where('code', $code)->sole(), $values);
}

it('answers Meta’s verification handshake only with our token', function (): void {
    $token = $this->official->credentials['verify_token'];

    $this->get('/hooks/whatsapp/official?hub.mode=subscribe&hub.verify_token='.$token.'&hub.challenge=12345')->assertOk()->assertSee('12345');
    $this->get('/hooks/whatsapp/official?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=12345')->assertForbidden();
});

it('rejects webhooks without a valid signature', function (): void {
    $this->postJson('/hooks/whatsapp/official', ['entry' => []])->assertUnauthorized();
    $this->call('POST', '/hooks/whatsapp/official', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256=bad'], '{}')->assertUnauthorized();
});

it('sends approvals over WhatsApp first and keeps email as the fallback (D59)', function (): void {
    WhatsAppContact::query()->where('user_id', $this->managerUser->id)->update(['last_inbound_at' => now()]);
    submitAsset($this);

    expect(WhatsAppMessage::query()->where('user_id', $this->managerUser->id)->sole())->status->toBe('sent')->provider_id->toBe('wamid.TEST1');
    Mail::assertNotQueued(NotificationMail::class, fn (NotificationMail $mail): bool => $mail->hasTo($this->managerUser->email));

    // A disconnected driver is skipped and the chain moves on to email.
    $this->official->forceFill(['status' => 'disconnected'])->save();
    submitAsset($this, 'BUSINESS_TRIP', ['destination' => 'Jeddah', 'dates' => ['from' => now()->addWeek()->toDateString(), 'to' => now()->addWeek()->toDateString()], 'purpose' => 'Visit']);

    Mail::assertQueued(NotificationMail::class, fn (NotificationMail $mail): bool => $mail->hasTo($this->managerUser->email));
});

it('falls back to email when no template is mapped and the 24-hour window is closed', function (): void {
    submitAsset($this);

    expect(WhatsAppMessage::query()->sole())->status->toBe('failed')->error_code->toBe('no_template');
    Mail::assertQueued(NotificationMail::class, fn (NotificationMail $mail): bool => $mail->hasTo($this->managerUser->email));
});

it('uses the mapped template with one-tap buttons outside the 24-hour window', function (): void {
    NotificationTemplate::query()->create(['event' => 'approvals.task_assigned', 'channel' => 'whatsapp', 'locale' => 'en', 'body' => '{{ requester }}',
        'wa_template' => ['name' => 'approval_request', 'language' => 'en', 'params' => ['request_type', 'requester'], 'buttons' => ['approved', 'rejected']]]);

    submitAsset($this);

    Http::assertSent(function (HttpRequest $request): bool {
        $template = $request['template'] ?? null;

        return str_ends_with($request->url(), '/messages') && is_array($template) && $template['name'] === 'approval_request'
            && $template['components'][0]['parameters'][0]['text'] === 'Equipment or access'
            && str_starts_with($template['components'][1]['parameters'][0]['payload'], 'mawred:');
    });
});

it('approves from a quick-reply tap on the assignee’s verified number', function (): void {
    NotificationTemplate::query()->create(['event' => 'approvals.task_assigned', 'channel' => 'whatsapp', 'locale' => 'en', 'body' => '{{ requester }}',
        'wa_template' => ['name' => 'approval_request', 'language' => 'en', 'params' => [], 'buttons' => ['approved', 'rejected']]]);
    $request = submitAsset($this);
    $payload = null;
    Http::assertSent(function (HttpRequest $sent) use (&$payload): bool {
        $payload ??= $sent['template']['components'][0]['parameters'][0]['payload'] ?? null;

        return true;
    });

    $tap = fn (string $from) => signedMeta($this, ['entry' => [['changes' => [['value' => ['messages' => [
        ['id' => 'wamid.IN'.$from, 'from' => $from, 'type' => 'button', 'button' => ['payload' => $payload, 'text' => 'Approve']],
    ]]]]]]]);

    $tap('966509999999')->assertOk();
    expect(WorkflowTask::query()->where('workflow_instance_id', $request->workflow_instance_id)->where('assignee_user_id', $this->managerUser->id)->sole()->status)->toBe(TaskStatus::Open);

    $tap('966501112233')->assertOk();
    $decided = WorkflowTask::query()->where('workflow_instance_id', $request->workflow_instance_id)->where('assignee_user_id', $this->managerUser->id)->sole();
    expect($decided->status)->toBe(TaskStatus::Done)->and($decided->decided_via?->value)->toBe('whatsapp');
});

it('records delivery receipts', function (): void {
    WhatsAppContact::query()->where('user_id', $this->managerUser->id)->update(['last_inbound_at' => now()]);
    submitAsset($this);
    signedMeta($this, ['entry' => [['changes' => [['value' => ['statuses' => [['id' => 'wamid.TEST1', 'status' => 'delivered']]]]]]]])->assertOk();
    signedMeta($this, ['entry' => [['changes' => [['value' => ['statuses' => [['id' => 'wamid.TEST1', 'status' => 'read']]]]]]]]);

    expect(WhatsAppMessage::query()->where('provider_id', 'wamid.TEST1')->sole())->status->toBe('read')->delivered_at->not->toBeNull();
});

it('verifies a number with a one-time code before using it (D58)', function (): void {
    resolve(SaveWhatsAppSettings::class)->handle(Actor::system('test'), IntegrationType::WhatsAppWaha, ['base_url' => 'http://waha.test', 'session' => 'default'], ['api_key' => 'k']);
    IntegrationConnection::query()->where('type', 'whatsapp_waha')->update(['status' => 'healthy']);
    $code = null;

    resolve(StartWhatsAppVerification::class)->handle($this->requesterUser, '0501234567');
    Http::assertSent(function (HttpRequest $request) use (&$code): bool {
        if (str_ends_with($request->url(), '/api/sendText') && preg_match('/\b(\d{6})\b/', (string) $request['text'], $match) === 1) {
            $code = $match[1];

            return $request['chatId'] === '966501234567@c.us';
        }

        return false;
    });

    expect(fn () => resolve(ConfirmWhatsAppNumber::class)->handle($this->requesterUser, '000000'))->toThrow(DomainRuleViolation::class);
    resolve(ConfirmWhatsAppNumber::class)->handle($this->requesterUser, (string) $code);

    expect(WhatsAppContact::query()->where('user_id', $this->requesterUser->id)->sole())->phone_e164->toBe('+966501234567')->verified_at->not->toBeNull();
});

it('syncs templates and pairs the unofficial session from the setup page', function (): void {
    resolve(SaveWhatsAppSettings::class)->handle(Actor::system('test'), IntegrationType::WhatsAppWaha, ['base_url' => 'http://waha.test', 'session' => 'default'], ['api_key' => 'k']);

    $this->actingAs($this->admin)->post(route('integrations.whatsapp.sync'))->assertRedirect();
    expect(WhatsAppTemplate::query()->sole())->name->toBe('approval_request')->status->toBe('APPROVED');

    $this->actingAs($this->admin)->getJson(route('integrations.whatsapp.pairing'))->assertOk()
        ->assertJson(['status' => 'SCAN_QR_CODE', 'qr' => 'data:image/png;base64,iVBORw0KGgo=']);

    $this->actingAs($this->admin)->get(route('integrations.whatsapp'))->assertOk()
        ->assertInertia(fn ($page) => $page->component('integrations/whatsapp')->where('official.accessToken', '••••oken')->has('templates', 1)->where('routing.0.chain', ['whatsapp_official', 'email']));
    $this->actingAs($this->requesterUser)->get(route('integrations.whatsapp'))->assertForbidden();
});

it('warns when a critical category relies on the unofficial driver alone', function (): void {
    $this->actingAs($this->admin)->put(route('integrations.whatsapp.routing'), ['chains' => ['approvals' => ['whatsapp_waha'], 'requests' => ['whatsapp_official', 'whatsapp_waha']], 'verificationTemplate' => null])->assertRedirect();

    $this->actingAs($this->admin)->get(route('integrations.whatsapp'))
        ->assertInertia(fn ($page) => $page->where('routing.0.category', 'approvals')->where('routing.0.chain', ['whatsapp_waha', 'email'])->where('routing.0.risky', true));
});

it('checks WAHA webhook signatures and opens the reply window', function (): void {
    $waha = resolve(SaveWhatsAppSettings::class)->handle(Actor::system('test'), IntegrationType::WhatsAppWaha, ['base_url' => 'http://waha.test', 'session' => 'default'], ['api_key' => 'k']);
    $body = (string) json_encode(['id' => 'evt_1', 'event' => 'message', 'session' => 'default', 'payload' => ['from' => '966501112233@c.us', 'fromMe' => false, 'body' => 'hi']]);

    $this->call('POST', '/hooks/whatsapp/waha', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_WEBHOOK_HMAC' => 'bad'], $body)->assertUnauthorized();
    $this->call('POST', '/hooks/whatsapp/waha', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_WEBHOOK_HMAC' => hash_hmac('sha512', $body, $waha->credentials['webhook_key'])], $body)->assertOk();

    expect(WhatsAppContact::query()->where('user_id', $this->managerUser->id)->sole()->inServiceWindow())->toBeTrue();
});
