<?php

declare(strict_types=1);

use App\Domain\Access\Support\AccessEpoch;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `organization.*` became unscoped reference data in Phase 1. Grants saved earlier with a
 * narrower scope would now fail closed, so they become `all`, as SyncRoleGrants stores them.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('role_permission_scopes')
            ->whereIn('permission_id', DB::table('permissions')->select('id')->where('name', 'like', 'organization.%'))
            ->where('scope', '!=', 'all')
            ->update(['scope' => 'all']);

        // Cached access is keyed by an epoch; bumping it invalidates every cached grant.
        resolve(AccessEpoch::class)->bump();
    }

    public function down(): void
    {
        // Narrower scopes cannot be restored and are meaningless for reference data.
    }
};
