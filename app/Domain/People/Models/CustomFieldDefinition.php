<?php

declare(strict_types=1);

namespace App\Domain\People\Models;

use App\Domain\Audit\Concerns\Auditable;
use App\Domain\People\Enums\CustomFieldType;
use App\Support\Ids\HasPublicUlid;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

/**
 * HR-defined employee field. Sensitive definitions are encrypted at rest and guarded by the
 * field permission `employee.custom.<key>` (ADR-0007).
 *
 * @property int $id
 * @property string $ulid
 * @property string $key
 * @property array<string, string>|string $label
 * @property array<string, string>|string|null $help
 * @property CustomFieldType $type
 * @property list<array{value: string, label: array<string, string>}>|null $options
 * @property string $section
 * @property bool $is_required
 * @property bool $is_sensitive
 * @property bool $is_active
 * @property int $sort_order
 */
#[Guarded(['id', 'ulid'])]
final class CustomFieldDefinition extends Model
{
    use Auditable;
    use HasPublicUlid;
    use HasTranslations;
    use SoftDeletes;

    public const string FIELD_KEY_PREFIX = 'employee.custom.';

    /** @var list<string> */
    public array $translatable = ['label', 'help'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'help' => null,
        'options' => null,
        'section' => 'personal',
        'is_required' => false,
        'is_sensitive' => false,
        'is_active' => true,
        'sort_order' => 0,
    ];

    public function fieldKey(): string
    {
        return self::FIELD_KEY_PREFIX.$this->key;
    }

    /**
     * @return list<string>
     */
    public function optionValues(): array
    {
        return array_map(fn (array $option): string => $option['value'], $this->options ?? []);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CustomFieldType::class,
            'options' => 'array',
            'is_required' => 'boolean',
            'is_sensitive' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
