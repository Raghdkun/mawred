<?php

declare(strict_types=1);

namespace App\Domain\Access\Support;

/**
 * Every permission in Mawred, as `module.action` (brief §6). The matrix UI, seeders and policies
 * all read from here, so a permission that is not listed cannot exist.
 *
 * Scoped modules hold records that belong to people (people, requests, leave…): their grants
 * carry a data scope. Unscoped modules are configuration or reference data (organization, forms,
 * workflows…): a grant is simply on or off and is stored with the `all` scope.
 */
final class PermissionCatalog
{
    public const array ACTIONS = ['view', 'create', 'update', 'delete', 'approve', 'export', 'configure'];

    /**
     * @var array<string, array{actions: list<string>, scoped: bool}>
     */
    public const array MODULES = [
        'setup' => ['actions' => ['view', 'configure'], 'scoped' => false],
        'organization' => ['actions' => ['view', 'create', 'update', 'delete', 'export', 'configure'], 'scoped' => false],
        'people' => ['actions' => ['view', 'create', 'update', 'delete', 'approve', 'export', 'configure'], 'scoped' => true],
        'access' => ['actions' => ['view', 'create', 'update', 'delete', 'configure'], 'scoped' => false],
        'policies' => ['actions' => ['view', 'create', 'update', 'delete', 'export', 'configure'], 'scoped' => false],
        'forms' => ['actions' => ['view', 'create', 'update', 'delete', 'configure'], 'scoped' => false],
        'workflows' => ['actions' => ['view', 'create', 'update', 'delete', 'configure'], 'scoped' => false],
        'requests' => ['actions' => ['view', 'create', 'update', 'delete', 'approve', 'export', 'configure'], 'scoped' => true],
        'leave' => ['actions' => ['view', 'create', 'update', 'delete', 'approve', 'export', 'configure'], 'scoped' => true],
        'scheduling' => ['actions' => ['view', 'create', 'update', 'delete', 'approve', 'export', 'configure'], 'scoped' => true],
        'attendance' => ['actions' => ['view', 'create', 'update', 'delete', 'approve', 'export', 'configure'], 'scoped' => true],
        'calendar' => ['actions' => ['view', 'create', 'update', 'delete'], 'scoped' => true],
        'hiring' => ['actions' => ['view', 'create', 'update', 'delete', 'approve', 'export', 'configure'], 'scoped' => true],
        'training' => ['actions' => ['view', 'create', 'update', 'delete', 'approve', 'export', 'configure'], 'scoped' => true],
        'reports' => ['actions' => ['view', 'create', 'update', 'delete', 'export'], 'scoped' => true],
        'notifications' => ['actions' => ['view', 'configure'], 'scoped' => false],
        'integrations' => ['actions' => ['view', 'create', 'update', 'delete', 'configure'], 'scoped' => false],
        'audit' => ['actions' => ['view', 'export'], 'scoped' => true],
        'system' => ['actions' => ['view'], 'scoped' => false],
    ];

    /**
     * Built-in sensitive fields guarded by field permissions (ADR-0007). Custom fields flagged
     * sensitive register as `employee.custom.<key>` at runtime (Phase 1).
     *
     * @var list<string>
     */
    public const array SENSITIVE_FIELDS = [
        'employee.national_id',
        'employee.passport',
        'employee.residency',
        'employee.salary',
        'employee.bank',
        'employee.date_of_birth',
        'employee.personal_contact',
    ];

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        $permissions = [];

        foreach (self::MODULES as $module => $definition) {
            foreach ($definition['actions'] as $action) {
                $permissions[] = "{$module}.{$action}";
            }
        }

        return $permissions;
    }

    public static function exists(string $permission): bool
    {
        return in_array($permission, self::all(), true);
    }

    public static function isScoped(string $permission): bool
    {
        $module = explode('.', $permission, 2)[0];

        return self::MODULES[$module]['scoped'] ?? false;
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function split(string $permission): array
    {
        $parts = explode('.', $permission, 2);

        return [$parts[0], $parts[1] ?? ''];
    }
}
