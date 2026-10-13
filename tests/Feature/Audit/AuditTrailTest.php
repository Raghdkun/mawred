<?php

declare(strict_types=1);

use App\Domain\Access\Models\User;
use App\Domain\Audit\Concerns\Auditable;
use App\Domain\Audit\Contracts\MasksAuditedAttributes;
use App\Domain\Audit\Models\Activity;
use App\Domain\Audit\Support\AuditContext;
use App\Domain\Audit\Support\LogAuditedActivity;
use App\Support\Actors\Channel;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Schema;

it('records model writes with old and new values', function (): void {
    $user = User::factory()->create(['name' => 'Before']);
    $user->update(['name' => 'After']);

    $entry = Activity::query()->where('subject_id', $user->id)->where('event', 'updated')->sole();

    expect($entry->attribute_changes?->get('attributes'))->toBe(['name' => 'After'])
        ->and($entry->attribute_changes?->get('old'))->toBe(['name' => 'Before']);
});

it('never logs secrets', function (): void {
    $user = User::factory()->create();
    $user->update(['password' => 'new-secret-password']);

    $logged = Activity::query()->where('subject_id', $user->id)->get()
        ->flatMap(fn (Activity $a): array => array_keys((array) $a->attribute_changes?->get('attributes', [])));

    expect($logged)->not->toContain('password')->not->toContain('remember_token');
});

it('masks sensitive attributes but records that they changed', function (): void {
    Schema::create('audit_probes', function ($table): void {
        $table->id();
        $table->string('name');
        $table->string('national_id')->nullable();
        $table->timestampsTz();
    });

    $probe = new #[Unguarded] #[Table(name: 'audit_probes')] class extends Model implements MasksAuditedAttributes
    {
        use Auditable;

        public function auditMaskedAttributes(): array
        {
            return ['national_id'];
        }
    };

    $model = $probe->newQuery()->create(['name' => 'A', 'national_id' => '1234567890']);
    $model->update(['national_id' => '0987654321']);

    $entry = Activity::query()->where('subject_type', $model::class)->where('event', 'updated')->sole();

    expect($entry->attribute_changes?->get('attributes'))->toBe(['national_id' => LogAuditedActivity::MASK])
        ->and($entry->attribute_changes?->get('old'))->toBe(['national_id' => LogAuditedActivity::MASK])
        ->and(json_encode($entry->getAttributes()))->not->toContain('1234567890')->not->toContain('0987654321');
});

it('stamps channel, client, impersonator and request id', function (): void {
    Context::add('request_id', 'req-123');
    AuditContext::forRequest(Channel::Api, '10.0.0.1', 'Flutter/1.0', 42);

    $user = User::factory()->create();

    $entry = Activity::query()->where('subject_id', $user->id)->where('event', 'created')->sole();

    expect($entry->channel)->toBe('api')
        ->and($entry->request_id)->toBe('req-123')
        ->and($entry->impersonator_id)->toBe(42)
        ->and($entry->ip)->toBe('10.0.0.1')
        ->and($entry->user_agent_hash)->toBe(hash('sha256', 'Flutter/1.0'));
});
