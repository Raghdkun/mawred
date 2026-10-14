<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Engine\Nodes;

use App\Domain\Workflows\Engine\AssigneeResolver;

/** Checks an assignee spec on publish. */
final class AssigneeSpec
{
    /**
     * @return list<string>
     */
    public static function problems(mixed $spec): array
    {
        $type = is_array($spec) && is_string($spec['type'] ?? null) ? $spec['type'] : null;

        if ($type === null || ! in_array($type, AssigneeResolver::TYPES, true)) {
            return [__('Choose who this goes to.')];
        }

        return match ($type) {
            'user' => is_string($spec['user'] ?? null) ? [] : [__('Choose a person.')],
            'role', 'round_robin' => is_string($spec['role'] ?? null) ? [] : [__('Choose a role.')],
            'field' => is_string($spec['field'] ?? null) ? [] : [__('Choose the form field that names the person.')],
            default => [],
        };
    }
}
