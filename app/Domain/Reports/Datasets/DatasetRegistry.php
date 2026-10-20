<?php

declare(strict_types=1);

namespace App\Domain\Reports\Datasets;

use App\Domain\Access\Models\User;
use Illuminate\Database\Eloquent\Model;

/** The datasets the custom builder reads from (D121), in the order the builder lists them. */
final class DatasetRegistry
{
    /** @var list<class-string<BaseDataset<covariant Model>>> */
    public const array DATASETS = [
        PeopleDataset::class,
        LeaveDataset::class,
        AttendanceDataset::class,
        RequestsDataset::class,
        ApplicationsDataset::class,
        EnrollmentsDataset::class,
    ];

    /** @var array<string, BaseDataset<covariant Model>>|null */
    private ?array $datasets = null;

    /** @return BaseDataset<covariant Model>|null */
    public function find(string $key): ?BaseDataset
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * @return array<string, BaseDataset<covariant Model>>
     */
    public function all(): array
    {
        if ($this->datasets === null) {
            $this->datasets = [];

            foreach (self::DATASETS as $class) {
                $dataset = resolve($class);
                $this->datasets[$dataset->key()] = $dataset;
            }
        }

        return $this->datasets;
    }

    /**
     * The datasets this person can build on.
     *
     * @return list<BaseDataset<covariant Model>>
     */
    public function for(User $user): array
    {
        return array_values(array_filter($this->all(), fn (BaseDataset $dataset): bool => $dataset->canUse($user)));
    }
}
