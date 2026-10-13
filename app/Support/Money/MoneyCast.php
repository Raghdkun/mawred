<?php

declare(strict_types=1);

namespace App\Support\Money;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Maps a Money attribute onto `<name>_minor` (bigint) and `<name>_currency` (char 3) columns.
 *
 * @implements CastsAttributes<Money|null, Money|null>
 */
final class MoneyCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        $minor = $attributes["{$key}_minor"] ?? null;
        $currency = $attributes["{$key}_currency"] ?? null;

        if (! is_numeric($minor) || ! is_string($currency)) {
            return null;
        }

        return new Money((int) $minor, $currency);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, int|string|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return ["{$key}_minor" => null, "{$key}_currency" => null];
        }

        return ["{$key}_minor" => $value->minor, "{$key}_currency" => $value->currency];
    }
}
