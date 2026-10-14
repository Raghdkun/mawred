<?php

declare(strict_types=1);

namespace App\Domain\Policies\Keys;

use App\Domain\Organization\Models\Branch;
use App\Domain\Policies\Enums\PolicySection;
use Spatie\LaravelData\Data;

/**
 * One configurable rule (ADR-0010): a stable id, a typed value, a default and the editor section.
 * Values are validated by the value class; there is no free-text formula anywhere.
 */
abstract class PolicyKey
{
    abstract public function id(): string;

    abstract public function section(): PolicySection;

    /**
     * @return class-string<Data>
     */
    abstract public function valueClass(): string;

    /** The Neutral default preset value (D49). */
    abstract public function default(): Data;

    /**
     * Domains whose results change with this key (used by impact previews).
     *
     * @return list<string>
     */
    public function affects(): array
    {
        return [];
    }

    /** A value taken from the branch record when no policy set defines the key (D46). */
    public function branchFallback(Branch $branch): ?Data
    {
        return null;
    }

    /**
     * @param  array<string, mixed>  $value
     */
    public function validate(array $value): Data
    {
        $class = $this->valueClass();

        return $class::validateAndCreate($value);
    }

    /**
     * @param  array<string, mixed>  $value
     */
    public function hydrate(array $value): Data
    {
        $class = $this->valueClass();

        return $class::from($value);
    }

    /**
     * A value as stored and serialised: its fields keyed by name.
     *
     * @return array<string, mixed>
     */
    public function serialize(Data $value): array
    {
        $fields = [];

        foreach ($value->toArray() as $field => $item) {
            $fields[(string) $field] = $item;
        }

        return $fields;
    }
}
