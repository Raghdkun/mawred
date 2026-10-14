<?php

declare(strict_types=1);

namespace App\Domain\Policies\Data;

use App\Domain\Policies\Keys\LeaveRuleKey;
use App\Domain\Policies\Keys\PolicyKey;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Registry entry for the editor: which editor to draw, the default, and the leave type it
 * belongs to for `leave.type.*` keys.
 */
#[TypeScript]
final class PolicyKeyData extends Data
{
    /**
     * @param  array<string, mixed>  $default
     * @param  list<string>  $affects
     */
    public function __construct(
        public string $id,
        public string $section,
        public string $editor,
        public array $default,
        public bool $branchFallback,
        public array $affects,
        public ?LeaveTypeData $leaveType,
    ) {}

    public static function fromKey(PolicyKey $key): self
    {
        return new self(
            id: $key->id(),
            section: $key->section()->value,
            editor: $key->editor(),
            default: $key->serialize($key->default()),
            branchFallback: $key->hasBranchFallback(),
            affects: $key->affects(),
            leaveType: $key instanceof LeaveRuleKey ? LeaveTypeData::fromType($key->type) : null,
        );
    }
}
