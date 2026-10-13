<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Domain\Access\Models\User;
use Illuminate\Auth\AuthenticationException;

/**
 * Typed access to the authenticated user inside form requests on authenticated routes.
 */
trait ResolvesAuthenticatedUser
{
    protected function authenticatedUser(): User
    {
        $user = $this->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }
}
