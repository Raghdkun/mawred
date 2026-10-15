<?php

declare(strict_types=1);

namespace App\Domain\Requests\Models;

use App\Domain\Audit\Concerns\Auditable;
use App\Domain\Forms\Models\Form;
use App\Domain\Requests\Enums\RequestCategory;
use App\Domain\Workflows\Models\WorkflowDefinition;
use App\Support\Ids\HasPublicUlid;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

/**
 * What people can request (handoff 10): a form plus the workflow that decides it. Types whose
 * module ships later stay hidden until then (D55). Sensitive types need sign-in for link
 * approvals (D24).
 *
 * @property int $id
 * @property string $ulid
 * @property string $code
 * @property string $source native|cognito
 * @property array<string, string>|string $name
 * @property array<string, string>|string|null $description
 * @property RequestCategory $category
 * @property string|null $icon
 * @property int $form_id
 * @property int $workflow_definition_id
 * @property bool $is_sensitive
 * @property int $available_in_phase
 * @property bool $is_active
 * @property int $sort_order
 */
#[Guarded(['id', 'ulid'])]
final class RequestType extends Model
{
    use Auditable;
    use HasPublicUlid;
    use HasTranslations;
    use SoftDeletes;

    /** @var list<string> */
    public array $translatable = ['name', 'description'];

    /** @var array<string, mixed> */
    protected $attributes = ['description' => null, 'icon' => null, 'is_sensitive' => false, 'available_in_phase' => 0, 'is_active' => true, 'sort_order' => 0, 'source' => 'native'];

    /**
     * @return BelongsTo<Form, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class)->withTrashed();
    }

    /**
     * @return BelongsTo<WorkflowDefinition, $this>
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'workflow_definition_id')->withTrashed();
    }

    /** Live now, or waiting for its module (D55). */
    public function isAvailable(): bool
    {
        return $this->is_active && $this->available_in_phase <= config()->integer('mawred.phase', 0);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['category' => RequestCategory::class, 'is_sensitive' => 'boolean', 'is_active' => 'boolean', 'available_in_phase' => 'integer', 'sort_order' => 'integer'];
    }
}
