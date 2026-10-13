<?php

declare(strict_types=1);

namespace App\Domain\People\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Sensitive fields (ADR-0007). Each group is optional: a group that is sent is written only when
 * the actor has write access to its field key; a group that is left out is untouched.
 */
#[TypeScript]
final class SensitiveDetailsInputData extends Data
{
    /**
     * @param  array{number: string|null}|Optional  $nationalId
     * @param  array{number: string|null, country?: string|null, expiresOn?: string|null}|Optional  $passport
     * @param  array{number: string|null, expiresOn?: string|null}|Optional  $residency
     * @param  array{bankName?: string|null, iban?: string|null, accountName?: string|null}|Optional  $bank
     * @param  array{email?: string|null, phone?: string|null, address?: string|null, emergencyName?: string|null, emergencyPhone?: string|null, emergencyRelation?: string|null}|Optional  $personalContact
     */
    public function __construct(
        public array|Optional $nationalId = new Optional,
        public array|Optional $passport = new Optional,
        public array|Optional $residency = new Optional,
        public array|Optional $bank = new Optional,
        public array|Optional $personalContact = new Optional,
        public string|null|Optional $dateOfBirth = new Optional,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'nationalId' => ['sometimes', 'array'],
            'nationalId.number' => ['nullable', 'string', 'max:40'],
            'passport' => ['sometimes', 'array'],
            'passport.number' => ['nullable', 'string', 'max:40'],
            'passport.country' => ['nullable', 'string', 'size:2', 'alpha'],
            'passport.expiresOn' => ['nullable', 'date_format:Y-m-d'],
            'residency' => ['sometimes', 'array'],
            'residency.number' => ['nullable', 'string', 'max:40'],
            'residency.expiresOn' => ['nullable', 'date_format:Y-m-d'],
            'bank' => ['sometimes', 'array'],
            'bank.bankName' => ['nullable', 'string', 'max:120'],
            'bank.iban' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z]{2}[0-9]{2}[A-Za-z0-9 ]{8,36}$/'],
            'bank.accountName' => ['nullable', 'string', 'max:160'],
            'personalContact' => ['sometimes', 'array'],
            'personalContact.email' => ['nullable', 'email', 'max:254'],
            'personalContact.phone' => ['nullable', 'string', 'max:32'],
            'personalContact.address' => ['nullable', 'string', 'max:500'],
            'personalContact.emergencyName' => ['nullable', 'string', 'max:160'],
            'personalContact.emergencyPhone' => ['nullable', 'string', 'max:32'],
            'personalContact.emergencyRelation' => ['nullable', 'string', 'max:60'],
            'dateOfBirth' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before:today'],
        ];
    }
}
