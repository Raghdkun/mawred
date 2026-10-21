<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Support;

use App\Domain\Notifications\Models\EmailSuppression;

/**
 * Whether an address is on the do-not-email list (D127). Every place that sends email checks it.
 */
final class EmailSuppressions
{
    public static function blocks(?string $email): bool
    {
        return $email !== null && $email !== '' && EmailSuppression::query()->where('email', mb_strtolower(trim($email)))->exists();
    }
}
