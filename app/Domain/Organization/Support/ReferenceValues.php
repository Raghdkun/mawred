<?php

declare(strict_types=1);

namespace App\Domain\Organization\Support;

use App\Support\Errors\DomainRuleViolation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Shared rules for organization reference records: codes are upper-case and unique per table
 * (archived rows included, since the unique index covers them); empty translations are dropped
 * so the fallback locale is used instead of a blank label.
 */
final class ReferenceValues
{
    public static function code(string $code): string
    {
        return mb_strtoupper(trim($code));
    }

    /**
     * @param  array<string, string|null>  $values
     * @return array<string, string>
     */
    public static function translations(array $values): array
    {
        $clean = [];

        foreach ($values as $locale => $value) {
            if (is_string($value) && trim($value) !== '') {
                $clean[(string) $locale] = trim($value);
            }
        }

        return $clean;
    }

    /**
     * @param  class-string<Model>  $model
     */
    public static function assertCodeAvailable(string $model, string $code, ?Model $current = null): void
    {
        $query = $model::query();

        if (in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
            $query->withoutGlobalScopes();
        }

        $taken = $query->where('code', $code)
            ->when($current?->exists, fn ($q) => $q->whereKeyNot($current?->getKey()))
            ->exists();

        if ($taken) {
            throw new DomainRuleViolation('organization.code_taken', __('This code is already used.'), ['code' => [__('This code is already used.')]]);
        }
    }
}
