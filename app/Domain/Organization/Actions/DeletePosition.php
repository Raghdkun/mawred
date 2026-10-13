<?php

declare(strict_types=1);

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Models\Position;

final readonly class DeletePosition
{
    public function handle(Position $position): void
    {
        $position->delete();
    }
}
