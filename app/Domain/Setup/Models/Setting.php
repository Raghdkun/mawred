<?php

declare(strict_types=1);

namespace App\Domain\Setup\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $group
 * @property string $key
 * @property mixed $value
 */
#[Fillable(['group', 'key', 'value'])]
final class Setting extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['value' => 'json'];
    }
}
