<?php

declare(strict_types=1);

namespace App\Support\Money;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Like MoneyCast, but the amount in `<name>_minor` is encrypted at rest (ADR-0007: salary).
 * The currency stays readable so reports can group without decrypting.
 *
 * @implements CastsAttributes<Money|null, Money|null>
 */
final class EncryptedMoneyCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        $cipher = $attributes["{$key}_minor"] ?? null;
        $currency = $attributes["{$key}_currency"] ?? null;

        if (! is_string($cipher) || ! is_string($currency)) {
            return null;
        }

        $minor = Crypt::decryptString($cipher);

        return is_numeric($minor) ? new Money((int) $minor, $currency) : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, string|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if (! $value instanceof Money) {
            return ["{$key}_minor" => null, "{$key}_currency" => null];
        }

        return ["{$key}_minor" => Crypt::encryptString((string) $value->minor), "{$key}_currency" => $value->currency];
    }
}
