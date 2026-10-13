<?php

declare(strict_types=1);

namespace App\Domain\Audit\Data;

use App\Domain\Access\Models\User;
use App\Domain\Audit\Models\Activity;
use Illuminate\Database\Eloquent\Model;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One row of the audit log (screen 43). The internal row id is never exposed; `key` is an
 * opaque, non-reversible handle for list rendering.
 */
#[TypeScript]
final class AuditEntryData extends Data
{
    /**
     * @param  array{id: string, name: string}|null  $actor
     * @param  array{type: string, id: string|null, name: string|null}|null  $subject
     * @param  array<string, mixed>  $changes
     * @param  array<string, mixed>  $properties
     */
    public function __construct(
        public string $key,
        public string $occurredAt,
        public ?string $log,
        public ?string $event,
        public string $description,
        public ?array $actor,
        public ?string $impersonator,
        public ?array $subject,
        public array $changes,
        public array $properties,
        public ?string $channel,
        public ?string $ip,
        public ?string $requestId,
    ) {}

    public static function fromActivity(Activity $activity): self
    {
        $causer = $activity->causer;
        $subject = $activity->subject;

        return new self(
            key: substr(hash_hmac('sha256', $activity->id.'|'.$activity->created_at?->toIso8601String(), config()->string('app.key')), 0, 20),
            occurredAt: $activity->created_at?->toIso8601String() ?? '',
            log: $activity->log_name,
            event: $activity->event,
            description: $activity->description,
            actor: $causer instanceof User ? ['id' => $causer->ulid, 'name' => $causer->name] : null,
            impersonator: $activity->impersonator?->name,
            subject: $activity->subject_type !== null ? [
                'type' => class_basename($activity->subject_type),
                'id' => $subject instanceof Model && is_string($subject->getAttribute('ulid')) ? $subject->getAttribute('ulid') : null,
                'name' => $subject instanceof Model ? self::subjectName($subject) : null,
            ] : null,
            changes: self::stringKeyed($activity->attribute_changes?->all() ?? []),
            properties: self::stringKeyed($activity->properties?->all() ?? []),
            channel: $activity->channel,
            ip: $activity->ip,
            requestId: $activity->request_id,
        );
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<string, mixed>
     */
    private static function stringKeyed(array $values): array
    {
        $result = [];

        foreach ($values as $key => $value) {
            $result[(string) $key] = $value;
        }

        return $result;
    }

    private static function subjectName(Model $subject): ?string
    {
        // Prefer a translated display label (e.g. roles) over an internal key like `hr_officer`.
        foreach (['label', 'name', 'title', 'email'] as $attribute) {
            if (! array_key_exists($attribute, $subject->getAttributes())) {
                continue;
            }

            $value = $subject->getAttribute($attribute);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
