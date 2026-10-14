<?php

declare(strict_types=1);

namespace App\Domain\People\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Sensitive fields the viewer may see. `access` lists only readable field keys ('read' or
 * 'write'); anything absent is hidden and its value is null (ADR-0007).
 */
#[TypeScript]
final class SensitiveDetailsData extends Data
{
    /**
     * @param  array<string, string>  $access
     * @param  array{number: string|null, country: string|null, expiresOn: string|null}|null  $passport
     * @param  array{number: string|null, expiresOn: string|null}|null  $residency
     * @param  array<string, string|null>|null  $bank
     * @param  array<string, string|null>|null  $personalContact
     */
    public function __construct(
        public array $access,
        public ?string $nationalId = null,
        public ?array $passport = null,
        public ?array $residency = null,
        public ?array $bank = null,
        public ?array $personalContact = null,
        public ?string $dateOfBirth = null,
    ) {}
}
