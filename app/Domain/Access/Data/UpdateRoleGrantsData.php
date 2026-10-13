<?php

declare(strict_types=1);

namespace App\Domain\Access\Data;

use App\Domain\Access\Enums\Scope;
use App\Domain\Access\Support\PermissionCatalog;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Matrix "Save": the full set of grants for a role (permission => scope).
 */
#[TypeScript]
final class UpdateRoleGrantsData extends Data
{
    /**
     * @param  array<string, string>  $grants
     */
    public function __construct(public array $grants) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'grants' => ['present', 'array'],
            'grants.*' => ['required', Rule::enum(Scope::class)],
        ];
    }

    /**
     * @return array<string, Scope>
     */
    public function toScopes(): array
    {
        $scopes = [];

        foreach ($this->grants as $permission => $scope) {
            if (PermissionCatalog::exists((string) $permission)) {
                $scopes[(string) $permission] = Scope::from($scope);
            }
        }

        return $scopes;
    }
}
