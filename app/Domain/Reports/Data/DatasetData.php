<?php

declare(strict_types=1);

namespace App\Domain\Reports\Data;

use App\Domain\Access\Models\User;
use App\Domain\Reports\Datasets\BaseDataset;
use App\Domain\Reports\Datasets\DatasetField;
use App\Domain\Reports\Enums\ReportCategory;
use Illuminate\Database\Eloquent\Model;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A dataset in the builder's first step, with the fields this person may use (D121). */
#[TypeScript]
final class DatasetData extends Data
{
    /**
     * @param  list<DatasetFieldData>  $fields
     */
    public function __construct(
        public string $key,
        public ReportCategory $category,
        public string $title,
        public string $description,
        public array $fields,
    ) {}

    /**
     * @param  BaseDataset<covariant Model>  $dataset
     */
    public static function fromDataset(BaseDataset $dataset, User $user, string $locale): self
    {
        $pick = $locale === 'ar' ? 'ar' : 'en';

        return new self(
            $dataset->key(),
            $dataset->category(),
            $dataset->title()[$pick],
            $dataset->description()[$pick],
            array_values(array_map(
                fn (DatasetField $field): DatasetFieldData => DatasetFieldData::fromField($field, $locale),
                array_filter($dataset->fields(), fn (DatasetField $field): bool => $dataset->visible($user, $field)),
            )),
        );
    }
}
