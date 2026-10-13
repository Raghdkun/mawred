<?php

declare(strict_types=1);

namespace App\Domain\Access\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property string|null $module
 * @property string|null $action
 */
final class Permission extends SpatiePermission {}
