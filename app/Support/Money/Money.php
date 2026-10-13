<?php

declare(strict_types=1);

namespace App\Support\Money;

use InvalidArgumentException;
use JsonSerializable;
use NumberFormatter;

/**
 * An amount in minor units with its ISO 4217 currency (ADR-0022). No FX: amounts in different
 * currencies are never combined.
 */
final readonly class Money implements JsonSerializable
{
    public function __construct(
        public int $minor,
        public string $currency,
    ) {
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvalidArgumentException("Invalid ISO 4217 currency [{$currency}].");
        }
    }

    public static function fromDecimal(string|int|float $amount, string $currency): self
    {
        $digits = self::fractionDigits($currency);

        return new self((int) round((float) $amount * (10 ** $digits)), strtoupper($currency));
    }

    /** ISO 4217 minor units, e.g. 2 for SAR/USD, 3 for KWD/BHD/OMR, 0 for JPY. */
    public static function fractionDigits(string $currency): int
    {
        $formatter = new NumberFormatter('en@currency='.strtoupper($currency), NumberFormatter::CURRENCY);

        return $formatter->getAttribute(NumberFormatter::FRACTION_DIGITS);
    }

    public function toDecimal(): string
    {
        $digits = self::fractionDigits($this->currency);

        return number_format($this->minor / (10 ** $digits), $digits, '.', '');
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor + $other->minor, $this->currency);
    }

    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor - $other->minor, $this->currency);
    }

    public function format(string $locale = 'en'): string
    {
        return (string) (new NumberFormatter($locale, NumberFormatter::CURRENCY))
            ->formatCurrency((float) $this->toDecimal(), $this->currency);
    }

    /**
     * @return array{amount: string, minor: int, currency: string}
     */
    public function jsonSerialize(): array
    {
        return ['amount' => $this->toDecimal(), 'minor' => $this->minor, 'currency' => $this->currency];
    }

    private function assertSameCurrency(self $other): void
    {
        if ($other->currency !== $this->currency) {
            throw new InvalidArgumentException("Cannot combine {$this->currency} with {$other->currency}.");
        }
    }
}
