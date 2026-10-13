<?php

declare(strict_types=1);

namespace App\Domain\Access\Enums;

/**
 * Data scope attached to a role's permission grant (ADR-0005). Ordered from narrowest to widest.
 *
 * The permission matrix shows "Team", which expands to DirectReports or TeamTree (D29).
 */
enum Scope: string
{
    case Own = 'own';
    case DirectReports = 'direct_reports';
    case TeamTree = 'team_tree';
    case Department = 'department';
    case Branch = 'branch';
    case All = 'all';

    public function rank(): int
    {
        return match ($this) {
            self::Own => 1,
            self::DirectReports => 2,
            self::TeamTree => 3,
            self::Department => 4,
            self::Branch => 5,
            self::All => 6,
        };
    }

    public function isWiderThan(self $other): bool
    {
        return $this->rank() > $other->rank();
    }

    /** Scopes that depend on the organisation structure (reporting lines, departments, branches). */
    public function isOrgRelative(): bool
    {
        return in_array($this, [self::DirectReports, self::TeamTree, self::Department, self::Branch], true);
    }

    /** Scopes that may be narrowed to explicit anchors on a role assignment (D5). */
    public function acceptsAnchors(): bool
    {
        return in_array($this, [self::Department, self::Branch], true);
    }
}
