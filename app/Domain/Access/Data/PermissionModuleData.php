<?php

declare(strict_types=1);

namespace App\Domain\Access\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One row of the permission matrix (screen 38): a module with its available actions.
 */
#[TypeScript]
final class PermissionModuleData extends Data
{
    /**
     * @param  list<string>  $actions
     */
    public function __construct(
        public string $module,
        public array $actions,
        public bool $scoped,
    ) {}
}
