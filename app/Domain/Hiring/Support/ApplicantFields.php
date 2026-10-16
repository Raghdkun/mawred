<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Support;

use App\Domain\Forms\Enums\FieldType;
use App\Domain\Forms\Support\FieldSpec;
use App\Domain\Forms\Support\FormSchema;

/**
 * Finds who applied in an application form's answers (D81): the fields keyed `full_name`,
 * `email`, `phone` and `cv`, or else the first name-like text, email, phone and file fields.
 * A form can be used for applications only when it has a name and an email.
 */
final readonly class ApplicantFields
{
    private function __construct(
        public ?string $nameKey,
        public ?string $emailKey,
        public ?string $phoneKey,
        public ?string $cvKey,
    ) {}

    /**
     * @param  array<mixed>  $schema
     */
    public static function of(array $schema): self
    {
        $fields = FormSchema::fieldsByKey($schema);
        $first = function (string $preferred, FieldType ...$types) use ($fields): ?string {
            if (isset($fields[$preferred]) && in_array(FieldSpec::type($fields[$preferred]), $types, true)) {
                return $preferred;
            }

            foreach ($fields as $key => $field) {
                if (in_array(FieldSpec::type($field), $types, true)) {
                    return $key;
                }
            }

            return null;
        };

        return new self(
            $first('full_name', FieldType::Text),
            $first('email', FieldType::Email),
            $first('phone', FieldType::Phone),
            $first('cv', FieldType::File),
        );
    }

    public function usable(): bool
    {
        return $this->nameKey !== null && $this->emailKey !== null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{name: string, email: string|null, phone: string|null, cv: string|null}
     */
    public function read(array $data): array
    {
        $string = fn (?string $key): ?string => $key !== null && is_string($data[$key] ?? null) && trim($data[$key]) !== '' ? trim($data[$key]) : null;
        $cv = $this->cvKey !== null && is_array($data[$this->cvKey] ?? null) ? $data[$this->cvKey] : [];
        $firstFile = is_array($cv[0] ?? null) && is_string($cv[0]['id'] ?? null) ? $cv[0]['id'] : null;

        return [
            'name' => mb_substr($string($this->nameKey) ?? '', 0, 160),
            'email' => $string($this->emailKey),
            'phone' => $string($this->phoneKey),
            'cv' => $firstFile,
        ];
    }
}
