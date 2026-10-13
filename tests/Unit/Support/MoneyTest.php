<?php

declare(strict_types=1);

use App\Support\Money\Money;

it('uses ISO 4217 minor units per currency', function (string $currency, int $digits): void {
    expect(Money::fractionDigits($currency))->toBe($digits);
})->with([
    ['SAR', 2],
    ['USD', 2],
    ['KWD', 3],
    ['BHD', 3],
    ['JPY', 0],
]);

it('round-trips decimal amounts', function (): void {
    $money = Money::fromDecimal('1250.755', 'KWD');

    expect($money->minor)->toBe(1250755)
        ->and($money->toDecimal())->toBe('1250.755');
});

it('refuses to combine currencies', function (): void {
    expect(fn (): Money => (new Money(100, 'SAR'))->plus(new Money(100, 'USD')))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects invalid currency codes', function (): void {
    expect(fn (): Money => new Money(1, 'sar'))->toThrow(InvalidArgumentException::class);
});
