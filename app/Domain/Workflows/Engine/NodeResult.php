<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Engine;

use Carbon\CarbonImmutable;

/**
 * What a node handler decided: move on (through some handles), wait (for a person or a time),
 * or fail (retryable or not).
 */
final readonly class NodeResult
{
    /**
     * @param  array<string, mixed>  $output
     * @param  list<string>|null  $handles  null = follow every outgoing edge
     */
    private function __construct(
        public string $kind,
        public array $output = [],
        public ?array $handles = null,
        public ?CarbonImmutable $until = null,
        public ?string $error = null,
        public bool $retryable = false,
    ) {}

    /**
     * @param  array<string, mixed>  $output
     * @param  list<string>|null  $handles
     */
    public static function completed(array $output = [], ?array $handles = null): self
    {
        return new self('completed', $output, $handles);
    }

    /** Waits for a person (until = null) or until a time (a timer the tick sweeps). */
    public static function waiting(?CarbonImmutable $until = null): self
    {
        return new self('waiting', until: $until);
    }

    public static function failed(string $error, bool $retryable = true): self
    {
        return new self('failed', error: $error, retryable: $retryable);
    }

    public function isCompleted(): bool
    {
        return $this->kind === 'completed';
    }

    public function isWaiting(): bool
    {
        return $this->kind === 'waiting';
    }
}
