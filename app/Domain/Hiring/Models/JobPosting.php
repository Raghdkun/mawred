<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Models;

use App\Domain\Audit\Concerns\Auditable;
use App\Domain\Forms\Models\Form;
use App\Domain\Hiring\Enums\PostingStatus;
use App\Domain\Hiring\Enums\Workplace;
use App\Domain\Integrations\Models\CognitoSource;
use App\Support\Ids\HasPublicUlid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/**
 * A bilingual advert for a requisition on the careers site (D87). Candidates apply through the
 * posting's form, or the default application form, or a mapped Cognito form (D81). Knock-outs
 * are `mawred.rule/v1` conditions over the answers.
 *
 * @property int $id
 * @property string $ulid
 * @property int $job_requisition_id
 * @property string $slug
 * @property array<string, string>|string $title
 * @property array<string, string>|string|null $summary
 * @property array<string, string>|string $description
 * @property array<string, string>|string|null $location
 * @property Workplace $workplace
 * @property bool $show_salary
 * @property int|null $form_id
 * @property int|null $cognito_source_id
 * @property list<array{field: string, rule: mixed}>|null $knockouts
 * @property array{title?: array<string, string>, description?: array<string, string>}|null $seo
 * @property PostingStatus $status
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $closes_on
 * @property CarbonImmutable $updated_at
 */
#[Guarded(['id', 'ulid'])]
final class JobPosting extends Model
{
    use Auditable;
    use HasPublicUlid;
    use HasTranslations;

    /** @var list<string> */
    public array $translatable = ['title', 'summary', 'description', 'location'];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'draft', 'workplace' => 'onsite', 'show_salary' => false];

    /**
     * @return BelongsTo<JobRequisition, $this>
     */
    public function requisition(): BelongsTo
    {
        return $this->belongsTo(JobRequisition::class, 'job_requisition_id');
    }

    /**
     * @return BelongsTo<Form, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /**
     * @return BelongsTo<CognitoSource, $this>
     */
    public function cognitoSource(): BelongsTo
    {
        return $this->belongsTo(CognitoSource::class);
    }

    /**
     * @return HasMany<JobApplication, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    /** Published, its requisition still open and its closing date not passed. */
    public function isOpen(CarbonImmutable $today): bool
    {
        return $this->status === PostingStatus::Published
            && ($this->closes_on === null || $this->closes_on->toDateString() >= $today->toDateString());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'workplace' => Workplace::class,
            'status' => PostingStatus::class,
            'show_salary' => 'boolean',
            'knockouts' => 'array',
            'seo' => 'array',
            'published_at' => 'immutable_datetime',
            'closes_on' => 'immutable_date',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
