<?php

declare(strict_types=1);

namespace App\Domain\Organization\Enums;

use App\Domain\Organization\Models\CostCenter;
use App\Domain\Organization\Models\EmploymentType;
use App\Domain\Organization\Models\Grade;
use Illuminate\Database\Eloquent\Model;

/**
 * The simple code + name lists HR maintains next to the structure (screen 39 "Types & grades").
 */
enum CatalogKind: string
{
    case EmploymentType = 'employment-types';
    case Grade = 'grades';
    case CostCenter = 'cost-centers';

    /**
     * @return class-string<EmploymentType|Grade|CostCenter>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::EmploymentType => EmploymentType::class,
            self::Grade => Grade::class,
            self::CostCenter => CostCenter::class,
        };
    }

    public function table(): string
    {
        return (new ($this->modelClass()))->getTable();
    }

    public function hasRank(): bool
    {
        return $this === self::Grade;
    }

    /**
     * @return EmploymentType|Grade|CostCenter
     */
    public function newModel(): Model
    {
        return new ($this->modelClass());
    }
}
