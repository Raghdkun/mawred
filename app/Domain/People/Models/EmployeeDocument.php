<?php

declare(strict_types=1);

namespace App\Domain\People\Models;

use App\Domain\Audit\Concerns\Auditable;
use App\Domain\Audit\Contracts\MasksAuditedAttributes;
use App\Support\Ids\HasPublicUlid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * An employee document (contract, ID copy, certificate…) with its file on the private disk.
 * Files are only served through an authorised, short-lived download route.
 *
 * @property int $id
 * @property string $ulid
 * @property int $employee_id
 * @property int $document_type_id
 * @property string|null $number
 * @property CarbonImmutable|null $issued_on
 * @property CarbonImmutable|null $expires_on
 * @property string|null $notes
 * @property list<int> $reminders_sent
 * @property int|null $uploaded_by
 */
#[Guarded(['id', 'ulid'])]
final class EmployeeDocument extends Model implements HasMedia, MasksAuditedAttributes
{
    use Auditable;
    use HasPublicUlid;
    use InteractsWithMedia;
    use SoftDeletes;

    public const string DISK = 'local';

    /** @var list<string> */
    public const array MIME_TYPES = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'number' => null,
        'issued_on' => null,
        'expires_on' => null,
        'notes' => null,
        'reminders_sent' => '[]',
        'uploaded_by' => null,
    ];

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<DocumentType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id')->withTrashed();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('file')
            ->singleFile()
            ->useDisk(self::DISK)
            ->acceptsMimeTypes(self::MIME_TYPES);
    }

    public function auditMaskedAttributes(): array
    {
        return ['number'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'number' => 'encrypted',
            'issued_on' => 'immutable_date',
            'expires_on' => 'immutable_date',
            'reminders_sent' => 'array',
        ];
    }
}
