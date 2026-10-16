<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Hiring\Actions\PurgeCandidates;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('hiring:purge')]
#[Description('Anonymise candidates past the retention period (D86, D96).')]
final class HiringPurge extends Command
{
    public function handle(PurgeCandidates $purge): int
    {
        $count = $purge->handle(CarbonImmutable::now());
        $this->info("Anonymised {$count} candidate(s).");

        return self::SUCCESS;
    }
}
