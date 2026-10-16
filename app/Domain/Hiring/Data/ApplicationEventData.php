<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Data;

use App\Domain\Hiring\Models\ApplicationEvent;
use App\Domain\Hiring\Support\Texts;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One line of an application's timeline. */
#[TypeScript]
final class ApplicationEventData extends Data
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public string $kind,
        public string $at,
        public ?string $actor,
        public ?string $fromStage,
        public ?string $toStage,
        public ?string $body,
        public array $data,
    ) {}

    public static function fromEvent(ApplicationEvent $event): self
    {
        $locale = app()->getLocale();

        return new self(
            $event->kind->value,
            $event->created_at->toIso8601String(),
            $event->actor?->name,
            Texts::one($event->fromStage, 'name', $locale),
            Texts::one($event->toStage, 'name', $locale),
            $event->body,
            $event->data ?? [],
        );
    }
}
