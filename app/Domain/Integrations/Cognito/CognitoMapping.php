<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Cognito;

use App\Domain\Access\Models\User;
use App\Domain\Integrations\Models\CognitoSource;
use App\Domain\People\Models\Employee;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Arr;

/**
 * Reads a source's mapping (ADR-0015). Cognito's JSON keys are the form's internal field
 * names; nested values use dots (`Name.First`, `Entry.Version`). Cognito doesn't document
 * its payload envelope (D19), so the entry id and version paths are configurable.
 */
final readonly class CognitoMapping
{
    public const string DEFAULT_ENTRY_PATH = 'Id';

    public const string DEFAULT_VERSION_PATH = 'Entry.Version';

    /**
     * @param  list<array{from: string, to: string}>  $fields
     * @param  array<string, string>  $prefill  email|name|employee_number => Cognito path
     */
    public function __construct(
        public array $fields,
        public string $requesterBy,
        public string $requesterPath,
        public string $entryPath,
        public string $versionPath,
        public array $prefill,
    ) {}

    public static function of(CognitoSource $source): self
    {
        $mapping = $source->mapping;
        $fields = [];

        foreach (is_array($mapping['fields'] ?? null) ? $mapping['fields'] : [] as $row) {
            if (is_array($row) && is_string($row['from'] ?? null) && is_string($row['to'] ?? null) && $row['from'] !== '' && $row['to'] !== '') {
                $fields[] = ['from' => $row['from'], 'to' => $row['to']];
            }
        }

        $requester = is_array($mapping['requester'] ?? null) ? $mapping['requester'] : [];
        $identity = is_array($mapping['identity'] ?? null) ? $mapping['identity'] : [];
        $prefill = [];

        foreach (is_array($mapping['prefill'] ?? null) ? $mapping['prefill'] : [] as $key => $path) {
            if (in_array($key, ['email', 'name', 'employee_number'], true) && is_string($path) && $path !== '') {
                $prefill[$key] = $path;
            }
        }

        return new self(
            fields: $fields,
            requesterBy: ($requester['by'] ?? null) === 'employee_number' ? 'employee_number' : 'email',
            requesterPath: is_string($requester['path'] ?? null) && $requester['path'] !== '' ? $requester['path'] : 'Email',
            entryPath: is_string($identity['entry'] ?? null) && $identity['entry'] !== '' ? $identity['entry'] : self::DEFAULT_ENTRY_PATH,
            versionPath: is_string($identity['version'] ?? null) && $identity['version'] !== '' ? $identity['version'] : self::DEFAULT_VERSION_PATH,
            prefill: $prefill,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function scalar(array $payload, string $path): ?string
    {
        $value = Arr::get($payload, $path);

        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }

    /**
     * Answers for the request type's form, from the mapped Cognito fields.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function answers(array $payload): array
    {
        $answers = [];

        foreach ($this->fields as $row) {
            $answers[$row['to']] = Arr::get($payload, $row['from']);
        }

        return $answers;
    }

    /**
     * The employee the entry is about, by work email (or account email) or employee number.
     *
     * @param  array<string, mixed>  $payload
     */
    public function employee(array $payload): ?Employee
    {
        $value = self::scalar($payload, $this->requesterPath);

        if ($value === null) {
            return null;
        }

        if ($this->requesterBy === 'employee_number') {
            return Employee::query()->where('employee_number', trim($value))->whereNull('termination_date')->first();
        }

        $email = mb_strtolower(trim($value));
        $userId = User::query()->whereRaw('lower(email) = ?', [$email])->value('id');

        return Employee::query()->whereNull('termination_date')
            ->where(fn (Builder $q) => $q->whereRaw('lower(work_email) = ?', [$email])->when($userId !== null, fn ($q) => $q->orWhere('user_id', $userId)))
            ->first();
    }

    /**
     * The hosted form link with the person's details filled in (D60). Only name, email and
     * employee number: Cognito prefill values travel in the URL in plain text.
     */
    public function prefillUrl(string $formUrl, Employee $employee, ?User $user, string $locale): string
    {
        $values = array_filter([
            'email' => $employee->work_email ?? $user?->email,
            'name' => $employee->displayName($locale),
            'employee_number' => $employee->employee_number,
        ], fn (?string $value): bool => $value !== null && $value !== '');
        $entry = [];

        foreach ($this->prefill as $key => $path) {
            if (isset($values[$key])) {
                Arr::set($entry, $path, $values[$key]);
            }
        }

        if ($entry === []) {
            return $formUrl;
        }

        return $formUrl.(str_contains($formUrl, '?') ? '&' : '?').'entry='.rawurlencode((string) json_encode($entry, JSON_UNESCAPED_UNICODE));
    }
}
