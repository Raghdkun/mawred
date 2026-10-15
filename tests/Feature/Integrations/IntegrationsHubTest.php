<?php

declare(strict_types=1);

use App\Domain\Integrations\Contracts\HealthCheck;
use App\Domain\Integrations\Enums\IntegrationType;
use App\Domain\Integrations\Health\HealthChecks;
use App\Domain\Integrations\Health\HealthResult;
use App\Domain\Integrations\Models\IntegrationConnection;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    Mail::fake();
    seedAccess();
    $this->admin = userWithRoles('hr_admin');
    $this->admin->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();
    $this->connection = IntegrationConnection::query()->create(['type' => 'email', 'name' => 'default', 'status' => 'healthy',
        'config' => ['host' => 'smtp.example.com', 'port' => 587, 'from_address' => 'hr@example.com'], 'credentials' => ['password' => 'super-secret-pass']]);
    $this->outcome = HealthResult::healthy();
    resolve(HealthChecks::class)->register(new readonly class($this) implements HealthCheck
    {
        public function __construct(private object $test) {}

        public function type(): IntegrationType
        {
            return IntegrationType::Email;
        }

        public function check(IntegrationConnection $connection): HealthResult
        {
            return $this->test->outcome;
        }
    });
});

it('shows a card per integration with masked credentials', function (): void {
    $this->actingAs($this->admin)->get(route('integrations.index'))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('integrations/index')->has('cards', 4)
            ->where('cards.0.status', 'healthy')->where('cards.0.details.password', '••••pass')->where('cards.1.status', 'not_configured'));

    expect(json_encode($this->actingAs($this->admin)->get(route('integrations.index'))->viewData('page')))->not->toContain('super-secret');
    $this->actingAs(userWithRoles('employee'))->get(route('integrations.index'))->assertForbidden();
});

it('records health checks and alerts integration admins when the badge changes', function (): void {
    $this->outcome = HealthResult::disconnected('Connection refused');
    $this->artisan('integrations:health')->assertSuccessful();

    expect($this->connection->fresh())->status->toBe('disconnected')->last_error->toBe('Connection refused')
        ->and($this->admin->notifications()->sole()->data)->toMatchArray(['category' => 'integrations', 'title' => 'Email: disconnected']);

    $this->artisan('integrations:health');
    expect($this->admin->notifications()->count())->toBe(1);

    $this->outcome = HealthResult::healthy();
    $this->actingAs($this->admin)->post(route('integrations.check', 'email'))->assertRedirect();
    expect($this->connection->fresh())->status->toBe('healthy')->last_success_at->not->toBeNull()
        ->and($this->admin->notifications()->count())->toBe(2);
});

it('edits email settings from the hub', function (): void {
    $this->actingAs($this->admin)->get(route('integrations.email'))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('email.host', 'smtp.example.com')->where('email.hasPassword', true)->missing('email.password'));

    $this->actingAs($this->admin)->post(route('integrations.email.update'), ['host' => 'smtp.new.example', 'port' => 465, 'security' => 'ssl', 'username' => null, 'password' => null, 'fromAddress' => 'hr@example.com', 'fromName' => 'HR'])
        ->assertRedirect();

    expect($this->connection->fresh())->config->toMatchArray(['host' => 'smtp.new.example', 'security' => 'ssl'])
        ->credentials->toBe(['password' => 'super-secret-pass']);
});
