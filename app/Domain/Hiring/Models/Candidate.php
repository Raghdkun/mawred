<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Models;

use App\Domain\Audit\Concerns\Auditable;
use App\Domain\Audit\Contracts\MasksAuditedAttributes;
use App\Domain\Hiring\Enums\ApplicationSource;
use App\Support\Crypto\BlindIndex;
use App\Support\Ids\HasPublicUlid;
use App\Support\Search\HasSearchText;
use Carbon\CarbonImmutable;
use Database\Factories\Hiring\CandidateFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A person who applied or was added by a recruiter. One candidate per email address: applying
 * again with the same email adds an application to the same person. A matching phone number on
 * a different email is flagged as a possible duplicate for a recruiter to merge.
 *
 * @property int $id
 * @property string $ulid
 * @property string $name
 * @property string|null $email
 * @property string|null $email_bidx
 * @property string|null $phone
 * @property string|null $phone_bidx
 * @property string $locale
 * @property string|null $location
 * @property ApplicationSource $source
 * @property bool $talent_pool
 * @property CarbonImmutable|null $talent_pool_consented_at
 * @property CarbonImmutable|null $consented_at
 * @property int|null $possible_duplicate_of_id
 * @property int|null $merged_into_id
 * @property CarbonImmutable|null $anonymised_at
 * @property CarbonImmutable $last_activity_at
 * @property CarbonImmutable $created_at
 */
#[Guarded(['id', 'ulid', 'search_text', 'email_bidx', 'phone_bidx'])]
#[UseFactory(CandidateFactory::class)]
final class Candidate extends Model implements MasksAuditedAttributes
{
    use Auditable;

    /** @use HasFactory<CandidateFactory> */
    use HasFactory;

    use HasPublicUlid;
    use HasSearchText;

    /** @var array<string, mixed> */
    protected $attributes = ['locale' => 'en', 'talent_pool' => false, 'email' => null, 'phone' => null, 'location' => null];

    public static function emailHash(?string $email): ?string
    {
        return BlindIndex::of($email === null ? null : mb_strtolower(trim($email)), 'candidates.email');
    }

    public static function phoneHash(?string $phone): ?string
    {
        return BlindIndex::of($phone, 'candidates.phone');
    }

    /**
     * @return BelongsToMany<HiringTag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(HiringTag::class, 'candidate_tag');
    }

    /**
     * @return HasMany<JobApplication, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    /**
     * @return HasMany<CandidateMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(CandidateMessage::class)->latest('created_at')->orderByDesc('id');
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function possibleDuplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'possible_duplicate_of_id');
    }

    public function searchableValues(): array
    {
        return [$this->name, $this->location];
    }

    public function auditMaskedAttributes(): array
    {
        return ['email', 'phone'];
    }

    protected static function booted(): void
    {
        self::saving(function (self $candidate): void {
            if ($candidate->isDirty('email')) {
                $candidate->setAttribute('email_bidx', self::emailHash($candidate->email));
            }

            if ($candidate->isDirty('phone')) {
                $candidate->setAttribute('phone_bidx', self::phoneHash($candidate->phone));
            }
        });
    }

    /**
     * @return list<string>
     */
    protected function auditExcept(): array
    {
        return ['search_text', 'email_bidx', 'phone_bidx', 'last_activity_at'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email' => 'encrypted',
            'phone' => 'encrypted',
            'source' => ApplicationSource::class,
            'talent_pool' => 'boolean',
            'talent_pool_consented_at' => 'immutable_datetime',
            'consented_at' => 'immutable_datetime',
            'anonymised_at' => 'immutable_datetime',
            'last_activity_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
