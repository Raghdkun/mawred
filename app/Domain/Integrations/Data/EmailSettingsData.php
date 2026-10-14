<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Data;

use Spatie\LaravelData\Data;

/**
 * The email connection form (setup step 7). The password is only sent when it changes; an
 * empty password keeps the stored one.
 */
final class EmailSettingsData extends Data
{
    public function __construct(
        public string $host,
        public int $port,
        public string $security,
        public ?string $username,
        public ?string $password,
        public string $fromAddress,
        public string $fromName,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'host' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9.-]+$/'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'security' => ['required', 'in:starttls,ssl'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:500'],
            'fromAddress' => ['required', 'email:rfc', 'max:255'],
            'fromName' => ['required', 'string', 'max:120'],
        ];
    }
}
